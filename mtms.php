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
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function mtms_civicrm_xmlMenu(&$files) {
  _mtms_civix_civicrm_xmlMenu($files);
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
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function mtms_civicrm_postInstall() {
  _mtms_civix_civicrm_postInstall();
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
  _mtms_civix_civicrm_uninstall();
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
  _mtms_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function mtms_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _mtms_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function mtms_civicrm_managed(&$entities) {
  _mtms_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function mtms_civicrm_caseTypes(&$caseTypes) {
  _mtms_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function mtms_civicrm_angularModules(&$angularModules) {
  _mtms_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function mtms_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _mtms_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function mtms_civicrm_entityTypes(&$entityTypes) {
  _mtms_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_thems().
 */
function mtms_civicrm_themes(&$themes) {
  _mtms_civix_civicrm_themes($themes);
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
