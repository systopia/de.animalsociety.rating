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
 * Rating.Calculate API specification to re-calculate a contact's values
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_rating_calculate_spec(&$spec)
{
    $spec['contact_id'] = [
        'name' => 'contact_id',
        'api.required' => 1,
        'title' => E::ts('Contact ID'),
        'description' => E::ts('ID of the contact to update the status of'),
    ];
}

/**
 * Rating.Calculate API
 *
 *
 * @param array $params
 *   mainly contains the contact_id
 *
 * @return array API result descriptor
 *
 * @throws \CiviCRM_API3_Exception
 * @see civicrm_api3_create_error
 * @see civicrm_api3_create_success
 *
 * @deprecated use rating.update_spec
 */
function civicrm_api3_rating_calculate($params)
{
    CRM_Rating_Algorithm::updateContacts([$params['contact_id']]);
    return civicrm_api3_create_success();
}






/**
 * Rating.Update API specification to re-calculate a contact's values
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_rating_update_spec(&$spec)
{
    $spec['entity_type'] = [
        'name' => 'entity_type',
        'api.required' => 1,
        'title' => E::ts('Entity Type'),
        'description' => E::ts('Type of the entity that the rating should be updated for. Options are activity, contact, organisation'),
    ];
    $spec['entity_ids'] = [
        'name' => 'entity_ids',
        'api.required' => 1,
        'title' => E::ts('Entity IDs'),
        'description' => E::ts('ID or list of IDs of the entities that the rating should be updated of'),
    ];
    /** disabled for NOW:
    $spec['update_sources'] = [
        'name' => 'update_sources',
        'api.required' => 0,
        'title' => E::ts('Update sources'),
        'description' => E::ts('Update all entities this entity\'s rating depends on'),
    ];
    $spec['update_dependencies'] = [
        'name' => 'update_dependencies',
        'api.required' => 0,
        'title' => E::ts('Update dependencies'),
        'description' => E::ts('Update all entities this rating might have an influence on'),
    ];*/


    /**
     * Rating.Update API
     *
     *
     * @param array $params
     *   mainly contains the contact_id
     *
     * @return array API result descriptor
     *
     * @throws \CiviCRM_API3_Exception
     * @see civicrm_api3_create_error
     * @see civicrm_api3_create_success
     *
     * @deprecated use rating.update_spec
     */
    function civicrm_api3_rating_update($params)
    {
        // extract entity IDs as array of integers:
        // first: make sure it's an array
        $entity_ids = $params['entity_ids'];
        if (!is_array($entity_ids)) {
            $entity_ids = explode(',', $entity_ids);
        }
        // then make sure it's all integers
        $entity_ids = array_map('intval', $entity_ids);

        switch (strtolower($params['entity_type'])) {
            case 'contact':
                CRM_Rating_Algorithm::updateContacts($params['contact_id']);
                break;

            case 'activity':
                CRM_Rating_Algorithm::updateActivities($params['contact_id']);
                break;

            case 'organisation':
                CRM_Rating_Algorithm::updateOrganisations($params['contact_id']);
                break;

        }
        return civicrm_api3_create_success();
    }
}
