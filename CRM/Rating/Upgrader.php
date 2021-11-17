<?php
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
    $customData->syncOptionGroup(E::path('resources/option_group_activity_class.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_score.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_theme.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_weight.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_type.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_activity_factors.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contact_results.json'));

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
  // public function upgrade_0010(): bool {
  //   $this->ctx->log->info('Applying update 0010');
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
  //   CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
  //   return TRUE;
  // }

}
