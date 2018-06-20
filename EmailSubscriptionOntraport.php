<?php

namespace App\Classes;

use GuzzleHttp\Client;
use Illuminate\Http\Response;

class EmailSubscriptionOntraport extends EmailSubscription {

  private $app_id; // Ontraport App id
  private $api_key; // Ontraport API key

  // Standardized JSON Keys and Values for the client
  const OUT_STATUS = 'status';
  const OUT_MESSAGE = 'message';
  const OUT_STATUS_SUCCESS = 'success';

  const ONTRAPORT_DOMAIN = 'https://api.ontraport.com/1';
  const ENDPOINT_CONTACTS = '/Contacts';
  const ENDPOINT_TAGS = '/objects/tagByName';

  /**
   * Contact Objects in Ontraport have this ID that needs to be referenced to
   * perform actions like attaching tags to them (for categorization of contacts)
   */
  const CONTACT_OBJECT_TYPE_ID = '0';

  /**
   * These are fields names that need to be passed when creating an ontraport
   * contact.
   */
  const FIELD_FIRST_NAME = 'firstname';
  const FIELD_EMAIL = 'email';
  /**
   * ID field refers to the ID of a contact once created with Ontraport
   */
  const FIELD_CONTACT_ID = 'id';
  /**
   * IDs key refers to an array of IDs which point to objects that we can
   * attach tags to when doing a tagging request
   */
  const KEY_CONTACT_IDS = 'ids';
  /**
   * The add_names key refers to an array of name strings that refer to tags
   * that we want to attach to referenced objects inside ontraport
   */
  const KEY_ADD_NAMES = 'add_names';
  /**
   * Source location should be the full URL of the location from which a user
   * was registered with Ontraport.
   */
  const FIELD_SOURCE_LOCATION = 'source_location';

  /**
   * These are field names that need to be passed when associated an ontraport
   * contact with a tag which we want to categorize the contact with. For example,
   * we can associate a user with a tag for article_sidebar in order to keep
   * track of users who registered using the form on our website.
   */
  /**
   * The object ID field refers to which type of object we are attaching the tags
   * to. The object type we will likely be referring to are contacts. The constant
   * for that object type is referenced below.
   */
  const FIELD_OBJECT_ID = 'objectID';
  /**
   * The add list field refers to the comma-separated list of tag ids that we
   * are attaching to the the object(s).
   */
  const FIELD_ADD_LIST = 'add_list';
  /**
   * The ids field refers to the comma-separated list of object ids that we are
   * attaching tags to.
   */
  const FIELD_IDS = 'ids';
  /**
   * Successful ontraport responses have this field to indicate a successful call.
   */
  const FIELD_CODE = 'code';
  /**
   * When making a GET request to find contacts, we can provide this parameter
   * do perform filtering
   */
  const ONTRAPORT_PARAMETER_CONDITION = 'condition';
  /**
   * When an Ontraport API request comes back successful, this is the code that it has.
   */
  const ONTRAPORT_CODE_SUCCESS = 0;

  /**
   * The Ontraport Object Type referring to contacts.
   */
  const OBJECT_TYPE_CONTACTS = '0';

  /**
   * Key on response object pointing to data.
   */
  const RESPONSE_DATA = 'data';

  const GUZZLE_HEADERS = 'headers';
  /**
   * Required when sending Guzzle request of type x-www-form-urlencoded
   */
  const GUZZLE_FORM_PARAMETERS = 'form_params';
  /**
   * Required when sending Guzzle request with GET request parameters
   */
  const GUZZLE_QUERY = 'query';
  /**
   * Required when sending Guzzle request of type application/json
   */
  const GUZZLE_JSON = 'json';

  const ONTRAPORT_HEADER_APP_ID = 'Api-Appid';
  const ONTRAPORT_HEADER_API_KEY = 'Api-key';

  /**
   * The ontraport tags that are accepted by this service
   */
  private $ontraport_tags = [];

  private $httpClient;

  /**
   * Get the configuration from the .env file to interact with the Ontraport API
   *
   * EmailSubscriptionOntraport constructor.
   */
  public function __construct( Client $httpClient ) {
    $this->setAppId( env( 'ONTRAPORT_APP_ID', null ) );
    $this->setApiKey( env( 'ONTRAPORT_API_KEY', null ) );

    $this->interpretOntraportTags();

    $this->httpClient = $httpClient;
  }

  /**
   * Reads .env file to find allowed Ontraport tags
   *
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function interpretOntraportTags() {
    $ontraport_tags_from_env = env( 'ONTRAPORT_ALLOWED_TAGS', '' );

    $ontraport_tags_array = explode( ',', $ontraport_tags_from_env );

    // Values shouldn't have leading or trailing whitespace
    foreach ( $ontraport_tags_array as $id => $tag_name ) {
      $ontraport_tags_array[ $id ] = trim( $tag_name );
    }

    $this->ontraport_tags = $ontraport_tags_array;
  }

  /**
   * Main handler for subscribing users to ontraport tags. Tags are similar to
   * lists for other subscription services. Contacts can have multiple tags
   * assigned to them and then campaigns can be targeted to all contacts who
   * have particular tags applied to them.
   *
   * @param string|null $first_name
   * @param string $email
   * @param string $tag
   * @param string|null $source_location
   *
   * @return \Illuminate\Http\JsonResponse|null|\Psr\Http\Message\ResponseInterface
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  public function subscribe( string $first_name = null, string $email, string $tag, string $source_location = null ) {
    /**
     * Ensure that we are able to process this request:
     *
     * We need the API key and App ID to be known to our application and we
     * need the tag that we are trying to register the user to be known such
     * that the client application cannot create new tags without our consent.
     */
    $ontraport_correctly_configured = $this->ontraportCorrectlyConfigured();
    $ontraport_tag_exists           = $this->ontraportTagExists( $tag );
    $correct_configuration_and_tag  = ( $ontraport_correctly_configured && $ontraport_tag_exists );
    /**
     * TODO: May want to check if an existing Ontraport contact exists but does
     * TODO: not yet have a name associated with their account. We can use
     * TODO: subsequent requests to add a name, this would be ideal and expected
     * TODO: behaviour. - Andrei
     */
    $response = null;
    if ( $correct_configuration_and_tag ) {
      $response = $this->addContactWithTagResponse( $first_name, $email, $tag, $source_location );
    } else if ( ! $ontraport_tag_exists ) {
      $response = $this->cannotAssignTagResponse();
    } else {
      $response = $this->unconfiguredResponse();
    }

    return $response;
  }

  /**
   * Determines if we are able to handle the tag name. Only accept certain
   *
   * @param string $tag_name
   *
   * @return bool
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function ontraportTagExists( string $tag_name ): bool {
    $tag_exists = true;

    if ( ! in_array( $tag_name, $this->ontraport_tags ) ) {
      $tag_exists = false;
    }

    return $tag_exists;
  }

  /**
   * Perform Guzzle call to check if contact already exists within Ontraport.
   * Returns null on error.
   *
   * @param string $email
   *
   * @return array|null
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function contactCheckGuzzleRequest( string $email ): ?array {
    $ontraport_contact_check_response = null;
    try {
      $responseFromOntraport   = $this->httpClient->get( self::ONTRAPORT_DOMAIN . self::ENDPOINT_CONTACTS, [
          self::GUZZLE_HEADERS         => $this->getOntraportHeader(),
          self::GUZZLE_QUERY => [
              self::ONTRAPORT_PARAMETER_CONDITION      => $this->jsonStringTargettingEmail( $email ),
          ],
      ] );
      $ontraport_response_body = json_decode( $responseFromOntraport->getBody(), true );
      if ( $this->ontraportCallSuccess( $ontraport_response_body ) ) {
        $ontraport_contact_check_response = $ontraport_response_body;
      }
    } catch ( \Exception $e ) {
      /**
       * If there is an exception, we catch it and do nothing. The response will
       * be null and this is to be understood as a failed call.
       */
    }

    return $ontraport_contact_check_response;
  }

  /**
   * @param string|null $firstname
   * @param string $email
   * @param string|null $source_location
   *
   * @return mixed|null
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function createContactGuzzleRequest( string $firstname = null, string $email, string $source_location = null ) {
    $ontraport_create_contact_response = null;
    try {
      $responseFromOntraport   = $this->httpClient->post( self::ONTRAPORT_DOMAIN . self::ENDPOINT_CONTACTS, [
          self::GUZZLE_HEADERS         => $this->getOntraportHeader(),
          self::GUZZLE_FORM_PARAMETERS => [
              self::FIELD_FIRST_NAME      => $firstname,
              self::FIELD_EMAIL           => $email,
              self::FIELD_SOURCE_LOCATION => $source_location,
          ],
      ] );
      $ontraport_response_body = json_decode( $responseFromOntraport->getBody(), true );
      if ( $this->ontraportCallSuccess( $ontraport_response_body ) ) {
        $ontraport_create_contact_response = $ontraport_response_body;
      }

    } catch ( \Exception $e ) {
      /**
       * If there is an exception, we catch it and do nothing. The response will
       * be null and this is to be understood as a failed call.
       */
    }

    return $ontraport_create_contact_response;
  }

  /**
   * @param $ontraport_contact_check_response
   *
   * @return array|null
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function getDataFromOntraportResponse( $ontraport_contact_check_response ): ?array {
    $contact_data = null;

    if ( ! is_null( $ontraport_contact_check_response ) ) {
      $contact_data = $this->getResponseData( $ontraport_contact_check_response );
    }

    return $contact_data;
  }

  private function getFirstContact( $contact_data ): ?OntraportContact {
    $contact = null;

    /**
     * TODO: If there is a data array and there is more than one contact, merge
     */
    /**
     * If there is a data array and there is one or more contact, get the first
     * contact and get the ID of that contact, we will be adding the tag to
     * this contact
     */
    if ( is_array( $contact_data ) && count( $contact_data ) > 0 ) {

      $first_contact_array = $contact_data[ 0 ];
      $contact = new OntraportContact();
      $contact->setData($first_contact_array);
    }

    return $contact;
  }

  private function getNewContact( $new_contact_data ): ?OntraportContact {
    $contact = null;
    if ( is_array( $new_contact_data ) ) {
      $contact = new OntraportContact();
      $contact->setData( $new_contact_data );
    }
    return $contact;
  }

  /**
   * @param string|null $firstname
   * @param string $email
   * @param string $tag
   * @param string|null $source_location
   *
   * @return \Illuminate\Http\JsonResponse|\Psr\Http\Message\ResponseInterface
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function addContactWithTagResponse( string $firstname = null, string $email, string $tag, string $source_location = null ) {
    /**
     * Set default response to client before processing logic.
     */
    $responseToClient = $this->unexpectedResponse();

    $ontraport_contact_check_response = $this->contactCheckGuzzleRequest( $email );
    $contact_check_data = $this->getDataFromOntraportResponse( $ontraport_contact_check_response );
    $contact = $this->getFirstContact( $contact_check_data );

    if ( is_null( $contact ) ) {
      $ontraport_new_contact_response = $this->createContactGuzzleRequest( $firstname, $email, $source_location );
      $new_contact_data = $this->getDataFromOntraportResponse( $ontraport_new_contact_response );
      $contact = $this->getNewContact( $new_contact_data );
    }

    if ( ! is_null( $contact ) ) {
      if ( $contact->isValid( $email ) ) {
        $responseToClient = $this->assignUserToTagResponse( $contact->id(), $tag );
      }
    } else {
      $responseToClient = $this->emailErrorResponseWithMessage( "We failed to successfully subscribe you!" );
    }

    return $responseToClient;

  }


  /**
   * @param int $contact_id
   * @param string $tag
   *
   * @return \Illuminate\Http\JsonResponse
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function assignUserToTagResponse( int $contact_id, string $tag ) {
    $guzzle_error            = false;
    $ontraport_response_body = [];
    try {

      $responseFromOntraport = $this->httpClient->put( self::ONTRAPORT_DOMAIN . self::ENDPOINT_TAGS, [
          self::GUZZLE_HEADERS => $this->getOntraportHeader(),
          self::GUZZLE_JSON    => [
              self::FIELD_OBJECT_ID => self::CONTACT_OBJECT_TYPE_ID,
              self::KEY_CONTACT_IDS => [ $contact_id ],
              self::KEY_ADD_NAMES   => [ $tag ],
          ],
      ] );

      $ontraport_response_body = json_decode( $responseFromOntraport->getBody(), true );
    } catch ( \Exception $e ) {
      $guzzle_error = true;
    }


    if ( $this->ontraportCallSuccess( $ontraport_response_body ) ) {
      $responseToClient = response()->json( [
          self::OUT_STATUS  => self::OUT_STATUS_SUCCESS,
          self::OUT_MESSAGE => "Subscribed email to the tag.",
      ], Response::HTTP_OK );
    } else if ( $guzzle_error ) {
      $responseToClient = $this->guzzleErrorResponse( 'Tagging' );
    } else {
      $responseToClient = $this->emailErrorResponseWithMessage( "Some error occurred subscribing the email to the correct list." );
    }

    return $responseToClient;
  }

  /**
   * Checks that the success code is present on the ontraport response body.
   *
   * @param $response_body
   *
   * @return bool
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function ontraportCallSuccess( $response_body ) {
    $call_success = false;

    if ( is_array( $response_body ) &&
         array_key_exists( self::FIELD_CODE, $response_body ) &&
         $response_body[ self::FIELD_CODE ] === self::ONTRAPORT_CODE_SUCCESS
    ) {
      $call_success = true;
    }

    return $call_success;
  }

  private function getOntraportHeader(): array {
    return [
        self::ONTRAPORT_HEADER_APP_ID  => $this->getAppId(),
        self::ONTRAPORT_HEADER_API_KEY => $this->getApiKey(),
    ];
  }

  /**
   * Get a key from an Ontraport object by looking at an Ontraport response.
   *
   * @param $response_body
   *
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function getKeyFromResponseBody( $response_body, string $key ) {
    $key_value = null;

    if ( $this->responseBodyHasKey( $response_body, $key ) ) {
      $response_data = $this->getResponseData( $response_body );
      $key_value = $response_data[ $key ];

    }

    return $key_value;
  }

  /**
   * Get the response data from the Ontraport response body
   *
   * @param $response_body
   *
   * @return array|null
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function getResponseData( $response_body ) :?array {
    $response_data = null;

    if ( is_array( $response_body ) &&
         array_key_exists( self::RESPONSE_DATA, $response_body )
    ) {
      $response_data = $response_body[ self::RESPONSE_DATA ];
    }

    return $response_data;
  }

  /**
   * Look at the response from Ontraport to ensure that an object has a specified
   * key existing on it.
   *
   * @param $response_body
   * @param string $key
   *
   * @return bool
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function responseBodyHasKey( $response_body, string $key ) {
    $response_body_has_key = true;

    $data = $this->getResponseData( $response_body );

    if ( is_null( $data ) ) {
      $response_body_has_key = false;
    } else if ( is_array( $data ) ) {

      if ( ! array_key_exists( $key, $data ) ) {
        $response_body_has_key = false;
      }

    } else {
      $response_body_has_key = false;
    }

    return $response_body_has_key;
  }


  private function getApiKey() {
    return $this->api_key;
  }

  private function getAppId() {
    return $this->app_id;
  }

  private function setApiKey( $api_key ) {
    $this->api_key = $api_key;
  }

  private function setAppId( $app_id ) {
    $this->app_id = $app_id;
  }

  private function ontraportCorrectlyConfigured() {
    if ( is_null( $this->api_key ) ||
         is_null( $this->app_id )
    ) {
      return false;
    }

    return true;
  }

  private function cannotAssignTagResponse() {
    return $this->emailErrorResponseWithMessage(
        'Cannot subscribe you at this time. The subscription list being requested is invalid.'
    );
  }

  /**
   *
   *
   * @param string $email
   *
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  private function jsonStringTargettingEmail( string $email ) {
    $json = [
        [
            "field" => [
                "field" => "email"
            ],
            "op"    => "=",
            "value" => [
                "value" => $email
            ]
        ]
    ];
    return json_encode( $json );
  }


}

