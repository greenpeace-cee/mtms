<?php

use Civi\Api4\Contact;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Api4\Phone;
use Civi\Test\Api3TestTrait;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test Utils
 *
 * @group headless
 */
class CRM_Mtms_UtilsTest extends TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use Api3TestTrait;

  private $bankingPresent;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    $test = \Civi\Test::headless()
      ->installMe(__DIR__);
    $this->bankingPresent = $this->callApiSuccess('Extension', 'getcount', [
      'full_name' => 'org.project60.banking',
    ]);
    if ($this->bankingPresent) {
      $test->uninstall('org.project60.banking');
      $test->install('org.project60.banking');
    }
    $test->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Test the creation of new contacts
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function testNewContact() {
    $phone = random_int(1000000000, 9000000000);
    $contact_id = CRM_Mtms_Utils::getOrCreateContact($phone);
    $this->assertNotEmpty($contact_id);
    $contact = Contact::get()
      ->addSelect('display_name')
      ->addWhere('id', '=', $contact_id)
      ->execute()
      ->first();
    $this->assertEquals($phone, $contact['display_name']);
    $phoneContact = Phone::get()
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $contact_id)
      ->execute()
      ->first();
    $this->assertEquals($phone, $phoneContact['phone']);
  }

  /**
   * Test matching of existing contacts by phone
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function testExistingContact() {
    $phone = random_int(1000000000, 9000000000);
    $contact = Contact::create()
      ->addValue('display_name', $phone)
      ->addChain('Phone', Phone::create()
        ->addValue('contact_id', '$id')
        ->addValue('phone', $phone)
      )
      ->setCheckPermissions(FALSE)
      ->execute()
      ->first();
    $contact_id = CRM_Mtms_Utils::getOrCreateContact($phone);
    $this->assertEquals($contact['id'], $contact_id);
  }

  /**
   * Test creation and matching of NBAN_MTMS bank accounts
   *
   * @throws \API_Exception
   * @throws \CiviCRM_API3_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function testBankAccount() {
    if (!$this->bankingPresent) {
      $this->markTestSkipped(
        'The org.project60.banking extension is not available.'
      );
    }

    // TODO: hack - this should be done by mtms_civicrm_enable, but probably
    //   isn't executed in the right order during test runs
    $banking_option_group = OptionGroup::get()
      ->addSelect('id')
      ->addWhere('name', '=', 'civicrm_banking.reference_types')
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
    OptionValue::save()
      ->setRecords([$banking_option_value])
      ->setCheckPermissions(FALSE)
      ->execute();
    $phone = random_int(1000000000, 9000000000);
    $contact = Contact::create()
      ->addValue('display_name', $phone)
      ->setCheckPermissions(FALSE)
      ->execute()
      ->first();
    // create a new bank account
    $new_bank_account = CRM_Mtms_Utils::getOrCreateBankAccount($phone, $contact['id']);
    $this->assertNotEmpty($new_bank_account);
    // call again with the same value
    $existing_bank_account = CRM_Mtms_Utils::getOrCreateBankAccount($phone, $contact['id']);
    $this->assertEquals(
      $new_bank_account,
      $existing_bank_account,
      'Should return existing bank account'
    );
    // create another one with a new value
    $other_bank_account = CRM_Mtms_Utils::getOrCreateBankAccount(
      random_int(1000000000, 9000000000),
      $contact['id']
    );
    $this->assertNotEquals(
      $new_bank_account,
      $other_bank_account,
      'Should create a new bank account'
    );
  }

  /**
   * Test creation and matching of billing operators
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function testBillingOperator() {
    // create option group to perform tests on
    OptionGroup::create()
      ->addValue('name', 'billing_operator')
      ->addValue('data_type', 'Integer')
      ->setCheckPermissions(FALSE)
      ->execute();
    $new_operator = CRM_Mtms_Utils::getOrCreateBillingOperator('Drei');
    $this->assertNotEmpty($new_operator);
    $existing_operator = CRM_Mtms_Utils::getOrCreateBillingOperator('Drei');
    $this->assertEquals(
      $new_operator,
      $existing_operator,
      'Should return existing operator'
    );
    $other_operator = CRM_Mtms_Utils::getOrCreateBillingOperator('A1');
    $this->assertNotEquals(
      $new_operator,
      $other_operator,
      'Should create a new operator'
    );
  }

}
