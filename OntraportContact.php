<?php

namespace App\Classes;

class OntraportContact {

  const ONTRAPORT_CONTACT_ID_KEY = 'id';
  const ONTRAPORT_CONTACT_FIRSTNAME_KEY = 'firstname';
  const ONTRAPORT_CONTACT_EMAIL_KEY = 'email';

  private $contact_array;

  public function __construct() {
    return $this;
  }

  public function setData( array $contact_array ) {
    $this->contact_array = $contact_array;
  }

  public function firstname(): ?string {
    $firstname = null;

    if ( is_array( $this->contact_array ) ) {
      if ( array_key_exists( self::ONTRAPORT_CONTACT_FIRSTNAME_KEY, $this->contact_array ) ) {
        $firstname = $this->contact_array[ self::ONTRAPORT_CONTACT_FIRSTNAME_KEY ];
      }
    }

    return $firstname;
  }

  public function email(): ?string {
    $email = null;

    if ( is_array( $this->contact_array ) ) {
      if ( array_key_exists( self::ONTRAPORT_CONTACT_EMAIL_KEY, $this->contact_array ) ) {
        $email = $this->contact_array[ self::ONTRAPORT_CONTACT_EMAIL_KEY ];
      }
    }

    return $email;
  }

  /**
   * Ontraport object ID types can have a value of 0, but actual object IDs are
   * positive integers greater than 0. We assume the $id passed is not a floating
   * point.
   *
   * @return int|null
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  public function id(): ?int {
    $contact_id = null;

    if ( is_array( $this->contact_array ) ) {
      if ( array_key_exists( self::ONTRAPORT_CONTACT_ID_KEY, $this->contact_array ) ) {
        $contact_id_value = $this->contact_array[ self::ONTRAPORT_CONTACT_ID_KEY ];
        if ( $this->idIsValid( $contact_id_value ) ) {
          $contact_id = intval( $contact_id_value );
        }
      }
    }

    return $contact_id;
  }

  /**
   * If the contact here has the $firstname and $email passed within it, it is
   * considered valid.
   *
   * @param string $firstname
   * @param string $email
   *
   * @return bool
   * @author Andrei Borissenko <andrei.borissenko@gmail.com>
   */
  public function isValid( string $email ) {
    $contact_is_valid = false;
    if ( $this->email() === $email ) {
      $contact_is_valid = true;
    }
    return $contact_is_valid;
  }

  private function idIsValid( $id ) {
    $id_is_valid = false;

    $id_intval = intval( $id );

    if ( $id_intval > 0 ) {
      $id_is_valid = true;
    }

    return $id_is_valid;
  }

}