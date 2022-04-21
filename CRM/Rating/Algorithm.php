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


use CRM_Rating_ExtensionUtil as E;

/**
 * This class implements the algorithms to calculate the aggregated rating values
 */
class CRM_Rating_Algorithm extends CRM_Rating_Base
{
    const BALLAST_VALUE = 15.0;
    const BALLAST_WEIGHT = 3.0;

    /**
     * Update the rating value of a selection of engagement activities
     *
     * @param array|string $activity_ids
     *   IDs of the activities to be updated, or 'all'
     *
     * @throws Exception
     *   if something's wrong with the algorithm or the data structures
     */
    public static function updateActivities($activity_ids = 'all')
    {
        $timestamp = microtime(true);
        $query = CRM_Rating_SqlQueries::getActivityScoreUpdateQuery($activity_ids);
        // @todo originally, this *is* the query applying the value updates to the DB table. But, For some crazy reason
        //  that doesn't work. Maybe it's hidden transactions, magic, or the evil eye.
        $result = CRM_Core_DAO::executeQuery($query);

        // WORKAROUND: fetch the entries individually, and run update queries (SLOW!!)
        $activity_data_table = CRM_Rating_CustomData::getGroupTable(self::ACTIVITY_GROUP);
        $target_field = CRM_Rating_CustomData::getCustomField(
            self::ACTIVITY_GROUP,
            CRM_Rating_SqlQueries::getFieldName(self::ACTIVITY_RATING_WEIGHTED)
        );
        $query = "UPDATE {$activity_data_table} activity_rating SET {$target_field['column_name']} = %1 WHERE entity_id = %2";
        while($result->fetch()) {
            if ($result->rating) {
                CRM_Core_DAO::executeQuery($query, [1 => [$result->rating, 'Float'], 2 => [$result->activity_id, 'Integer']]);
            }
        }

        // wrap up
        $runtime = microtime(true) - $timestamp;
        $count = $activity_ids == 'all' ? 'all' : count($activity_ids);
        self::log("Updating {$count} activities took {$runtime} seconds.");
    }

    /**
     * Update contact's aggregated values based on the linked activities
     *
     * @param array|string $contact_ids
     *   IDs of the contacts to be updated, or 'all'
     *
     * @throws Exception
     *   if something's wrong with the algorithm or the data structures
     */
    public static function updateIndividuals($contact_ids = 'all')
    {
        $timestamp = microtime(true);
        $query = CRM_Rating_SqlQueries::runContactAggregationUpdateQuery($contact_ids, 'Individual');
        $runtime = microtime(true) - $timestamp;
        $count = $contact_ids == 'all' ? 'all' : count($contact_ids);
        self::log("Updating {$count} contacts took {$runtime} seconds.");
    }

    /**
     * Update contact's aggregated values based on the linked activities
     *
     * @param array|string $contact_ids
     *   IDs of the contacts to be updated, or 'all'
     *
     * @throws Exception
     *   if something's wrong with the algorithm or the data structures
     */
    public static function updateOrganisations($contact_ids = 'all')
    {
        $timestamp = microtime(true);
        $query = CRM_Rating_SqlQueries::runContactAggregationUpdateQuery($contact_ids, 'Organization');
        $runtime = microtime(true) - $timestamp;
        $count = $contact_ids == 'all' ? 'all' : count($contact_ids);
        self::log("Updating {$count} organisations took {$runtime} seconds.");
        self::log("WARNING: only organisation's own activities considered");
    }
}
