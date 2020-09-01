# mtms

[![CircleCI](https://circleci.com/gh/greenpeace-cee/mtms.svg?style=svg)](https://circleci.com/gh/greenpeace-cee/mtms)

Send and receive text messages and SMS donations in CiviCRM using [mtms](https://mtms.at/).

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.2+
* CiviCRM 5.24+

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl mtms@https://github.com/greenpeace-cee/mtms/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/greenpeace-cee/mtms.git
cv en mtms
```

## Configuration

To configure mtms in CiviCRM, go to Administer > System Settings > SMS Providers,
click on "Add SMS Provider" and enter the following values:

* **Name:** `mtms`
* **Title:** `mtms`
* **Username:** TODO
* **Password:** TODO
* **API Type:** `http`
* **API Url:** TODO
* **API Parameters:** Multiple optional line-separated key-value items are supported:
  * `key={random_key}`
  * `donation_bank_account={bank_account_id}` *(Organization bank account for
    inbound donations when used with CiviBanking. Stored in a contribution
    custom field called `contribution_information.to_ba`.)*
  * `donation_campaign={campaign_id}` *(Campaign used for inbound donations)*

`{random_key}` is used as a simple authentication key for inbound messages
and donations to prevent forgeries. You may skip this step if you're not
processing inbound messages or donations in CiviCRM or want to use a different
authentication mechanism (e.g. IP whitelisting).

You can generate a random value like this (or use another tool of your choice):

    openssl rand -hex 32

To accept incoming donations in CiviCRM, use this URL:

`https://civicrm.example.org/civicrm/mtms/donation`

This endpoint accepts the following **POST** parameters:

* `provider_id`: ID of the SMS provider in CiviCRM
* `key`: The random key configured in the SMS Provider's API Parameters (optional)
* `id`: mtms-internal ID of the donation. Used as `trxn_id`, with a `MTMS-` prefix
* `phone_number`: Phone number that made the donation
* `billing_operator`: Billing operator (mobile network) that processed the donation
* `date`: Donation date (Format: `YmdHis`, e.g. `20200901130000`)
* `keyword`: Keyword with which the donation was made, stored in a custom
  contribution field called `contribution_information.keyword` (optional)
* `amount`: Donation amount (Format: `1234.56`)

## Known Issues

* Inbound and outbound text messages not yet implemented
