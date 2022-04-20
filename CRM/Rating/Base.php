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
 * This class contains all sorts of definitions and helper functions to deal with the
 *   data structures
 */
abstract class CRM_Rating_Base
{
    /** @var string custom field key for the activity type */
    const ACTIVITY_TYPE = 'political_activity';

    /** @var string custom field key for the activity status */
    const ACTIVITY_STATUS = 'published';

    /**
     * FIELDS of contact_results custom group.
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
     * FIELDS of "contact_help_fields" custom group
     */
//
//    /** @var string custom field key for the overall rating weighted */
//    const OVERALL_RATING_WEIGHTED = 'contact_help_fields.overall_rating_weighted';
//
//    /** @var string custom field key for the livestock rating weighted */
//    const LIVESTOCK_RATING_WEIGHTED = 'contact_help_fields.livestock_rating_weighted';
//
//    /** @var string custom field key for the aquaculture rating weighted */
//    const AQUACULTURE_RATING_WEIGHTED = 'contact_help_fields.aquaculture_rating_weighted';
//
//    /** @var string custom field key for the animal testing rating weighted */
//    const ANIMAL_TESTING_RATING_WEIGHTED = 'contact_help_fields.animal_testing_rating_weighted';
//
//    /** @var string custom field key for the animal_rights rating weighted */
//    const ANIMAL_RIGHTS_RATING_WEIGHTED = 'contact_help_fields.animal_rights_rating_weighted';
//
//    /** @var string custom field key for the hunting_wildlife rating weighted */
//    const HUNTING_WILDLIFE_RATING_WEIGHTED = 'contact_help_fields.hunting_wildlife_rating_weighted';
//
//    /** @var string custom field key for the pets rating weighted */
//    const PETS_RATING_WEIGHTED = 'contact_help_fields.pets_rating_weighted';
//
//    /** @var string custom field key for the animals_entertainment rating weighted */
//    const ANIMALS_ENTERTAINMENT_RATING_WEIGHTED = 'contact_help_fields.animals_entertainment_rating_weighted';
//
//    /** @var string custom field key for the food_consumer_protection rating weighted */
//    const FOOD_CONSUMER_PROTECTION_RATING_WEIGHTED = 'contact_help_fields.food_consumer_protection_rating_weighted';
//
//    /** @var string custom field key for the sum of all coefficients */
//    const SUM_COEFFICIENTS = 'contact_help_fields.sum_coefficients';
//
//    /** @var string custom field key for the livestock coefficients */
//    const LIVESTOCK_COEFFICIENTS = 'contact_help_fields.livestock_coefficients';
//
//    /** @var string custom field key for the aquaculture coefficients */
//    const AQUACULTURE_COEFFICIENTS = 'contact_help_fields.aquaculture_coefficients';
//
//    /** @var string custom field key for the animal testing coefficients */
//    const ANIMAL_TESTING_COEFFICIENTS = 'contact_help_fields.animal_testing_coefficients';
//
//    /** @var string custom field key for the animal_rights coefficients */
//    const ANIMAL_RIGHTS_COEFFICIENTS = 'contact_help_fields.animal_rights_coefficients';
//
//    /** @var string custom field key for the hunting_wildlife coefficients */
//    const HUNTING_WILDLIFE_COEFFICIENTS = 'contact_help_fields.hunting_wildlife_coefficients';
//
//    /** @var string custom field key for the pets coefficients */
//    const PETS_COEFFICIENTS = 'contact_help_fields.pets_coefficients';
//
//    /** @var string custom field key for the animals_entertainment coefficients */
//    const ANIMALS_ENTERTAINMENT_COEFFICIENTS = 'contact_help_fields.animals_entertainment_coefficients';
//
//    /** @var string custom field key for the food_consumer_protection coefficients */
//    const FOOD_CONSUMER_PROTECTION_COEFFICIENTS = 'contact_help_fields.food_consumer_protection_coefficients';


    /**
     * MAPPINGS
     *
     * @todo configurable?
     */

    /** @var array @var list of activity kind coefficient */
    const ACTIVITY_KIND_MAPPING = [
        1 => 1.0, // public communication
        2 => 2.0, // speech
        3 => 3.0, // program item
        4 => 4.0, // Extra-parliamentary work
        5 => 5.0, // Parliamentary question
        6 => 6.0, // participation draft law
        7 => 7.0, // Vote on subsidies
        8 => 7.0, // Vote on public contract
        10 => 10.0, // Vote on draft law
    ];

    /** @var array Mapping for weight coefficient */
    const ACTIVITY_WEIGHT_MAPPING = [
        10 => 1.0,
        15 => 1.5,
        20 => 2.0,
        25 => 2.5,
        30 => 3.0
    ];

    /** @var array Mapping of score  */
    const ACTIVITY_SCORE_MAPPING = [
        0 => 0.0,     // bad
        3 => 3.0,     // rather bad
        5 => 5.0,     // average
        7 => 7.0,     // rather good
        10 => 10.0,   // good
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
//        self::OVERALL_RATING_WEIGHTED,
//        self::LIVESTOCK_RATING_WEIGHTED,
//        self::AQUACULTURE_RATING_WEIGHTED,
//        self::ANIMAL_TESTING_RATING_WEIGHTED,
//        self::ANIMAL_RIGHTS_RATING_WEIGHTED,
//        self::HUNTING_WILDLIFE_RATING_WEIGHTED,
//        self::PETS_RATING_WEIGHTED,
//        self::ANIMALS_ENTERTAINMENT_RATING_WEIGHTED,
//        self::FOOD_CONSUMER_PROTECTION_RATING_WEIGHTED,
//        self::SUM_COEFFICIENTS,
//        self::LIVESTOCK_COEFFICIENTS,
//        self::AQUACULTURE_COEFFICIENTS,
//        self::ANIMAL_TESTING_COEFFICIENTS,
//        self::ANIMAL_RIGHTS_COEFFICIENTS,
//        self::HUNTING_WILDLIFE_COEFFICIENTS,
//        self::PETS_COEFFICIENTS,
//        self::ANIMALS_ENTERTAINMENT_COEFFICIENTS,
//        self::FOOD_CONSUMER_PROTECTION_COEFFICIENTS
    ];

    /**
     * Maps the column name in the contact field set to the related activity categories
     */
    const CONTACT_FIELD_TO_ACTIVITY_CATEGORIES_MAPPING = [
        'livestock_rating'                => '1', // livestock
        'aquaculture_rating'              => '2', // aquaculture
        'animal_testing_rating'           => '3', // animals_in_research
        'animal_rights_rating'            => '4', // animal_rights
        'hunting_wildlife_rating'         => '5', // wildlife
        'pets_rating'                     => '6', // pets
        'animals_entertainment_rating'    => '7', // animals_entertainment
        'food_consumer_protection_rating' => '8', // food_consumer_protection
    ];

    /** @var string custom group key for the activity fields */
    const ACTIVITY_GROUP = 'political_activity_additional_fields';

    /** @var string custom group key for the contact fields */
    const CONTACT_GROUP = 'contact_results';



    /** @var string custom field key for the activity kind */
    const ACTIVITY_KIND = 'political_activity_additional_fields.rating_kind';

    /** @var string custom field key for the activity kind */
    const ACTIVITY_CATEGORY = 'political_activity_additional_fields.rating_category';

    /** @var string custom field key for the activity title */
    const ACTIVITY_TITLE = 'political_activity_additional_fields.rating_title';

    /** @var string custom field key for the score of the single activity */
    const ACTIVITY_SCORE = 'political_activity_additional_fields.rating_score';

    /** @var string custom field key for the weight of the activity */
    const ACTIVITY_WEIGHT = 'political_activity_additional_fields.rating_weight';

    /** @var string custom field key for the weighted rating of the activity */
    const ACTIVITY_RATING_WEIGHTED = 'political_activity_additional_fields.rating_rating_weighted';

    /** @var array the list of fields that are relevant for the calculations */
    const RELEVANT_ACTIVITY_FIELDS = [
        self::ACTIVITY_TITLE,
        self::ACTIVITY_KIND,
        self::ACTIVITY_SCORE,
        self::ACTIVITY_WEIGHT,
        self::ACTIVITY_RATING_WEIGHTED,
        self::ACTIVITY_STATUS,
        'activity_date_time'
    ];

    /**
     * Get the rating activity type ID
     *
     * @return integer
     *   activity type id
     *
     * @throws \CiviCRM_API3_Exception if the activity type doesn't exist
     */
    public static function getRatingActivityTypeID() : int
    {
        static $activity_type_id = null;
        if ($activity_type_id == null) {
            $activity_type_id = (int) civicrm_api3('OptionValue', 'getvalue', [
                'return' => 'value',
                'option_group_id' => 'activity_type',
                'name' => self::ACTIVITY_TYPE
            ]);
        }
        return $activity_type_id;
    }

    /**
     * Get the activity status ID for 'published'
     *
     * @return integer
     *   activity status id
     *
     * @throws \CiviCRM_API3_Exception if the activity type doesn't exist
     */
    public static function getRatingActivityStatusPublished()
    {
        static $activity_status_published_id = null;
        if ($activity_status_published_id == null) {
            $activity_status_published_id = (int) civicrm_api3('OptionValue', 'getvalue', [
                'return' => 'value',
                'option_group_id' => 'activity_status',
                'name' => 'published'
            ]);
        }
        return $activity_status_published_id;
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
    public static function getResolvedFieldList($field_list)
    {
        $field_names_as_keys = array_flip($field_list);
        CRM_Rating_CustomData::resolveCustomFields($field_names_as_keys);
        return array_flip($field_names_as_keys);
    }

    /**
     * Log the given status message
     *
     * @param string $message
     */
    public static function log($message)
    {
        // todo: have a separate log file?
        Civi::log()->debug($message);
    }
}
