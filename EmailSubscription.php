<?php

namespace App\Classes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EmailSubscription {

  // Standard json key for the client that holds messages from this class
  const OUT_EMAIL_SUBSCRIPTION_MESSAGE = 'email_subscription_message';

  /**
   * Response to return when Email Subscription Class is not configured. The
   * check for whether the subscription class is configured must be implemented
   * for each individual class as required settings vary.
   *
   * @return JsonResponse
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  protected function unconfiguredResponse(){
    return $this->emailErrorResponseWithMessage( 'Oh no! Looks like we haven\'t correctly configured our subscription service.' );
  }

  /**
   * Send this response as a default. Should never actually make it to the client.
   *
   * @return JsonResponse
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  protected function unexpectedResponse() {
    return response()->json( [
        self::OUT_EMAIL_SUBSCRIPTION_MESSAGE => [ 'We made a mistake trying to subscribe you to our list, please let us know at '.env( 'INBOX_FOR_CONTACT_US', '' ) ],
    ], Response::HTTP_INTERNAL_SERVER_ERROR );
  }

  /**
   * Send this response when a guzzle call throws an exception.
   *
   * @param string $call_identifier string to help tell which guzzle call failed
   *
   * @return JsonResponse
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  protected function guzzleErrorResponse( string $call_identifier ) {
    return response()->json( [
        self::OUT_EMAIL_SUBSCRIPTION_MESSAGE => [ $call_identifier .': Error contacting our subscription service, please try again, if this persists, please let us know at '.env( 'INBOX_FOR_CONTACT_US', '' ) ],
    ], Response::HTTP_INTERNAL_SERVER_ERROR );
  }

  /**
   * Send a response with a custom message to inform the client about the
   * error that occured.
   *
   * @param string $message
   * @param int $error_code
   *
   * @return JsonResponse
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  protected function emailErrorResponseWithMessage( string $message, int $error_code = Response::HTTP_INTERNAL_SERVER_ERROR ) {
    return response()->json( [
        self::OUT_EMAIL_SUBSCRIPTION_MESSAGE => [ $message . ' ... Please let us know at '.env( 'INBOX_FOR_CONTACT_US', '' ) ],
    ], $error_code );
  }

}