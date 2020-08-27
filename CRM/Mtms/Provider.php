<?php

class CRM_Mtms_Provider extends CRM_SMS_Provider {

  protected $providerInfo = [];

  static private $_singleton;

  public function __construct(array $provider) {
    // TODO: implement
  }

  public static function &singleton($providerParams = [], $force = FALSE) {
    if (!isset(self::$_singleton)) {
      self::$_singleton = new self($providerParams);
    }
    return self::$_singleton;
  }

  public function send($recipients, $header, $message, $dncID = NULL) {
    // TODO: implement
  }

  public function inbound() {
    // TODO: implement
  }

}
