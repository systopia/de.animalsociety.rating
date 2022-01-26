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
class CRM_Rating_Algorithm extends CRM_Rating_Base
{
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
        $current_data = self::fetch_contact_data($contact_id);

        // step 2: fetch all relevant activities
        $activities = self::fetch_relevant_activities($contact_id);


        // step 3: get current date & recalculate values
        $current_date = date('y-m-d h:i:s');
        $example_calculated_score = 0.0;
        foreach ($activities as $activity) {
            $activity_type_coefficient = '';

            CRM_Rating_CustomData::labelCustomFields($activity);
            $activity_kind_coefficient = self::ACTIVITY_KIND_MAPPING[$activity[self::ACTIVITY_KIND]];
            $activity_weight_coefficient = self::ACTIVITY_WEIGHT_MAPPING[$activity[self::ACTIVITY_WEIGHT]];
            // $time_interval = activity_date_time->diff($current_date);
            // $time_coefficient =  0.75 / (((($time_interval)/365)/2.9)^4 + 1) + .25;
            $activity_score = self::ACTIVITY_SCORE_MAPPING[$activity[self::ACTIVITY_SCORE]];

            $activity_rating_weighted = $activity_kind_coefficient * $activity_weight_coefficient * $activity_score;
            self::write_rating_weighted($activity['id'], $activity_rating_weighted);
            //$example_calculated_score += $activity[self::ACTIVITY_RATING_WEIGHTED]; ?
        }

        // step 3.1: aggregate activities to contact


        // step 4: check if the values need to be updated and do so


        if ($example_calculated_score != $current_data[self::OVERALL_RATING]) {
            // update
            $contact_update = [
                'id' => $contact_id,
                self::OVERALL_RATING => $example_calculated_score,
            ];
            CRM_Rating_CustomData::resolveCustomFields($contact_update);
            civicrm_api3('Contact', 'create', $contact_update);
            Civi::log()->debug("Contact [{$contact_id}] updated.");
        } else {
            Civi::log()->debug("Contact [{$contact_id}] NOT updated (score hasn't changed).");
        }
    }




    /****************************************************
     **              HELPER FUNCTIONS                  **
     ****************************************************/

    /**
     * Fetches contact_data with needed rating parameters
     *
     * @param $contact_id
     *
     * @return array
     * @throws \CiviCRM_API3_Exception
     */
    public static function fetch_contact_data($contact_id)
    {
        $current_data_query = [
            'id' => $contact_id,
            'return' => self::getResolvedFieldList(self::RELEVANT_CONTACT_FIELDS),
        ];
        $current_data = civicrm_api3('Contact', 'getsingle', $current_data_query);
        CRM_Rating_CustomData::labelCustomFields($current_data);
        return $current_data;
    }

    /**
     * fetches the relevant activities to given contact with specified
     * return values (self::RELEVANT_ACTIVITY_FIELDS)
     *
     * @param $contact_id
     *
     * @return mixed
     * @throws \CiviCRM_API3_Exception
     */
    public static function fetch_relevant_activities($contact_id)
    {
        $activities = civicrm_api3('Activity', 'get', [
            'option.limit' => 0,
            'activity_type_id' => self::ACTIVITY_TYPE,
            'status_id' => self::ACTIVITY_STATUS,
            'target_contact_id' => $contact_id,
            'return' => self::getResolvedFieldList(self::RELEVANT_ACTIVITY_FIELDS),
        ]);
        return $activities['values'];
    }

    /**
     * Write the given ratings/weights to the activity
     *
     * @param integer $activity_id
     * @param array $rating_weighted
     *
     * @return void
     * @throws \CiviCRM_API3_Exception
     */
    public static function write_rating_weighted($activity_id, $rating_weighted)
    {
        $activity_data = [
            'id' => $activity_id,
            self::ACTIVITY_RATING_WEIGHTED => $rating_weighted,
        ];
        CRM_Rating_CustomData::resolveCustomFields($activity_data);
        $result = civicrm_api3('Activity', 'create', $activity_data);
    }

}
