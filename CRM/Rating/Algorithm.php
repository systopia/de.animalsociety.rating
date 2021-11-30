<?php
/*-------------------------------------------------------+
| AnimalSociety Rating Extension                         |
| Copyright (C) 2021 SYSTOPIA                            |
| Author: A. Bugey (bugey@systopia.de)                   |
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


use CRM_Rating_ExtensionUtil as E;

/**
 * This class implements the algorithms to calculate the aggregated rating values
 */
class CRM_Rating_Algorithm
{
    /** @var string custom field key for the overall score */
    const ACTIVITY_TYPE = 'political_activity';

    /** @var string custom field key for the overall score */
    const CONTACT_SCORE = 'contact_results.overall_score';

    /** @var array the list of fields that are relevant for the calculations */
    const RELEVANT_CONTACT_FIELDS = [
        self::CONTACT_SCORE,
    ];

    /** @var string custom field key for the overall score */
    const ACTIVITY_SCORE = 'political_activity_additional_fields.activity_score';

    /** @var array the list of fields that are relevant for the calculations */
    const RELEVANT_ACTIVITY_FIELDS = [
        self::ACTIVITY_SCORE,
        'activity_date_time',
        'status_id'
    ];

    /**
     * Update an individual contact based on it's activities
     *
     * @param integer $contact_id
     *   ID of the contact to be updated
     *
     * @throws \CiviCRM_API3_Exception if something's wrong
     */
    public static function updateContact(int $contact_id)
    {
        // step 1: fetch the contact data
        $current_data_query = [
            'id' => $contact_id,
            'return' => self::getResolvedFieldList(self::RELEVANT_CONTACT_FIELDS)
        ];
        $current_data = civicrm_api3('Contact', 'getsingle', $current_data_query);
        CRM_Rating_CustomData::labelCustomFields($current_data);

        // step 2: fetch all relevant activities
        $activities = civicrm_api3('Activity', 'get', [
            'option.limit' => 0,
            'activity_type_id' => self::ACTIVITY_TYPE,
            'target_contact_id' => $contact_id,
            'return' => self::getResolvedFieldList(self::RELEVANT_ACTIVITY_FIELDS)
        ])['values'];

        // step 4: recalculate values
        $example_calculated_score = 0.0;
        foreach ($activities as $activity) {
            CRM_Rating_CustomData::labelCustomFields($activity);
            $example_calculated_score += $activity[self::ACTIVITY_SCORE];
        }

        // step 5: check if the values need to be updated and do so
        if ($example_calculated_score != $current_data[self::CONTACT_SCORE]) {
            // update
            $contact_update = [
                'id' => $contact_id,
                self::CONTACT_SCORE => $example_calculated_score
            ];
            CRM_Rating_CustomData::resolveCustomFields($contact_update);
            civicrm_api3('Contact', 'create', $contact_update);
            Civi::log()->debug("Contact [{$contact_id}] updated.");
        } else {
            Civi::log()->debug("Contact [{$contact_id}] NOT updated (score hasn't changed).");
        }
    }

    /**
     * Translates the field list in the 'group_name.field_name' notation to the custom_xx notation
     *
     * @param array $field_list
     *    list of fields in the 'group_name.field_name' notation
     *
     * @return array
     *    list of fields in the 'custom_xx' notation
     */
    static function getResolvedFieldList($field_list)
    {
        $field_names_as_keys = array_flip($field_list);
        CRM_Rating_CustomData::resolveCustomFields($field_names_as_keys);
        return array_flip($field_names_as_keys);
    }
}
