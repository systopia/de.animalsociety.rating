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
class CRM_Rating_Upgrader extends CRM_Rating_Upgrader_Base {

  /**
   * Example: Run an external SQL script when the module is installed.
   */
  public function install() {
    // install option groups and custom groups
    $customData = new CRM_Rating_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_activity_category.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_kind.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_score.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_species.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_subcategory.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_type.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_vote.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_weight.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_contact_importance.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_political_activity_additional_fields.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contact_results.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contact_help_fields.json'));
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  // public function postInstall() {
  //  $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
  //    'return' => array("id"),
  //    'name' => "customFieldCreatedViaManagedHook",
  //  ));
  //  civicrm_api3('Setting', 'create', array(
  //    'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
  //  ));
  // }



  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
   // public function upgrade_0001()
   // {
   //     $this->ctx->log->info('Updating data structures');
   //     $customData = new CRM_Rating_CustomData(E::LONG_NAME);
   //     $customData->syncOptionGroup(E::path('resources/option_group_activity_type.json'));
   //     return true;
   // }

}
