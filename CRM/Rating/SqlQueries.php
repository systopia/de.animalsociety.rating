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
 * This class can generate and execute SQL queries and snippets
 */
class CRM_Rating_SqlQueries extends CRM_Rating_Base
{
    /** @var bool can be set to true for sql/data debugging */
    const DEBUG = true;

    /** @var bool can be set to true for tmp table dropping */
    const AUTO_DROP = false; // todo: set to true to save memory? needs testing...

    /***********************************************
     **               ACTIVITY-LEVEL              **
     ***********************************************/

    /**
     * Generate a SQL query to recalculate score (rating_weighted) of the given activity
     *
     * @param array|string $activity_ids
     *   activity IDs to update
     *
     * @return string
     *   generated query
     *
     * @throws Exception
     *   if something's wrong with the expected data structure
     */
    public static function getActivityScoreUpdateQuery($activity_ids = 'all')
    {
        // gather some data and field names
        $activity_data_table = CRM_Rating_CustomData::getGroupTable(self::ACTIVITY_GROUP);
        $target_field = CRM_Rating_CustomData::getCustomField(
            self::ACTIVITY_GROUP,
            self::getFieldName(self::ACTIVITY_RATING_WEIGHTED)
        );

        // not needed: there needs to be some fields filled already in order to make an impact
        self::createMissingActivityCustomData($activity_ids);

        // gather some values
        $activity_status_id = (int) CRM_Rating_Algorithm::getRatingActivityStatusPublished();
        $activity_type_id = (int) CRM_Rating_Algorithm::getRatingActivityTypeID();
        $activity_id_selector = 'IS NOT NULL'; // fallback
        if ($activity_ids != 'all') {
            if (!is_array($activity_ids)) {
                $activity_ids = [$activity_ids];
            }
            $activity_ids = array_map('intval', $activity_ids);
            $activity_id_selector = "IN(" . implode(',', $activity_ids) . ')';
        }

        // generate statement
        $rating_calculation_expression = self::getActivityScoreExpression('activity_rating', 'activity');

        // create a temp table with the results, because we'd run into a "Can't update table in stored function/trigger because it is already used by statement which invoked this stored function/trigger"
        CRM_Core_DAO::disableFullGroupByMode();
        $new_values_query = "
            SELECT activity.id AS activity_id, {$rating_calculation_expression} AS rating
            FROM {$activity_data_table} activity_rating
            LEFT JOIN civicrm_activity activity ON activity_rating.entity_id = activity.id
            WHERE activity_rating.entity_id {$activity_id_selector}
              AND activity.status_id = {$activity_status_id}
              AND activity.activity_type_id = {$activity_type_id}";
        $tmp_table = CRM_Utils_SQL_TempTable::build()
            ->setMemory(!self::DEBUG)
            ->setDurable(self::DEBUG)
            ->setAutodrop(self::AUTO_DROP)
            ->createWithQuery($new_values_query);
        CRM_Core_DAO::reenableFullGroupByMode();

        // add index...
        $tmp_table_name = $tmp_table->getName();
        CRM_Core_DAO::executeQuery("ALTER TABLE {$tmp_table_name} ADD INDEX activity_id(activity_id);");

        /* for some weird, very annoying reason, the following doesn't work in my setup:
        return "
            UPDATE {$activity_data_table} activity_rating
            INNER JOIN {$tmp_table_name} new_values ON new_values.activity_id = activity_rating.entity_id
            SET activity_rating.{$target_field['column_name']} = new_values.rating;";
        */

        // workaround: return the select query
        return "SELECT activity_id, rating FROM {$tmp_table_name}";
    }

    /**
     * Generate a SQL expression to calculate the current scoring value
     *
     * @param string $activity_table
     *   name for the activity table
     *
     * @param string $activity_rating_table
     *   name for the activity rating table
     *
     * @return string
     *   sql expression
     */
    protected static function getActivityScoreExpression($activity_rating_table, $activity_table)
    {
        // type coefficient "Aktivitätsart-Koeffizient"
        [$field_group, $field_name] = explode('.', self::ACTIVITY_KIND);
        $kind_coefficient_field = CRM_Rating_CustomData::getCustomField($field_group, $field_name);
        $kind_coefficient = self::createSqlMappingExpression(
            "{$activity_rating_table}.{$kind_coefficient_field['column_name']}",
            self::ACTIVITY_KIND_MAPPING
        );

        // weight coefficient "Gewichtungskoeffizient"
        [$field_group, $field_name] = explode('.', self::ACTIVITY_WEIGHT);
        $weight_coefficient_field = CRM_Rating_CustomData::getCustomField($field_group, $field_name);
        $weight_coefficient = self::createSqlMappingExpression(
            "{$activity_rating_table}.{$weight_coefficient_field['column_name']}",
            self::ACTIVITY_WEIGHT_MAPPING
        );

        // age coefficient "Alterskoeffizient"
        $age_coefficient = "0.75 / (POWER(((DATEDIFF(NOW(), {$activity_table}.activity_date_time)/365.0)/2.9), 4) + 1.0) + 0.25";

        // score coefficient "Gewichtete Note"
        [$field_group, $field_name] = explode('.', self::ACTIVITY_SCORE);
        $score_coefficient_field = CRM_Rating_CustomData::getCustomField($field_group, $field_name);
        $score_coefficient = self::createSqlMappingExpression(
            "{$activity_rating_table}.{$score_coefficient_field['column_name']}",
            self::ACTIVITY_SCORE_MAPPING
        );

        // return as product of all coefficients
        return "( ({$kind_coefficient}) * ({$weight_coefficient}) * ({$score_coefficient}) * ({$age_coefficient}) )";
    }



    /***********************************************
     **              INDIVIDUAL-LEVEL             **
     ***********************************************/

    /**
     * Run a SQL query to recalculate score of the given contacts
     *
     * @param array|string $contact_ids
     *   activity IDs to update
     *
     * @throws Exception
     *   if something's wrong with the expected data structure
     */
    public static function runPersonAggregationUpdateQuery($contact_ids = 'all')
    {
        // extract contact ID restrictions
        if ($contact_ids != 'all') {
            if (!is_array($contact_ids)) {
                $contact_ids = [$contact_ids];
            }
            $contact_ids = array_map('intval', $contact_ids);
            $contact_id_selector = "(contact.contact_type = 'Individual' AND contact.id IN(" . implode(',', $contact_ids) . '))';
        } else {
            $contact_id_selector = "(contact.contact_type = 'Individual' AND contact.is_deleted = 0)";
        }

        // generate temp table with the values
        $activity_data_table = CRM_Rating_CustomData::getGroupTable(self::ACTIVITY_GROUP);
        $activity_status_id = (int) CRM_Rating_Algorithm::getRatingActivityStatusPublished();
        $activity_type_id = (int) CRM_Rating_Algorithm::getRatingActivityTypeID();
        $activity_rating_field = CRM_Rating_CustomData::getCustomField(
            self::ACTIVITY_GROUP,
            self::getFieldName(self::ACTIVITY_RATING_WEIGHTED)
        );
        $activity_weight_field = CRM_Rating_CustomData::getCustomField(
            self::ACTIVITY_GROUP,
            self::getFieldName(self::ACTIVITY_SCORE)
        );

        // ballast parameters
        $ballast_value  = CRM_Rating_Algorithm::BALLAST_VALUE;
        $ballast_weight = CRM_Rating_Algorithm::BALLAST_WEIGHT;

        $CATEGORY_SUMIFS = '';
        foreach (self::CONTACT_FIELD_TO_ACTIVITY_CATEGORIES_MAPPING as $column_name => $categories) {
            $CATEGORY_SUMIFS .= "(SUM(IF(activity_data.category IN({$categories}), activity_data.{$activity_rating_field['column_name']}, 0.0)) + {$ballast_value}) / (SUM(IF(activity_data.category IN({$categories}), activity_data.{$activity_rating_field['column_name']} / {$activity_weight_field['column_name']}, 0)) + {$ballast_weight}) AS {$column_name}, \n";
        }
        $calculation_query = "
            SELECT
                contact.id                                                AS contact_id,
                COUNT(activity.id)                                        AS activity_count,
                {$CATEGORY_SUMIFS}
                (SUM(activity_data.{$activity_rating_field['column_name']}) + {$ballast_value}) / (SUM(activity_data.{$activity_rating_field['column_name']} / {$activity_weight_field['column_name']}) + {$ballast_weight}) AS overall_rating
            FROM civicrm_contact contact
            LEFT JOIN civicrm_activity_contact activity_link
                   ON activity_link.contact_id = contact.id
                   AND record_type_id = 3
            LEFT JOIN civicrm_activity activity
                   ON activity_link.activity_id = activity.id
            LEFT JOIN {$activity_data_table} activity_data
                   ON activity_data.entity_id = activity.id
            WHERE {$contact_id_selector}
              AND activity.activity_type_id = {$activity_type_id}
              AND activity.status_id = {$activity_status_id}
              AND contact.contact_type = 'Individual'
              AND activity_data.{$activity_rating_field['column_name']} IS NOT NULL
            GROUP BY contact.id
            ;";

        // create a temp table with the results, because we'd run into a "Can't update table in stored function/trigger because it is already used by statement which invoked this stored function/trigger"
        CRM_Core_DAO::disableFullGroupByMode();
        $tmp_table = CRM_Utils_SQL_TempTable::build()
            ->setMemory(!self::DEBUG)
            ->setDurable(self::DEBUG)
            ->setAutodrop(self::AUTO_DROP)
            ->createWithQuery($calculation_query);

        // add index...
        $tmp_table_name = $tmp_table->getName();
        CRM_Core_DAO::executeQuery("ALTER TABLE {$tmp_table_name} ADD INDEX contact_id(contact_id);");
        CRM_Core_DAO::reenableFullGroupByMode();

        // make sure the entries are all there
        CRM_Core_DAO::executeQuery("
            INSERT IGNORE INTO civicrm_value_contact_results (entity_id)
            SELECT contact_id AS entity_id FROM {$tmp_table_name}");

        // ... and return the value update query
        $contact_data_table = CRM_Rating_CustomData::getGroupTable(self::CONTACT_GROUP);
        $INDIVIDUAL_FIELDS_SQL = '';
        foreach (self::CONTACT_FIELD_TO_ACTIVITY_CATEGORIES_MAPPING as $column_name => $categories) {
            $INDIVIDUAL_FIELDS_SQL .= ", contact_rating.{$column_name} = new_values.{$column_name}";
        }
        $update_query = "
            UPDATE {$contact_data_table} contact_rating
            INNER JOIN {$tmp_table_name} new_values ON new_values.contact_id = contact_rating.entity_id
            SET contact_rating.overall_rating = new_values.overall_rating
                {$INDIVIDUAL_FIELDS_SQL}";
        CRM_Core_DAO::executeQuery($update_query);
    }


    /***********************************************
     **           PARTY/ORGANISATION-LEVEL        **
     ***********************************************/

    /**
     * Run a SQL query to recalculate score of the given contacts
     *
     * @param array|string $contact_ids
     *   activity IDs to update
     *
     * @throws Exception
     *   if something's wrong with the expected data structure
     */
    public static function runOrganisationAggregationUpdateQuery($contact_ids = 'all')
    {
        // extract contact ID restrictions
        if ($contact_ids != 'all') {
            if (!is_array($contact_ids)) {
                $contact_ids = [$contact_ids];
            }
            $contact_ids = array_map('intval', $contact_ids);
            $contact_id_selector = "(contact.contact_type = 'Organization' AND contact.id IN(" . implode(',', $contact_ids) . '))';
        } else {
            $contact_id_selector = "(contact.contact_type = 'Organization' AND contact.is_deleted = 0)";
        }

        /************************************************
         **      1/10: ORGANISATION'S OWN ACTIVITIES   **
         **       (algorithm identical to persons')    **
         **       results in {$activity_tmp_table}     **
         ************************************************/

        // ballast parameters
        $ballast_value  = CRM_Rating_Algorithm::BALLAST_VALUE;
        $ballast_weight = CRM_Rating_Algorithm::BALLAST_WEIGHT;

        // look up fields
        $activity_data_table = CRM_Rating_CustomData::getGroupTable(self::ACTIVITY_GROUP);
        $activity_status_id = (int) CRM_Rating_Algorithm::getRatingActivityStatusPublished();
        $activity_type_id = (int) CRM_Rating_Algorithm::getRatingActivityTypeID();
        $activity_rating_field = CRM_Rating_CustomData::getCustomField(
            self::ACTIVITY_GROUP,
            self::getFieldName(self::ACTIVITY_RATING_WEIGHTED)
        );
        $activity_weight_field = CRM_Rating_CustomData::getCustomField(
            self::ACTIVITY_GROUP,
            self::getFieldName(self::ACTIVITY_SCORE)
        );

        $CATEGORY_SUMIFS = '';
        foreach (self::CONTACT_FIELD_TO_ACTIVITY_CATEGORIES_MAPPING as $column_name => $categories) {
            $CATEGORY_SUMIFS .= "(SUM(IF(activity_data.category IN({$categories}), activity_data.{$activity_rating_field['column_name']}, 0.0)) + {$ballast_value}) / (SUM(IF(activity_data.category IN({$categories}), activity_data.{$activity_rating_field['column_name']} / {$activity_weight_field['column_name']}, 0)) + {$ballast_weight}) AS {$column_name}, \n";
        }
        $activity_calculation_query = "
            SELECT
                contact.id AS contact_id,
                {$CATEGORY_SUMIFS}
                (SUM(activity_data.{$activity_rating_field['column_name']}) + {$ballast_value}) / (SUM(activity_data.{$activity_rating_field['column_name']} / {$activity_weight_field['column_name']}) + {$ballast_weight}) AS overall_rating
            FROM civicrm_contact contact
            LEFT JOIN civicrm_activity_contact activity_link
                   ON activity_link.contact_id = contact.id
                   AND record_type_id = 3
            LEFT JOIN civicrm_activity activity
                   ON activity_link.activity_id = activity.id
            LEFT JOIN {$activity_data_table} activity_data
                   ON activity_data.entity_id = activity.id
            WHERE {$contact_id_selector}
              AND activity.activity_type_id = {$activity_type_id}
              AND activity.status_id = {$activity_status_id}
              AND activity_data.{$activity_rating_field['column_name']} IS NOT NULL
            GROUP BY contact.id
            ;";

        // create a temp table with the results, because we'd run into a "Can't update table in stored function/trigger because it is already used by statement which invoked this stored function/trigger"
        CRM_Core_DAO::disableFullGroupByMode();
        $activity_tmp_table = CRM_Utils_SQL_TempTable::build()
            ->setMemory(!self::DEBUG)
            ->setDurable(self::DEBUG)
            ->setAutodrop(self::AUTO_DROP)
            ->createWithQuery($activity_calculation_query);

        // add index...
        $activity_tmp_table_name = $activity_tmp_table->getName();
        CRM_Core_DAO::executeQuery("ALTER TABLE {$activity_tmp_table_name} ADD INDEX contact_id(contact_id);");
        CRM_Core_DAO::reenableFullGroupByMode();



        /************************************************
         **    9/10: ORGANISATION MEMBERS' RATINGS     **
         **          aggregate members' ratings        **
         **       results in {$activity_tmp_table}     **
         ************************************************/

        $membership_relationship_type_id = self::getPartyMemberRelationshipTypeId();
        $contact_data_table = CRM_Rating_CustomData::getGroupTable(self::CONTACT_GROUP);

        $CATEGORY_AGGREGATION = '';
        foreach (self::CONTACT_FIELD_TO_ACTIVITY_CATEGORIES_MAPPING as $column_name => $categories) {
            $CATEGORY_AGGREGATION .= "IFNULL(SUM(member_scores.$column_name) / SUM(member_scores.contact_importance), 0) AS {$column_name}, \n";
        }
        $members_calculation_query = "
            SELECT
                contact.id                      AS contact_id,
                {$CATEGORY_AGGREGATION}
                IFNULL(SUM(member_scores.overall_rating) / SUM(member_scores.contact_importance), 0)
                                                AS members_total_rating
            FROM civicrm_contact contact
            INNER JOIN civicrm_relationship membership
                   ON membership.contact_id_b = contact.id
                   AND membership.relationship_type_id = {$membership_relationship_type_id}
                   AND membership.is_active = 1
            INNER JOIN civicrm_contact member
                   ON membership.contact_id_a = member.id
                   AND member.is_deleted = 0
            LEFT JOIN {$contact_data_table} member_scores
                   ON member_scores.entity_id = member.id
            WHERE {$contact_id_selector}
              AND contact.contact_type = 'Organization'
            GROUP BY contact.id
            ;";

        // create a temp table with the results, because we'd run into a "Can't update table in stored function/trigger because it is already used by statement which invoked this stored function/trigger"
        CRM_Core_DAO::disableFullGroupByMode();
        $member_scores_tmp_table = CRM_Utils_SQL_TempTable::build()
            ->setMemory(!self::DEBUG)
            ->setDurable(self::DEBUG)
            ->setAutodrop(self::AUTO_DROP)
            ->createWithQuery($members_calculation_query);

        // add index...
        $member_scores_tmp_table_name = $member_scores_tmp_table->getName();
        CRM_Core_DAO::executeQuery("ALTER TABLE {$member_scores_tmp_table_name} ADD INDEX contact_id(contact_id);");
        CRM_Core_DAO::reenableFullGroupByMode();

        /*************************************************
         ** COMBINE OWN ACTIVITY'S AND MEMBER's RATINGS **
         **************************************************/

        // first, make sure the entries are all there
        self::createMissingContactCustomData($contact_ids);
//        CRM_Core_DAO::executeQuery("
//            INSERT IGNORE INTO {$contact_data_table} (entity_id)
//            SELECT contact_id AS entity_id FROM {$activity_tmp_table_name}");
//        CRM_Core_DAO::executeQuery("
//            INSERT IGNORE INTO {$contact_data_table} (entity_id)
//            SELECT contact_id AS entity_id FROM {$member_scores_tmp_table_name}");

        // then run the value update query
        $update_query = "
            UPDATE {$contact_data_table} contact_rating
            INNER JOIN {$activity_tmp_table_name} activity_rating
                    ON activity_rating.contact_id = contact_rating.entity_id
            INNER JOIN {$member_scores_tmp_table_name} member_rating
                    ON member_rating.contact_id = contact_rating.entity_id
            SET contact_rating.overall_rating = (0.1 * IFNULL(activity_rating.overall_rating, 0.0) + 0.9 * IFNULL(member_rating.members_total_rating, 0.0))
            WHERE activity_rating.contact_id IS NOT NULL OR member_rating.contact_id IS NOT NULL

        ";
        // todo: individual fields
        //        foreach (self::CONTACT_FIELD_TO_ACTIVITY_CATEGORIES_MAPPING as $column_name => $categories) {
        //            $INDIVIDUAL_FIELDS_SQL .= ", contact_rating.{$column_name} = new_values.{$column_name}";
        //        }

        CRM_Core_DAO::executeQuery($update_query);
    }


    /***************************************************
     ***             RELATIONSHIP QUERIES            ***
     ***************************************************/

    /**
     * Fetch the contacts used by this activities
     *
     * @param array|string $activity_ids
     *   'all' or list of IDs
     *
     * @return string|array
     *   'all' or list of IDs
     */
    public static function getContactIdsForActivities($activity_ids)
    {
        if ($activity_ids == 'all') return 'all';
        $contact_ids = [];
        $activity_ids = implode(',', array_map('intval', $activity_ids));
        if (!empty($activity_ids)) {
            $activity_type_id = (int) CRM_Rating_Algorithm::getRatingActivityTypeID();
            $query = "
                SELECT DISTINCT(ac.contact_id) AS contact_id
                FROM civicrm_activity activity
                LEFT JOIN civicrm_activity_contact ac ON ac.activity_id = activity.id
                WHERE ac.activity_id IN ({$activity_ids})
                  AND ac.record_type_id = 3
                  AND activity.activity_type_id = {$activity_type_id}";
            $result = CRM_Core_DAO::executeQuery($query)->fetchAll();
            foreach ($result as $result_entry) {
                $contact_ids[] = $result_entry['contact_id'];
            }
        }
        return $contact_ids;
    }

    /**
     * Fetch the activity IDs used by this contacts
     *
     * @param array|string $contact_ids
     *   'all' or list of IDs
     *
     * @return string|array
     *   'all' or list of IDs
     */
    public static function getActivityIdsForContacts($contact_ids)
    {
        if ($contact_ids == 'all') return 'all';
        $activity_ids = [];
        $contact_ids = implode(',', array_map('intval', $contact_ids));
        if (!empty($contact_ids)) {
            $activity_type_id = (int) CRM_Rating_Algorithm::getRatingActivityTypeID();
            $query = "
                SELECT DISTINCT(ac.activity_id) AS activity_id
                FROM civicrm_activity activity
                LEFT JOIN civicrm_activity_contact ac ON ac.activity_id = activity.id
                WHERE ac.contact_id IN ({$contact_ids})
                  AND ac.record_type_id = 3
                  AND activity.activity_type_id = {$activity_type_id}";
            $result = CRM_Core_DAO::executeQuery($query)->fetchAll();
            foreach ($result as $result_entry) {
                $activity_ids[] = $result_entry['activity_id'];
            }
        }
        return $activity_ids;
    }

    /**
     * Fetch the organisation contact IDs for the given individual contact ids
     *
     * @param array|string $contact_ids
     *   'all' or list of IDs
     *
     * @return string|array
     *   'all' or list of IDs
     */
    public static function getOrganisationIdsForContacts($contact_ids)
    {
        if ($contact_ids == 'all') return 'all';

        $organisation_ids = [];
        $contact_ids = implode(',', array_map('intval', $contact_ids));
        if (!empty($contact_ids)) {
            $relationship_type_id = (int) CRM_Rating_Base::getPartyMemberRelationshipTypeId();
            $query = "
                SELECT DISTINCT(party.id) AS organisation_id
                FROM civicrm_contact member
                LEFT JOIN civicrm_relationship party_member
                       ON member.id = party_member.contact_id_a
                LEFT JOIN civicrm_contact party
                       ON party.id  = party_member.contact_id_b
                WHERE member.id IN ({$contact_ids})
                  AND party_member.relationship_type_id = {$relationship_type_id}
                  AND member.is_deleted = 0
                  AND party.is_deleted = 0
                  AND party_member.is_active = 1";
            $result = CRM_Core_DAO::executeQuery($query)->fetchAll();
            foreach ($result as $result_entry) {
                $organisation_ids[] = $result_entry['organisation_id'];
            }
        }
        return $organisation_ids;
    }

    /**
     * Fetch the individual contact IDs for the given organsation contact ids
     *
     * @param array|string $contact_ids
     *   'all' or list of IDs
     *
     * @return string|array
     *   'all' or list of IDs
     */
    public static function getContactIdsForOrganisations($contact_ids)
    {
        if ($contact_ids == 'all') return 'all';
        Civi::log()->warning("getContactIdsForOrganisations not implemented");
        return [];
    }





    /**
     * Generate a SQL expression for a numeric mapping
     *
     * @param string $value_source
     *   sql expression of where to take the value-to-be-mapped from
     *
     * @param array $mapping
     *   from / to int or float pairs
     *
     * @param string $fallback
     *   fallback value
     *
     * @return string
     *   generated expression
     */
    protected static function createSqlMappingExpression($value_source, $mapping, $from_type = 'int', $to_type = 'float', $fallback = null)
    {
        $expression = "CASE ";
        foreach ($mapping as $from => $to) {
            // make sure we have numbers (no sql injection)
            switch ($from_type) {
                default:
                case 'int':
                    $from = (int) $from;
                    break;
                case 'float':
                    $from = (float) $from;
            }
            switch ($to_type) {
                default:
                case 'int':
                    $to = (int) $to;
                    break;
                case 'float':
                    $to = (float) $to;
            }
            $expression .= " WHEN {$value_source} = {$from} THEN {$to} ";
        }
        if ($fallback !== null) {
            $expression .= " ELSE {$fallback}";
        }
        $expression .= " END ";
        return $expression;
    }

    /**
     * @param mixed $qualified_field_name
     *   the field name specified as "group_name.field_name" or [group_name, field_name]
     *
     * @return string
     *   field name
     */
    public static function getFieldName($qualified_field_name)
    {
        if (!is_array($qualified_field_name)) {
            $qualified_field_name = explode('.', $qualified_field_name);
        }
        return $qualified_field_name[1];
    }

    /**
     * Generate missing entries in the activity custom data table
     *
     * @param string|array $activity_ids
     *
     * @return void
     */
    protected static function createMissingActivityCustomData($activity_ids)
    {
        // get the group table
        $activity_data_table = CRM_Rating_CustomData::getGroupTable(self::ACTIVITY_GROUP);

        if ($activity_ids != 'all') {
            if (!is_array($activity_ids)) {
                $activity_ids = [$activity_ids];
            }
            $activity_ids = array_map('intval', $activity_ids);
            $activity_id_selector = "IN(" . implode(',', $activity_ids) . ')';
            CRM_Core_DAO::executeQuery("
            INSERT IGNORE INTO {$activity_data_table} (entity_id)
            SELECT id FROM civicrm_activity WHERE id {$activity_id_selector}");
        } else {
            $activity_type_id = (int) CRM_Rating_Algorithm::getRatingActivityTypeID();
            // todo: there has to be a better way to fill the missing rows
            CRM_Core_DAO::executeQuery("
            INSERT IGNORE INTO {$activity_data_table} (entity_id)
            SELECT id FROM civicrm_activity WHERE activity_type_id = {$activity_type_id}");
        }
    }

    /**
     * Generate missing entries in the activity custom data table
     *
     * @param string|array $contact_ids
     *
     * @return void
     */
    protected static function createMissingContactCustomData($contact_ids)
    {
        // get the group table
        $contact_data_table = CRM_Rating_CustomData::getGroupTable(self::CONTACT_GROUP);

        if ($contact_ids != 'all') {
            if (!is_array($contact_ids)) {
                $contact_ids = [$contact_ids];
            }
            $contact_ids = array_map('intval', $contact_ids);
            $contact_id_selector = "IN(" . implode(',', $contact_ids) . ')';
            $fill_query = "
                INSERT IGNORE INTO {$contact_data_table} (entity_id)
                SELECT id AS entity_id FROM civicrm_contact WHERE id {$contact_id_selector}";
            CRM_Core_DAO::executeQuery($fill_query);
        } else {
            $relationship_type_id = (int) CRM_Rating_Base::getPartyMemberRelationshipTypeId();
            // todo: there has to be a better way to fill the missing rows
            CRM_Core_DAO::executeQuery("
            INSERT IGNORE INTO {$contact_data_table} (entity_id)
            SELECT contact_id_b FROM civicrm_relationship WHERE relationship_type_id = {$relationship_type_id}");
        }
    }
}
