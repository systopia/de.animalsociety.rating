<?php
/*-------------------------------------------------------+
| AnimalSociety Rating Extension                         |
| Copyright (C) 2022 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use Civi\Test\Api3TestTrait;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

use CRM_Rating_ExtensionUtil as E;

/**
 * This is the test base class with lots of utility functions
 *
 * @group headless
 */
abstract class CRM_Rating_TestBase implements HeadlessInterface, HookInterface,
                                                                            TransactionalInterface
{
    use Api3TestTrait {
        callAPISuccess as protected traitCallAPISuccess;
    }

    /** @var CRM_Core_Transaction current transaction */
    protected $transaction = null;

    public function setUpHeadless()
    {
        // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
        // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
        return \Civi\Test::headless()
//            ->install(['de.systopia.xcm'])
            ->installMe(__DIR__)
            ->apply();
    }

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * Generate a random string, and make sure we don't collide
     *
     * @param int $length
     *   length of the string
     *
     * @return string
     *   random string
     */
    public function randomString($length = 32)
    {
        static $generated_strings = [];
        $candidate = substr(sha1(random_bytes(32)), 0, $length);
        if (isset($generated_strings[$candidate])) {
            // simply try again (recursively). Is this dangerous? Yes, but veeeery unlikely... :)
            return $this->randomString($length);
        }
        // mark as 'generated':
        $generated_strings[$candidate] = 1;
        return $candidate;
    }

    /**
     * Create a new contact
     *
     * @param array $contact_details
     *   overrides the default values
     *
     * @return array
     *  contact data
     */
    public function createContact($contact_details = [])
    {
        // prepare contact data
        $contact_data = [
            'contact_type' => 'Individual',
            'first_name'   => $this->randomString(10),
            'last_name'    => $this->randomString(10),
            'email'        => $this->randomString(10) . '@' . $this->randomString(10) . '.org',
            'prefix_id'    => 1,
        ];
        foreach ($contact_details as $key => $value) {
            $contact_data[$key] = $value;
        }
        CRM_Rating_CustomData::resolveCustomFields($contact_data);

        // create contact
        $result = $this->traitCallAPISuccess('Contact', 'create', $contact_data);
        $contact = $this->traitCallAPISuccess('Contact', 'getsingle', ['id' => $result['id']]);
        CRM_Rating_CustomData::labelCustomFields($contact);
        return $contact;
    }

}