<?php

use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;

require_once 'mtms.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function mtms_civicrm_config(&$config) {
  _mtms_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function mtms_civicrm_install() {
  OptionGroup::get()
    ->addSelect('id')
    ->addWhere('name', '=', 'sms_provider_name')
    ->addChain('OptionValue', OptionValue::create()
      ->addValue('option_group_id', '$id')
      ->addValue('name', 'mtms')
      ->addValue('label', 'mtms')
      ->addValue('value', 'mtms.provider')
      ->addValue('is_active', TRUE)
    )
    ->setCheckPermissions(FALSE)
    ->execute();
  _mtms_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function mtms_civicrm_uninstall() {
  OptionGroup::get()
    ->addSelect('id')
    ->addWhere('name', '=', 'sms_provider_name')
    ->addChain('OptionValue', OptionValue::delete()
      ->addWhere('option_group_id', '=', '$id')
      ->addWhere('name', '=', 'mtms')
    )
    ->setCheckPermissions(FALSE)
    ->execute();
  // TODO: convert to APIv4 once SmsProvider is available
  civicrm_api3('SmsProvider', 'get', [
    'name'                   => 'mtms',
    'api.SmsProvider.delete' => [],
  ]);
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function mtms_civicrm_enable() {
  // create or enable an account reference type in CiviBanking
  $banking_option_group = OptionGroup::get()
    ->addSelect('id')
    ->addWhere('name', '=', 'civicrm_banking.reference_types')
    ->setCheckPermissions(FALSE)
    ->execute()
    ->first();

  if (!empty($banking_option_group['id'])) {
    // CiviBanking is in use
    $banking_option_value_id = OptionValue::get()
      ->addSelect('id')
      ->addWhere('option_group_id', '=', $banking_option_group['id'])
      ->addWhere('name', '=', 'NBAN_MTMS')
      ->setCheckPermissions(FALSE)
      ->execute()
      ->first();
    $banking_option_value = [
      'option_group_id' => $banking_option_group['id'],
      'label'           => 'mtms',
      'description'     => 'mtms payment phone number',
      'name'            => 'NBAN_MTMS',
      'value'           => 'NBAN_MTMS',
      'filter'          => '0',
      'is_reserved'     => '0',
      'is_active'       => '1',
    ];
    if (!empty($banking_option_value_id['id'])) {
      // update existing OptionValue
      $banking_option_value['id'] = $banking_option_value_id['id'];
    }
    OptionValue::save()
      ->setRecords([$banking_option_value])
      ->setCheckPermissions(FALSE)
      ->execute();
  }

  OptionGroup::get()
    ->addSelect('id')
    ->addWhere('name', '=', 'sms_provider_name')
    ->addChain('OptionValue', OptionValue::update()
      ->addWhere('option_group_id', '=', '$id')
      ->addWhere('name', '=', 'websms')
      ->addValue('is_active', TRUE)
    )
    ->setCheckPermissions(FALSE)
    ->execute();
  // TODO: convert to APIv4 once SmsProvider is available
  civicrm_api3('SmsProvider', 'get', [
    'name'                   => 'websms.provider',
    'is_active'              => 0,
    'api.SmsProvider.create' => ['is_active' => 1],
  ]);

  _mtms_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function mtms_civicrm_disable() {
  // disable account reference type in CiviBanking
  $banking_option_group = OptionGroup::get()
    ->addSelect('id')
    ->addWhere('name', '=', 'civicrm_banking.reference_types')
    ->setCheckPermissions(FALSE)
    ->execute()
    ->first();
  if (!empty($banking_option_group['id'])) {
    OptionValue::update()
      ->addWhere('option_group_id', '=', $banking_option_group['id'])
      ->addWhere('name', '=', 'NBAN_MTMS')
      ->addValue('is_active', TRUE)
      ->setCheckPermissions(FALSE)
      ->execute();
  }

  OptionGroup::get()
    ->addSelect('id')
    ->addWhere('name', '=', 'sms_provider_name')
    ->addChain('OptionValue', OptionValue::update()
      ->addWhere('option_group_id', '=', '$id')
      ->addWhere('name', '=', 'mtms')
      ->addValue('is_active', FALSE)
    )
    ->setCheckPermissions(FALSE)
    ->execute();
  // TODO: convert to APIv4 once SmsProvider is available
  civicrm_api3('SmsProvider', 'get', [
    'name'                   => 'mtms.provider',
    'is_active'              => 1,
    'api.SmsProvider.create' => ['is_active' => 0],
  ]);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function mtms_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
//function mtms_civicrm_navigationMenu(&$menu) {
//  _mtms_civix_insert_navigation_menu($menu, 'Mailings', array(
//    'label' => E::ts('New subliminal message'),
//    'name' => 'mailing_subliminal_message',
//    'url' => 'civicrm/mailing/subliminal',
//    'permission' => 'access CiviMail',
//    'operator' => 'OR',
//    'separator' => 0,
//  ));
//  _mtms_civix_navigationMenu($menu);
//}
