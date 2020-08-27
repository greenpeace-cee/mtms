<?php

return [
  'mtms_donation_bank_account' => [
    'name'        => 'mtms_donation_bank_account',
    'type'        => 'Integer',
    'html_type'   => 'text',
    'default'     => NULL,
    'add'         => '1.0',
    'title'       => ts('CiviBanking Bank Account ID'),
    'is_domain'   => 1,
    'is_contact'  => 0,
    'description' => ts('Receiving CiviBanking Bank Account ID for inbound donations.'),
  ],
  'mtms_donation_campaign' => [
    'name'        => 'mtms_donation_campaign',
    'type'        => 'Integer',
    'html_type'   => 'text',
    'default'     => NULL,
    'add'         => '1.0',
    'title'       => ts('Campaign for mtms donations'),
    'is_domain'   => 1,
    'is_contact'  => 0,
    'description' => ts('Campaign for mtms donations.'),
  ],
];
