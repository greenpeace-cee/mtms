<?php

use Civi\Api4\Contribution;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Test;
use Civi\Test\Api3TestTrait;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test donation processing
 *
 * @group headless
 */
class CRM_Mtms_DonationTest extends TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use Api3TestTrait;

  private $providerId;

  const SAMPLE_REQUEST = [
    'id'               => 123,
    'key'              => 'hunter2',
    'phone_number'     => '436801234567',
    'billing_operator' => 'Drei',
    'date'             => '20200826125005',
    'keyword'          => 'foobar',
    'amount'           => 25.51,
  ];

  public function setUpHeadless() {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    $this->providerId = $this->callAPISuccess('SmsProvider', 'create', [
      'name'       => 'mtms_provider',
      'title'      => 'mtms',
      // TODO: older versions of Civi expect this as int, replace with "http" once we don't care about those
      'api_type'   => CRM_Core_PseudoConstant::getKey('CRM_SMS_DAO_Provider', 'api_type', 'http'),
      'api_params' => 'key=hunter2',
    ])['id'];
    OptionGroup::get()
      ->addSelect('id')
      ->addWhere('name', '=', 'payment_instrument')
      ->addChain('OptionValue', OptionValue::create()
        ->addValue('option_group_id', '$id')
        ->addValue('name', 'SMS')
        ->addValue('label', 'SMS')
      )
      ->setCheckPermissions(FALSE)
      ->execute();
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Test that an invalid key fails
   *
   * @throws \Exception
   */
  public function testAuthenticationFailure() {
    $this->expectExceptionMessage('Wrong authentication key');
    new CRM_Mtms_Donation(array_merge(
      self::SAMPLE_REQUEST,
      [
        'provider_id' => $this->providerId,
        'key' => 'badpassword',
      ]
    ));
  }

  /**
   * Test that a donation is saved as a contribution
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function testDonationSaved() {
    $donation = new CRM_Mtms_Donation(array_merge(
      self::SAMPLE_REQUEST,
      [
        'provider_id' => $this->providerId,
      ]
    ));
    $donation->save();
    $contribution = Contribution::get()
      ->addSelect('total_amount', 'receive_date', 'financial_type_id')
      ->addWhere('trxn_id', '=', 'MTMS-' . self::SAMPLE_REQUEST['id'])
      ->execute()
      ->first();
    $this->assertEquals(25.51, $contribution['total_amount']);
    $this->assertEquals('2020-08-26 12:50:05', $contribution['receive_date']);
    $this->assertEquals(
      \CRM_Core_PseudoConstant::getKey(
        'CRM_Contribute_BAO_Contribution',
        'financial_type_id',
        'Donation'
      ),
      $contribution['financial_type_id']
    );
  }

}
