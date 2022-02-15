<?php
/*-------------------------------------------------------+
| AnimalSociety Rating Extension                         |
| Copyright (C) 2021 SYSTOPIA                            |
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

        // gather some values
        $activity_status_id = (int) CRM_Rating_Algorithm::getRatingActivityStatusPublished();
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
        $tmp_table = CRM_Utils_SQL_TempTable::build()
            ->setMemory(true)
            ->setAutodrop(false)
            ->createWithQuery("
            SELECT activity.id AS activity_id, {$rating_calculation_expression} AS rating
            FROM {$activity_data_table} activity_rating
            LEFT JOIN civicrm_activity activity ON activity_rating.entity_id = activity.id
            WHERE activity_rating.entity_id {$activity_id_selector}
              AND activity.status_id = {$activity_status_id}");

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
    public static function getActivityScoreExpression($activity_rating_table, $activity_table)
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
    public static function createSqlMappingExpression($value_source, $mapping, $from_type = 'int', $to_type = 'float')
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
     *
     * @deprecated not needed. I think....
     */
    public static function createMissingActivityCustomData($force = false)
    {
        static $has_been_run = false;
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
