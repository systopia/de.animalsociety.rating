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
 * Collection of upgrade steps.
 */
class CRM_Rating_Upgrader extends CRM_Rating_Upgrader_Base
{

    /**
     * Example: Run an external SQL script when the module is installed.
     */
    public function install()
    {
        // install option groups and custom groups
        $customData = new CRM_Rating_CustomData(E::LONG_NAME);
        $customData->syncOptionGroup(E::path('resources/option_group_activity_category.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_kind.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_score.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_species.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_status.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_subcategory.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_type.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_vote.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_weight.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_contact_importance.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_political_activity_additional_fields.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_contact_results.json'));
//        $customData->syncCustomGroup(E::path('resources/custom_group_contact_help_fields.json'));
    }

    /**
     * Run first upgrader: redo all fields for l10n
     *
     * @return TRUE on success
     * @throws Exception
     */
    public function upgrade_0001()
    {
        $this->ctx->log->info('Translating data structures');
        $customData = new CRM_Rating_CustomData(E::LONG_NAME);
        $customData->syncOptionGroup(E::path('resources/option_group_activity_category.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_kind.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_score.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_species.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_status.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_subcategory.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_type.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_vote.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_activity_weight.json'));
        $customData->syncOptionGroup(E::path('resources/option_group_contact_importance.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_political_activity_additional_fields.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_contact_results.json'));
        return true;
    }
}
