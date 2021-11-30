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
 * Rating.Update API specification to re-calculate a contact's values
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
 */
function civicrm_api3_rating_calculate($params)
{
    CRM_Rating_Algorithm::updateContact($params['contact_id']);
    return civicrm_api3_create_success();
}
