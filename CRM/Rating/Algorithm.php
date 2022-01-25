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
    /** @var string custom field key for the activity type */
    const ACTIVITY_TYPE = 'political_activity';

    /** @var string custom field key for the activity status */
    const ACTIVITY_STATUS = 'published';

    /**
     * Contact fields of contact_results custom group. Fields to be displayed
     */

    /** @var string custom field key for the overall score */
    const OVERALL_RATING = 'contact_results.overall_rating';

    /** @var string custom field key for the importance of a contact */
    const CONTACT_IMPORTANCE = 'contact_results.contact_importance';

    /** @var string custom field key for the livestock rating weighted */
    const LIVESTOCK_RATING = 'contact_results.livestock_rating';

    /** @var string custom field key for the aquaculture rating weighted */
    const AQUACULTURE_RATING = 'contact_results.aquaculture_rating';

    /** @var string custom field key for the animal testing rating weighted */
    const ANIMAL_TESTING_RATING = 'contact_results.animal_testing_rating';

    /** @var string custom field key for the animal_rights rating weighted */
    const ANIMAL_RIGHTS_RATING = 'contact_results.animal_rights_rating';

    /** @var string custom field key for the hunting_wildlife rating weighted */
    const HUNTING_WILDLIFE_RATING = 'contact_results.hunting_wildlife_rating';

    /** @var string custom field key for the pets rating weighted */
    const PETS_RATING = 'contact_results.pets_rating';

    /** @var string custom field key for the animals_entertainment rating weighted */
    const ANIMALS_ENTERTAINMENT_RATING = 'contact_results.animals_entertainment_rating';

    /** @var string custom field key for the food_consumer_protection rating weighted */
    const FOOD_CONSUMER_PROTECTION_RATING = 'contact_results.food_consumer_protection_rating';

    /**
     * Contact fields of "contact_help_fields" custom group. Helper fields - not to be displayed
     */

    /** @var string custom field key for the overall rating weighted */
    const OVERALL_RATING_WEIGHTED = 'contact_help_fields.overall_rating_weighted';

    /** @var string custom field key for the livestock rating weighted */
    const LIVESTOCK_RATING_WEIGHTED = 'contact_help_fields.livestock_rating_weighted';

    /** @var string custom field key for the aquaculture rating weighted */
    const AQUACULTURE_RATING_WEIGHTED = 'contact_help_fields.aquaculture_rating_weighted';

    /** @var string custom field key for the animal testing rating weighted */
    const ANIMAL_TESTING_RATING_WEIGHTED = 'contact_help_fields.animal_testing_rating_weighted';

    /** @var string custom field key for the animal_rights rating weighted */
    const ANIMAL_RIGHTS_RATING_WEIGHTED = 'contact_help_fields.animal_rights_rating_weighted';

    /** @var string custom field key for the hunting_wildlife rating weighted */
    const HUNTING_WILDLIFE_RATING_WEIGHTED = 'contact_help_fields.hunting_wildlife_rating_weighted';

    /** @var string custom field key for the pets rating weighted */
    const PETS_RATING_WEIGHTED = 'contact_help_fields.pets_rating_weighted';

    /** @var string custom field key for the animals_entertainment rating weighted */
    const ANIMALS_ENTERTAINMENT_RATING_WEIGHTED = 'contact_help_fields.animals_entertainment_rating_weighted';

    /** @var string custom field key for the food_consumer_protection rating weighted */
    const FOOD_CONSUMER_PROTECTION_RATING_WEIGHTED = 'contact_help_fields.food_consumer_protection_rating_weighted';

    /** @var string custom field key for the sum of all coefficients */
    const SUM_COEFFICIENTS = 'contact_help_fields.sum_coefficients';

    /** @var string custom field key for the livestock coefficients */
    const LIVESTOCK_COEFFICIENTS = 'contact_help_fields.livestock_coefficients';

    /** @var string custom field key for the aquaculture coefficients */
    const AQUACULTURE_COEFFICIENTS = 'contact_help_fields.aquaculture_coefficients';

    /** @var string custom field key for the animal testing coefficients */
    const ANIMAL_TESTING_COEFFICIENTS = 'contact_help_fields.animal_testing_coefficients';

    /** @var string custom field key for the animal_rights coefficients */
    const ANIMAL_RIGHTS_COEFFICIENTS = 'contact_help_fields.animal_rights_coefficients';

    /** @var string custom field key for the hunting_wildlife coefficients */
    const HUNTING_WILDLIFE_COEFFICIENTS = 'contact_help_fields.hunting_wildlife_coefficients';

    /** @var string custom field key for the pets coefficients */
    const PETS_COEFFICIENTS = 'contact_help_fields.pets_coefficients';

    /** @var string custom field key for the animals_entertainment coefficients */
    const ANIMALS_ENTERTAINMENT_COEFFICIENTS = 'contact_help_fields.animals_entertainment_coefficients';

    /** @var string custom field key for the food_consumer_protection coefficients */
    const FOOD_CONSUMER_PROTECTION_COEFFICIENTS = 'contact_help_fields.food_consumer_protection_coefficients';

    /** @var array @var list of activity kind coefficient */
    const ACTIVITY_KIND_MAPPING = [
        1 => 1, // public communication
        2 => 2, // speech
        3 => 3, // program item
        4 => 4, // Extra-parliamentary work
        5 => 5, // Parliamentary question
        6 => 6, // participation draft law
        7 => 7, // Vote on subsidies
        8 => 7, // Vote on public contract
        10 => 10, // Vote on draft law
    ];

    /** @var array Mapping for weight coefficient */
    const ACTIVITY_WEIGHT_MAPPING = [
        1 => 1,
        1.5 => 1.5,
        2 => 2,
        2.5 => 2.5,
        3 => 3
    ];

    /** @var array Mapping of score  */
    const ACTIVITY_SCORE_MAPPING = [
        0 => 0,     // bad
        3 => 3,     // rather bad
        7 => 7,     // rather good
        10 => 10,   // good
    ];


    /** @var array the list of fields that are relevant for the calculations */
    const RELEVANT_CONTACT_FIELDS = [
        self::OVERALL_RATING,
        self::CONTACT_IMPORTANCE,
        self::LIVESTOCK_RATING,
        self::AQUACULTURE_RATING,
        self::ANIMAL_TESTING_RATING,
        self::ANIMAL_RIGHTS_RATING,
        self::HUNTING_WILDLIFE_RATING,
        self::PETS_RATING,
        self::ANIMALS_ENTERTAINMENT_RATING,
        self::FOOD_CONSUMER_PROTECTION_RATING,
        self::OVERALL_RATING_WEIGHTED,
        self::LIVESTOCK_RATING_WEIGHTED,
        self::AQUACULTURE_RATING_WEIGHTED,
        self::ANIMAL_TESTING_RATING_WEIGHTED,
        self::ANIMAL_RIGHTS_RATING_WEIGHTED,
        self::HUNTING_WILDLIFE_RATING_WEIGHTED,
        self::PETS_RATING_WEIGHTED,
        self::ANIMALS_ENTERTAINMENT_RATING_WEIGHTED,
        self::FOOD_CONSUMER_PROTECTION_RATING_WEIGHTED,
        self::SUM_COEFFICIENTS,
        self::LIVESTOCK_COEFFICIENTS,
        self::AQUACULTURE_COEFFICIENTS,
        self::ANIMAL_TESTING_COEFFICIENTS,
        self::ANIMAL_RIGHTS_COEFFICIENTS,
        self::HUNTING_WILDLIFE_COEFFICIENTS,
        self::PETS_COEFFICIENTS,
        self::ANIMALS_ENTERTAINMENT_COEFFICIENTS,
        self::FOOD_CONSUMER_PROTECTION_COEFFICIENTS
    ];

    /** @var string custom field key for the activity kind */
    const ACTIVITY_KIND = 'political_activity_additional_fields.kind';

    /** @var string custom field key for the score of the single activity */
    const ACTIVITY_SCORE = 'political_activity_additional_fields.score';

    /** @var string custom field key for the weight of the activity */
    const ACTIVITY_WEIGHT = 'political_activity_additional_fields.weight';

    /** @var string custom field key for the weighted rating of the activity */
    const ACTIVITY_RATING_WEIGHTED = 'political_activity_additional_fields.rating_weighted';



    /** @var array the list of fields that are relevant for the calculations */
    const RELEVANT_ACTIVITY_FIELDS = [
        self::ACTIVITY_KIND,
        self::ACTIVITY_SCORE,
        self::ACTIVITY_WEIGHT,
        self::ACTIVITY_RATING_WEIGHTED,
        self::ACTIVITY_STATUS,
        'activity_date_time'
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
               self::OVERALL_RATING => $example_calculated_score
           ];
           CRM_Rating_CustomData::resolveCustomFields($contact_update);
            civicrm_api3('Contact', 'create', $contact_update);
           Civi::log()->debug("Contact [{$contact_id}] updated.");
       } else {
            Civi::log()->debug("Contact [{$contact_id}] NOT updated (score hasn't changed).");
        }
    }

    ///////////////////////////////////////////////////////
    // Helper functions

    /**
     * Fetches contact_data with needed rating parameters
     *
     * @param $contact_id
     *
     * @return array
     * @throws \CiviCRM_API3_Exception
     */
    public static function fetch_contact_data($contact_id) {
        $current_data_query = [
            'id' => $contact_id,
            'return' => self::getResolvedFieldList(self::RELEVANT_CONTACT_FIELDS)
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
        public static function fetch_relevant_activities($contact_id) {
            $activities = civicrm_api3('Activity', 'get', [
                'option.limit' => 0,
                'activity_type_id' => self::ACTIVITY_TYPE,
                'status_id' => self::ACTIVITY_STATUS,
                'target_contact_id' => $contact_id,
                'return' => self::getResolvedFieldList(self::RELEVANT_ACTIVITY_FIELDS)
            ]);
            return $activities['values'];
        }


    public static function write_rating_weighted($activity_id, $rating_weighted) {
        $activity_data = [
            'id' => $activity_id,
            self::ACTIVITY_RATING_WEIGHTED => $rating_weighted,
        ];
        CRM_Rating_CustomData::resolveCustomFields($activity_data);
        $result = civicrm_api3('Activity', 'create', $activity_data );
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
