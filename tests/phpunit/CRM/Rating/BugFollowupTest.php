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
 * Tests to reproduce / verify fix of bugs found during development and production
 *
 * @group headless
 */
class CRM_Rating_BugFollowupTest extends CRM_Rating_TestBase
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
     * Simple calculation
     */
    public function testUpdateMultipleActivityRating()
    {
        // create a contact
        $contact = $this->createContact();
        $this->assertNotEmpty($contact, "Contact not created");

        // create activity_1
        $activity_1 = $this->createPoliticalActivity(
            $contact['id'],
            [
                'activity_date_time' => date('YmdHis', strtotime("now")), // now
                CRM_Rating_Base::ACTIVITY_CATEGORY => 1, // livestock
                CRM_Rating_Base::ACTIVITY_KIND => 2,     // speech
                CRM_Rating_Base::ACTIVITY_SCORE => 7,    // rather good
                CRM_Rating_Base::ACTIVITY_WEIGHT => 10,  // 1.0
            ]
        );
        $this->assertNotEmpty($activity_1, "Political Activity not created");
        $activity_1 = $this->reloadActivity($activity_1);
        $rating_weighted = $activity_1['political_activity_additional_fields.rating_weighted'] ?? null;
        $this->assertNull($rating_weighted, "This should be null at this point (after creation before update)");

        // now calculate rating and reload
        $this->refreshRating($activity_1['id'], 'Activity');
        $activity_1 = $this->reloadActivity($activity_1);
        $rating_weighted = $activity_1[CRM_Rating_Base::ACTIVITY_RATING_WEIGHTED] ?? null;
        $this->assertNotEmpty($rating_weighted, "This should be null at this point (after creation before update)");
    }
}
