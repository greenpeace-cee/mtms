<?php

use Civi\Api4\Contribution;
use Civi\Api4\Generic\DAOCreateAction;

class CRM_Mtms_Donation {

  /**
   * @var array
   */
  private $donation;

  /**
   * @var array
   */
  private $providerInfo;

  private $fields = [
    'provider_id'      => 'Integer',
    'key'              => 'String',
    'id'               => 'Integer',
    'phone_number'     => 'String',
    'billing_operator' => 'String',
    'date'             => 'Date',
    'keyword'          => 'String',
    'amount'           => 'Money',
  ];

  /**
   * CRM_Mtms_Donation constructor.
   *
   * @param array $donation
   *
   * @throws \Exception
   */
  public function __construct(array $donation) {
    foreach ($this->fields as $name => $type) {
      if ($name != 'keyword' && empty($donation[$name])) {
        throw new \Exception("Parameter '{$name}' must not be empty.");
      }
      $this->donation[$name] = \CRM_Utils_Type::validate($donation[$name], $type);
    }

    $this->providerInfo = CRM_SMS_BAO_Provider::getProviderInfo(
      $this->donation['provider_id']
    );

    if (empty($this->providerInfo)) {
      throw new Exception("Unknown provider '{$this->donation['provider_id']}'");
    }

    if (!empty($this->providerInfo['api_params']['key'])) {
      if (!hash_equals($this->providerInfo['api_params']['key'], $this->donation['key'])) {
        throw new \Exception('Wrong authentication key');
      }
    }

    $this->donation['phone_number'] = CRM_Mtms_Utils::normalizePhone(
      $this->donation['phone_number'],
      'international'
    );

    $this->donation['billing_operator'] = CRM_Mtms_Utils::getOrCreateBillingOperator(
      $this->donation['billing_operator']
    );
  }

  /**
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function save() {
    $contribution = Contribution::create()
      ->setCheckPermissions(FALSE)
      ->addValue(
        'contact_id',
        CRM_Mtms_Utils::getOrCreateContact($this->donation['phone_number'])
      )
      ->addValue(
        'financial_type_id',
        \CRM_Core_PseudoConstant::getKey(
          'CRM_Contribute_BAO_Contribution',
          'financial_type_id',
          'Donation'
        )
      )
      ->addValue(
        'payment_instrument_id',
        \CRM_Core_PseudoConstant::getKey(
          'CRM_Contribute_BAO_Contribution',
          'payment_instrument_id',
          'SMS'
        )
      )
      ->addValue('total_amount', $this->donation['amount'])
      ->addValue('trxn_id', 'MTMS-' . $this->donation['id'])
      ->addValue('receive_date', $this->donation['date'])
      ->addValue(
        'contribution_information.billing_operator',
        $this->donation['billing_operator']
      )
      ->addValue(
        'contribution_information.keyword',
        $this->donation['keyword']
      );

    // set contribution campaign based on settings
    $campaign = $this->providerInfo['api_params']['donation_campaign'] ??
      \Civi::settings()->get('mtms_donation_campaign');
    if (!empty($campaign)) {
      $contribution->addValue('campaign_id', $campaign);
    }

    // TODO: APIv4 seems to have trouble setting contribution_information.from_ba/to_ba
    //   once that's resolved, enable processBanking() and remove postProcessBanking()
    // $this->processBanking($contribution);

    $contribution = $contribution->execute()->first();

    // TODO: remove once processBanking() works
    $this->postProcessBanking($contribution);
    return $contribution['id'];
  }

  /**
   * Process CiviBanking-related fields (if available)
   *
   * @param \Civi\Api4\Generic\DAOCreateAction $apiContribution
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function processBanking(DAOCreateAction $apiContribution) {
    $bankingAvailable = civicrm_api3('Extension', 'getcount', [
      'full_name' => 'de.systopia.segmentation',
      'status'    => 'installed',
    ]);
    if ($bankingAvailable !== 1) {
      // CiviBanking not available, nothing to do
      return;
    }

    // set destination bank account based on setting
    $destination_bank_account =
      $this->providerInfo['api_params']['donation_bank_account'] ??
      \Civi::settings()->get('mtms_donation_bank_account');
    if (!empty($destination_bank_account)) {
      $apiContribution->addValue(
        'contribution_information.to_ba',
        $destination_bank_account
      );
    }

    // set source bank account to phone number
    $apiContribution->addValue(
      'contribution_information.from_ba',
      CRM_Mtms_Utils::getOrCreateBankAccount(
        $this->donation['phone_number'],
        $apiContribution->getValue('contact_id')
      )
    );

  }

  /**
   * Process CiviBanking-related fields (if available)
   *
   * @todo remove once processBanking() works
   *
   * @param array $contribution
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function postProcessBanking(array $contribution) {
    $bankingAvailable = civicrm_api3('Extension', 'getcount', [
      'full_name' => 'de.systopia.segmentation',
      'status'    => 'installed',
    ]);
    if ($bankingAvailable !== 1) {
      // CiviBanking not available, nothing to do
      return;
    }

    $to_ba_field = 'custom_' . civicrm_api3('CustomField', 'getvalue', [
      'name'            => 'to_ba',
      'custom_group_id' => 'contribution_information',
      'return'          => 'id',
    ]);

    $from_ba_field = 'custom_' . civicrm_api3('CustomField', 'getvalue', [
      'name'            => 'from_ba',
      'custom_group_id' => 'contribution_information',
      'return'          => 'id',
    ]);

    $data = [
      'id' => $contribution['id'],
    ];

    // set destination bank account based on setting
    $destination_bank_account =
      $this->providerInfo['api_params']['donation_bank_account'] ??
      \Civi::settings()->get('mtms_donation_bank_account');
    if (!empty($destination_bank_account)) {
      $data[$to_ba_field] = $destination_bank_account;
    }

    // set source bank account to phone number
    $data[$from_ba_field] = CRM_Mtms_Utils::getOrCreateBankAccount(
      $this->donation['phone_number'],
      $contribution['contact_id']
    );
    civicrm_api3('Contribution', 'create', $data);
  }

}
