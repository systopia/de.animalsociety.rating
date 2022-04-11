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

use Civi\Test\Api3TestTrait;
use CRM_Rating_ExtensionUtil as E;

/**
 * First simple tests about the rating algorithms
 *
 * @group headless
 */
class CRM_Rating_PerformanceTest extends CRM_Rating_TestBase
{
    use Api3TestTrait {
        callAPISuccess as protected traitCallAPISuccess;
    }

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * Create contacts with activities and then run the algorithm to see about the performance
     */
    public function testFewContactsManyActivities()
    {
        //$this->runPerformanceTest(10, 100);
    }

    /**
     * Create contacts with activities and then run the algorithm to see about the performance
     */
    public function testManyContactsFewActivities()
    {
        //->runPerformanceTest(100, 10);
    }

    /**
     * Just run a test with the given numbers of contacts and activities
     */
    public function runPerformanceTest($contact_count, $activities_per_contact_count)
    {
        // create contacts
        for ($contact_idx = 0; $contact_idx < $contact_count; $contact_idx++) {
            $contact = $this->createContact();
//            if ((($contact_idx+1) % 10) == 0) {
//                Civi::log()->debug("Created another {$contact_idx} contacts.");
//            }

            // and create activities for each
            for ($activity_idx = 0; $activity_idx < $activities_per_contact_count; $activity_idx++) {
                $activity = $this->createPoliticalActivity($contact['id']);
//                if ((($activity_idx+1) % 10) == 0) {
//                    Civi::log()->debug("Created another {$activity_idx} activities.");
//                }
            }
        }

        // then run the update on all
        Civi::log()->debug("Starting refresh on {$contact_count} contacts and {$activities_per_contact_count} activities.");
        $this->refreshRating('all', 'Contact', 1);
    }
}
