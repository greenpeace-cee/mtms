<?php

use Civi\Api4\Contact;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Api4\Phone;

class CRM_Mtms_Utils {

  /**
   * Get an existing contact based on phone number or create one
   *
   * @param $phone
   *
   * @return mixed ID of matched or new contact
   */
  public static function getOrCreateContact($phone) {
    $contact = Phone::get()
      ->addSelect('contact_id')
      ->addWhere('phone', '=', $phone)
      ->addWhere('contact.is_deleted', '=', FALSE)
      ->addOrderBy('contact_id', 'ASC')
      ->setCheckPermissions(FALSE)
      ->execute()
      ->first();

    if (!empty($contact)) {
      // matched to existing contact
      return $contact['contact_id'];
    }
    else {
      // create a new contact
      $contact = Contact::create()
        ->addValue('display_name', $phone)
        ->addChain('Phone', Phone::create()
          ->addValue('contact_id', '$id')
          ->addValue('phone', $phone)
        )
        ->setCheckPermissions(FALSE)
        ->execute()
        ->first();
      return $contact['id'];
    }
  }

  /**
   * Normalize phone number using com.cividesk.normalize (if installed)
   *
   * @param $phone
   * @param $type
   *
   * @return string
   */
  public static function normalizePhone($phone, $type) {
    if (class_exists('CRM_Utils_Normalize')) {
      $tempPhone = $phone;
      // international numbers need a + prefix to be recognized by normalize
      if ($type == 'international') {
        $tempPhone = '+' . $tempPhone;
      }
      $normalizedPhone = ['phone' => $tempPhone];
      $normalize = CRM_Utils_Normalize::singleton();
      if ($normalize->normalize_phone($normalizedPhone)) {
        $phone = $normalizedPhone['phone'];
      }
    }
    return $phone;
  }

  public static function getOrCreateBillingOperator($operator) {
    $optionGroup = OptionGroup::get()
      ->addSelect('id')
      ->addWhere('name', '=', 'billing_operator')
      ->setCheckPermissions(FALSE)
      ->execute()
      ->first();

    if (empty($optionGroup['id'])) {
      return NULL;
    }

    $optionValue = OptionValue::get()
      ->addSelect('value')
      ->addWhere('option_group_id', '=', $optionGroup['id'])
      ->addWhere('name', '=', $operator)
      ->setCheckPermissions(FALSE)
      ->execute()
      ->first();

    if (empty($optionValue['value'])) {
      $optionValue = OptionValue::create()
        ->addValue('option_group_id', $optionGroup['id'])
        ->addValue('name', $operator)
        ->addValue('label', $operator)
        ->setCheckPermissions(FALSE)
        ->execute()
        ->first();
    }

    return $optionValue['value'];
  }

  /**
   * Get or create a matching NBAN_MTMS bank account for $phone
   *
   * @todo BankingAccount/BankingAccountReference API is a mess. make this less
   *   insane once it's improved
   *
   * @param $phone
   * @param $contact_id
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function getOrCreateBankAccount($phone, $contact_id) {
    $reference_type_value = civicrm_api3('OptionValue', 'getsingle', [
      'value' => 'NBAN_MTMS',
      'option_group_id' => 'civicrm_banking.reference_types',
      'is_active' => 1,
    ]);

    // find existing references
    $existing_references = civicrm_api3('BankingAccountReference', 'get', [
      'reference' => $phone,
      'reference_type_id' => $reference_type_value['id'],
      'option.limit' => 0,
    ]);

    // get the accounts for this
    $bank_account_ids = [];
    foreach ($existing_references['values'] as $account_reference) {
      $bank_account_ids[] = $account_reference['ba_id'];
    }
    if (!empty($bank_account_ids)) {
      $contact_bank_accounts = civicrm_api3('BankingAccount', 'get', [
        'id' => ['IN' => $bank_account_ids],
        'contact_id' => $contact_id,
        'option.limit' => 1,
      ]);
      if ($contact_bank_accounts['count']) {
        // bank account already exists with the contact
        $bank_account = reset($contact_bank_accounts['values']);
        return $bank_account['id'];
      }
    }

    // if we get here, that means that there is no such bank account. create it
    $bank_account = civicrm_api3('BankingAccount', 'create', [
      'contact_id' => $contact_id,
      'description' => 'MTMS',
    ]);

    civicrm_api3('BankingAccountReference', 'create', [
      'reference' => $phone,
      'reference_type_id' => $reference_type_value['id'],
      'ba_id' => $bank_account['id'],
    ]);
    return $bank_account['id'];
  }

}
