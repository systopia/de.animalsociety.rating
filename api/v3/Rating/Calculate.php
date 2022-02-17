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
 * Rating.Calculate API can trigger the re-calculation of the rating value
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_rating_calculate_spec(&$spec)
{
    $spec['entity_type'] = [
        'name' => 'entity_type',
        'api.required' => 1,
        'title' => E::ts('Entity Type'),
        'description' => E::ts(
            'Type of the entity that the rating should be updated for. Options are activity, individual, organisation'
        ),
    ];
    $spec['entity_ids'] = [
        'name' => 'entity_ids',
        'api.required' => 1,
        'title' => E::ts('Entity IDs'),
        'description' => E::ts(
            "ID or list of IDs of the entities that the rating should be updated of. Alternatively, you can set pass 'all' to update *all* those entities."
        ),
    ];
    $spec['source_update_level'] = [
        'name' => 'source_update_level',
        'api.default' => 0,
        'title' => E::ts('Update Sources (level)'),
        'description' => E::ts("First update the data structures needed the given entity, and the recursive level of that (max 2)"),
    ];
    $spec['propagation_level'] = [
        'name' => 'propagation_level',
        'api.default' => 0,
        'title' => E::ts('Propagate Updates (level)'),
        'description' => E::ts("Also update the data structures affected by this change, and the propagation level (max 2)"),
    ];
}

/**
 * Rating.Calculate API can trigger the re-calculation of the rating value
 *
 * @param array $params
 *   mainly contains entity_type and entity_ids
 *
 * @return array API result descriptor
 *
 * @throws \CiviCRM_API3_Exception
 * @see civicrm_api3_create_error
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_rating_calculate($params)
{
    // extract entity IDs as array of integers:
    // first: make sure it's an array
    $entity_ids = $params['entity_ids'];
    if (!is_array($entity_ids)) {
        $entity_ids = trim(strtolower($params['entity_ids']));
    }
    if ($entity_ids != 'all') {
        if (!is_array($entity_ids)) {
            $entity_ids = explode(',', $entity_ids);
        }
        // then make sure it's all integers
        $entity_ids = array_map('intval', $entity_ids);
    }

    // check if we have to do anything at all
    if (empty($entity_ids)) {
        return civicrm_api3_create_success();
    }

    // prep the source_update und propagation levels
    $source_update_level = (int) $params['source_update_level'] ?? 0;
    $propagation_level = (int) $params['propagation_level'] ?? 0;

    // handle the three different types
    switch (strtolower($params['entity_type'])) {
        case 'activity':
            // run the update
            CRM_Rating_Algorithm::updateActivities($entity_ids);

            // do the propagation
            if ($propagation_level > 0) {
                $contact_ids = CRM_Rating_SqlQueries::getContactIdsForActivities($entity_ids);
                civicrm_api3('Rating', 'calculate', [
                    'entity_type' => 'individual',
                    'entity_ids' => $contact_ids,
                    'source_update_level' => 0,
                    'propagation_level' => $propagation_level - 1,
                ]);
            }

            // we're done (ACTIVITIES)
            break;

        default:
        case 'individual':
        case 'contact':
            // update the sources
            if ($source_update_level > 0) {
                $activity_ids = CRM_Rating_SqlQueries::getActivityIdsForContacts($entity_ids);
                if (!empty($activity_ids)) {
                    civicrm_api3('Rating', 'calculate', [
                        'entity_type' => 'activity',
                        'entity_ids' => $activity_ids,
                        'source_update_level' => 0,
                        'propagation_level' => 0,
                    ]);
                }
            }

            // run the update
            CRM_Rating_Algorithm::updateIndividuals($entity_ids);

            // do the propagation
            if ($propagation_level > 0) {
                $organisation_id = CRM_Rating_SqlQueries::getOrganisationIdsForContacts($entity_ids);
                if (!empty($organisation_id)) {
                    civicrm_api3('Rating', 'calculate', [
                        'entity_type' => 'organisation',
                        'entity_ids' => $organisation_id,
                        'source_update_level' => 0,
                        'propagation_level' => 0,
                    ]);
                }
            }
            break;
            // we're done (CONTACTS)

        case 'organisation':
        case 'organization':
            // update the sources
            if ($source_update_level > 0) {
                $contact_ids = CRM_Rating_SqlQueries::getContactIdsForOrganisations($entity_ids);
                if (!empty($contact_ids)) {
                    civicrm_api3('Rating', 'calculate', [
                        'entity_type' => 'individual',
                        'entity_ids' => $contact_ids,
                        'source_update_level' => $source_update_level - 1,
                        'propagation_level' => 0,
                    ]);
                }
                $activity_ids = CRM_Rating_SqlQueries::getActivityIdsForContacts($entity_ids);
                if (!empty($activity_ids)) {
                    civicrm_api3('Rating', 'calculate', [
                        'entity_type' => 'activity',
                        'entity_ids' => $activity_ids,
                        'source_update_level' => 0,
                        'propagation_level' => 0,
                    ]);
                }
            }

            // run the organisation update
            CRM_Rating_Algorithm::updateOrganisations($entity_ids);
            break;
    }
    return civicrm_api3_create_success();
}