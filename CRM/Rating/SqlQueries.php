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
    /***********************************************
     **               ACTIVITY-LEVEL              **
     ***********************************************/

    /**
     * Generate a SQL query to recalculate score of the given activity
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

        // make sure there is an entry for all
        self::createMissingActivityCustomData();

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
        $tmp_table = CRM_Utils_SQL_TempTable::build()
            ->setMemory(true)
            ->setAutodrop(false)
            ->createWithQuery("
            SELECT activity.id AS activity_id, {$rating_calculation_expression} AS rating
            FROM {$activity_data_table} activity_rating
            LEFT JOIN civicrm_activity activity ON activity_rating.entity_id = activity.id
            WHERE activity_rating.entity_id {$activity_id_selector}
              AND activity.status_id = {$activity_status_id}
              AND activity.activity_type_id = {$activity_type_id}
              ");
        CRM_Core_DAO::reenableFullGroupByMode();

        // add index...
        $tmp_table_name = $tmp_table->getName();
        CRM_Core_DAO::executeQuery("ALTER TABLE {$tmp_table_name} ADD INDEX activity_id(activity_id);");

        // ... and return the value update query
        return "
            UPDATE {$activity_data_table} activity_rating
            INNER JOIN {$tmp_table_name} new_values ON new_values.activity_id = activity_rating.entity_id
            SET activity_rating.{$target_field['column_name']} = new_values.rating;";
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
        // "Aktivitätsart-Koeffizient"
        [$field_group, $field_name] = explode('.', self::ACTIVITY_KIND);
        $kind_coefficient_field = CRM_Rating_CustomData::getCustomField($field_group, $field_name);
        $kind_coefficient = self::createSqlMappingExpression(
            "{$activity_rating_table}.{$kind_coefficient_field['column_name']}",
            self::ACTIVITY_KIND_MAPPING
        );

        // "Gewichtungskoeffizient"
        [$field_group, $field_name] = explode('.', self::ACTIVITY_WEIGHT);
        $weight_coefficient_field = CRM_Rating_CustomData::getCustomField($field_group, $field_name);
        $weight_coefficient = self::createSqlMappingExpression(
            "{$activity_rating_table}.{$weight_coefficient_field['column_name']}",
            self::ACTIVITY_WEIGHT_MAPPING
        );

        // "Alterskoeffizient"
        $age_coefficient = "0.75 / (POWER(((DATEDIFF(NOW(), {$activity_table}.activity_date_time)/365.0)/2.9), 4) + 1.0) + 0.25";

        // "Gewichtete Note"
        [$field_group, $field_name] = explode('.', self::ACTIVITY_SCORE);
        $score_coefficient_field = CRM_Rating_CustomData::getCustomField($field_group, $field_name);
        $score_coefficient = self::createSqlMappingExpression(
            "{$activity_rating_table}.{$score_coefficient_field['column_name']}",
            self::ACTIVITY_SCORE_MAPPING
        );

        return "( ({$kind_coefficient}) * ({$weight_coefficient}) * ({$score_coefficient}) * ({$age_coefficient}) )";
    }



    /***********************************************
     **               CONTACT-LEVEL               **
     ***********************************************/

    /**
     * Generate a SQL query to recalculate score of the given activity
     *
     * @param array|string $contact_ids
     *   activity IDs to update
     *
     * @return string
     *   generated query
     *
     * @throws Exception
     *   if something's wrong with the expected data structure
     */
    public static function runContactAggregationUpdateQuery($contact_ids = 'all', $contact_type = 'Individual')
    {
        // extract contact ID restrictions
        if ($contact_ids != 'all') {
            if (!is_array($contact_ids)) {
                $contact_ids = [$contact_ids];
            }
            $contact_ids = array_map('intval', $contact_ids);
            $contact_id_selector = "contact.id IN(" . implode(',', $contact_ids) . ')';
        } else {
            $contact_id_selector = 'contact.is_deleted = 0';
        }

        // generate temp table with the values
        $activity_data_table = CRM_Rating_CustomData::getGroupTable(self::ACTIVITY_GROUP);
        $activity_status_id = (int) CRM_Rating_Algorithm::getRatingActivityStatusPublished();
        $activity_type_id = (int) CRM_Rating_Algorithm::getRatingActivityTypeID();
        $activity_score_field = CRM_Rating_CustomData::getCustomField(
            self::ACTIVITY_GROUP,
            self::getFieldName(self::ACTIVITY_RATING_WEIGHTED)
        );
        $CATEGORY_SUMIFS = '';
        foreach (self::CONTACT_FIELD_TO_ACTIVITY_CATEGORIES_MAPPING as $column_name => $categories) {
            $CATEGORY_SUMIFS .= "SUM(IF(category IN({$categories}), {$activity_score_field['column_name']}, 0.0)) AS {$column_name},\n                ";
        }
        $calculation_query = "
            SELECT 
                contact.id                                                AS contact_id,
                {$CATEGORY_SUMIFS} 
                SUM(activity_data.{$activity_score_field['column_name']}) AS overall_rating 
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
              AND contact.contact_type = '{$contact_type}'
              AND activity_data.{$activity_score_field['column_name']} IS NOT NULL
            GROUP BY contact.id
            ;";

        // create a temp table with the results, because we'd run into a "Can't update table in stored function/trigger because it is already used by statement which invoked this stored function/trigger"
        CRM_Core_DAO::disableFullGroupByMode();
        $tmp_table = CRM_Utils_SQL_TempTable::build()
            ->setMemory(true)
            ->setAutodrop(false)
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

        CRM_Core_DAO::executeQuery("
            UPDATE {$contact_data_table} contact_rating
            INNER JOIN {$tmp_table_name} new_values ON new_values.contact_id = contact_rating.entity_id    
            SET contact_rating.overall_rating = new_values.overall_rating
                {$INDIVIDUAL_FIELDS_SQL}
        ;");
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
     * @return string
     *   generated expression
     */
    protected static function createSqlMappingExpression($value_source, $mapping, $from_type = 'int', $to_type = 'float')
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
     * This query will create missing entries in the activity custom data,
     *  so later queries can rely on them being there
     *
     * @param boolean $force
     *   if false the query will only be executed once per process
     *
     * @return void
     */
    public static function createMissingContactCustomData($contact_id_term)
    {
        static $has_been_run = false;
        CRM_Core_DAO::executeQuery("INSERT IGNORE INTO civicrm_value_contact_results (entity_id) VALUES ({$contact_id_term})");

        if ($force || !$has_been_run) {
            $activity_data_table = CRM_Rating_CustomData::getGroupTable(self::ACTIVITY_GROUP);
            $activity_type_id = self::getRatingActivityTypeID();

            // get all missing IDs
            $missing_id_query = CRM_Core_DAO::executeQuery("
                SELECT id AS missing_id
                FROM civicrm_activity
                WHERE activity_type_id = {$activity_type_id})
                  AND missing_id NOT IN (SELECT entity_id FROM {$activity_data_table});");
            $missing_entries = $missing_id_query->fetchAll();

            if ($missing_entries) {
                // fetch empty values
                $EMPTY_VALUES = [];
                $all_columns = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM {$activity_data_table}");
                while ($all_columns->fetch()) {
                    if ($all_columns->Field == 'entity_id') {
                        $EMPTY_VALUES[] = 'id AS ' . $all_columns->Field;
                    } else {
                        $EMPTY_VALUES[] = 'NULL AS ' . $all_columns->Field;
                    }
                }
                $ALL_FIELDS_INIT_VALUES = implode(', ', $EMPTY_VALUES);

                // generate all entries
                foreach ($missing_entries as $missing_entry) {
                    CRM_Core_DAO::executeQuery(
                        "INSERT IGNORE INTO {$activity_data_table} (
                            SELECT {$ALL_FIELDS_INIT_VALUES}
                            FROM civicrm_activity 
                            WHERE activity_type_id = {$activity_type_id});"
                    );
                }
            }
        }
    }
}
