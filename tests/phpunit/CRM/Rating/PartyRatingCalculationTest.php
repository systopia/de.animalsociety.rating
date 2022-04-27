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
 * Tests trying to verify the rate calculation
 *
 * @group headless
 */
class CRM_Rating_PartyRatingCalculationTest extends CRM_Rating_TestBase
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
    public function testBasicRatingCalculation()
    {
        // create a party
        $party = $this->createParty();
        $this->assertNotEmpty($party, "Party not created");

        // create a contact
        $contact = $this->createContact();
        $this->assertNotEmpty($contact, "Contact not created");
        $this->joinParty($contact['id'], $party['id']);

        // create activity_1 with contact
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

        // calculate score and reload
        $this->refreshRating($activity_1['id'], 'Activity', 0, 2);
        $party2 = $this->reloadContact($party);


        // create activity_2 with organisation (political party)
        $activity_2 = $this->createPoliticalActivity(
            $party['id'],
            [
                'activity_date_time' => date('YmdHis', strtotime("now")), // now
                CRM_Rating_Base::ACTIVITY_CATEGORY => 1, // livestock
                CRM_Rating_Base::ACTIVITY_KIND => 2,     // speech
                CRM_Rating_Base::ACTIVITY_SCORE => 7,    // rather good
                CRM_Rating_Base::ACTIVITY_WEIGHT => 10,  // 1.0
            ]
        );
        $this->assertNotEmpty($activity_2, "Political Activity not created");
        $party3 = $this->reloadContact($party);

        $this->refreshRating([$activity_1['id'], $activity_2['id']], 'Activity', 0, 2);
        $party4 = $this->reloadContact($party);
    }

    /**
     * Simple calculation
     */
    public function testScenario1()
    {
        // create a party
        $party = $this->createParty();
        $this->assertNotEmpty($party, "Party not created");

        // create a 'normal' party member
        $normal_party_member = $this->createContact();
        $this->assertNotEmpty($normal_party_member, "Contact not created");
        $this->joinParty($normal_party_member['id'], $party['id']);
        $normal_party_member_activity = $this->createPoliticalActivity(
            $normal_party_member['id'],
            [
                'activity_date_time' => date('YmdHis', strtotime("now")), // now
                CRM_Rating_Base::ACTIVITY_CATEGORY => 1, // livestock
                CRM_Rating_Base::ACTIVITY_KIND => 2,     // speech
                CRM_Rating_Base::ACTIVITY_SCORE => 7,    // RATHER GOOD
                CRM_Rating_Base::ACTIVITY_WEIGHT => 10,  // 1.0
            ]
        );
        $this->assertNotEmpty($normal_party_member_activity, "Political Activity not created");


        // create a 'high profile' party member
        $important_party_member = $this->createContact(
            [CRM_Rating_Base::CONTACT_IMPORTANCE => 1]
        );
        $this->assertNotEmpty($important_party_member, "Contact not created");
        $this->joinParty($important_party_member['id'], $party['id']);
        $important_party_member_activity = $this->createPoliticalActivity(
            $important_party_member['id'],
            [
                'activity_date_time' => date('YmdHis', strtotime("now")), // now
                CRM_Rating_Base::ACTIVITY_CATEGORY => 1, // livestock
                CRM_Rating_Base::ACTIVITY_KIND => 2,     // speech
                CRM_Rating_Base::ACTIVITY_SCORE => 10,    // GOOD
                CRM_Rating_Base::ACTIVITY_WEIGHT => 10,  // 1.0
            ]
        );
        $this->assertNotEmpty($important_party_member_activity, "Political Activity not created");

        // calculate score and reload
        $this->refreshRating([$normal_party_member['id'], $important_party_member['id']], 'Contact', 1, 1);
        $important_party_member = $this->reloadContact($important_party_member);
        $this->assertGreaterThan(5.0, (float) $important_party_member[CRM_Rating_Base::LIVESTOCK_RATING], "Rating should be above average");
        $normal_party_member = $this->reloadContact($normal_party_member);
        $this->assertGreaterThanOrEqual(5.0, (float) $normal_party_member[CRM_Rating_Base::LIVESTOCK_RATING], "Rating should be at least average");
        $party = $this->reloadContact($party);
        $this->assertGreaterThan(5.7, (float) $party[CRM_Rating_Base::LIVESTOCK_RATING], "Rating should be above average");
    }
}
