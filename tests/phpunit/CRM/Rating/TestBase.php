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

    /**
     * Create a political activity for the given contacts
     *
     * @param integer $contact_id
     *   Contact ID for the activity
     *
     * @param array $attributes
     *   list of attributes for the activity, to overwrite the generated values. These are:
     *      rating_title, rating_kind, rating_category, activity_score, activity_type_id, activity_status_id
     * @return void
     */
    public function createPoliticalActivity($contact_id, $attributes = [])
    {
        // set mandatory attributes
        $attributes['activity_type_id'] = CRM_Rating_Base::getRatingActivityTypeID();
        $attributes['subject'] = $this->randomString();
        $attributes['target_id'] = $contact_id;
        $attributes['activity_status_id'] = CRM_Rating_Base::getRatingActivityStatusPublished();
        $attributes['activity_date_time'] = date('YmdHis', strtotime($attributes['activity_date_time']));

        // fill missing attributes
        if (empty($attributes[CRM_Rating_Base::ACTIVITY_TITLE])) {
            $attributes[CRM_Rating_Base::ACTIVITY_TITLE] = $this->randomString();
        }
        if (empty($attributes[CRM_Rating_Base::ACTIVITY_KIND])) {
            $attributes[CRM_Rating_Base::ACTIVITY_KIND] = $this->getRandomOptionValue('activity_kind');
        }
        if (empty($attributes[CRM_Rating_Base::ACTIVITY_CATEGORY])) {
            $attributes[CRM_Rating_Base::ACTIVITY_CATEGORY] = $this->getRandomOptionValue('activity_category');
        }
        if (empty($attributes[CRM_Rating_Base::ACTIVITY_SCORE])) {
            $attributes[CRM_Rating_Base::ACTIVITY_SCORE] = $this->getRandomOptionValue('activity_score');
        }

        $result = $this->traitCallAPISuccess('Activity', 'create', $attributes);
        return $result;
    }

    /**
     * Get a random (active) option from the given option group
     *
     * @param string $option_group_name
     *   the (internal) name of the option group
     *
     * @param boolean $reload
     *   since the option groups are being cached, the flag can be set to force a reload
     *
     * @return string
     *   a value from the option group
     */
    public function getRandomOptionValue($option_group_name, $reload = false)
    {
        static $option_cache = [];
        if ($reload || !isset($option_cache[$option_group_name])) {
            $data = civicrm_api3('OptionValue', 'get', [
                'option_group_name' => $option_group_name,
                'option.limit' => 0,
                'return' => 'value,label',
                'is_active' => 1
            ]);
            foreach ($data['values'] as $entry) {
                $option_cache[$option_group_name][$entry['value']] = $entry['label'];
            }
        }
        return array_rand($option_cache[$option_group_name]);
    }

    /**
     * Get the overall rating for the given contact
     *
     * @param integer $contact_id
     *    Contact ID
     *
     * @param string $full_field_name
     *    field id, specified by group_name.field_name
     *
     * @return float|null
     *    current rating
     */
    public function getRating($contact_id, $full_field_name)
    {
        $field_keys = explode('.', $full_field_name);
        $field = CRM_Rating_CustomData::getCustomField($field_keys[0], $field_keys[1]);
        $this->assertNotEmpty($field, "Field not found.");
        $field_id = 'custom_' . $field['id'];
        $result = $this->traitCallAPISuccess('Contact', 'getvalue', [
            'id' => $contact_id,
            'return' => 'custom_' . $field_id
        ]);
        return (float) $result;
    }

    /**
     * Get the overall rating for the given contact
     *
     * @param integer $contact_id
     *    Contact ID
     *
     * @return float|null
     *    current rating
     */
    public function getOverallContactRating($contact_id)
    {
        return $this->getRating($contact_id, CRM_Rating_Base::OVERALL_RATING);
    }
}