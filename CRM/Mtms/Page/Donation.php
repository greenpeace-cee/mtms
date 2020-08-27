<?php

class CRM_Mtms_Page_Donation extends CRM_Core_Page {

  public function run() {
    try {
      if (defined('CIVICRM_MTMS_LOGGING') && CIVICRM_MTMS_LOGGING) {
        Civi::log()->debug(
          '[mtms] Received inbound donation request',
          [
            'GET'  => $_GET,
            'POST' => $_POST,
          ]
        );
      }
      $donation = new CRM_Mtms_Donation($_POST);
      $contribution_id = $donation->save();
      echo '[accepted]';
      Civi::log()->info('[mtms] Saved inbound donation with contribution_id=' . $contribution_id);
      CRM_Utils_System::civiExit();
    }
    catch (Exception $e) {
      echo '[error: ' . $e->getMessage() . ']';
      Civi::log()->critical(
        '[mtms] Inbound donation request failed! Error: ' . $e->getMessage(),
        [
          'GET'  => $_GET,
          'POST' => $_POST,
        ]
      );
      throw $e;
    }
  }

}
