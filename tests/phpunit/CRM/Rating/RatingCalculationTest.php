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
class CRM_Rating_RatingCalculationTest extends CRM_Rating_TestBase
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
    public function testRatingCalculationSimple()
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

        // calculate score and reload
        $this->refreshRating($activity_1, 'Activity');
        $activity_1 = $this->reloadActivity($activity_1);
        $category_rating = $activity_1[CRM_Rating_Base::ACTIVITY_RATING_WEIGHTED];

        // should be weight * age * type * score
        $weighted_score = 1.0 * 1.0 * 2.0 * 7.0;
        $this->assertEquals($weighted_score, $category_rating, "Calculated livestock activity score differs from expected value.", 1.5);

        // now calculate the contact and see if the ballast takes effect
        $this->refreshRating($contact['id'], 'Contact');
        $new_category_rating = $this->getRating($contact['id'], CRM_Rating_Base::LIVESTOCK_RATING);

        // should be (value + ballast) / (weight + ballast weight)
        $expected_value = (14.0 + CRM_Rating_Algorithm::BALLAST_VALUE) / (2.0 + CRM_Rating_Algorithm::BALLAST_WEIGHT);
        $this->assertEqualsWithPrecision($expected_value, $new_category_rating, "Calculated livestock rating differs from expected value.", self::EQUALITY_PRECISION_95);
    }

    /**
     * The scenario described by Jonas Marx
     * @see https://projekte.systopia.de/issues/17720
     */
    public function testRatingCalculationByCategory()
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
        $this->refreshRating($activity_1['id'], 'Activity');
        $activity_1 = $this->reloadActivity($activity_1);
        $this->assertEquals(14.0, $activity_1[CRM_Rating_Base::ACTIVITY_RATING_WEIGHTED], "The weight calculation seems wrong.", self::DOUBLE_PRECISION_LOW);

        // create activity_2
        $activity_2 = $this->createPoliticalActivity(
            $contact['id'],
            [
                'activity_date_time' => date('YmdHis', strtotime("-1 year")), // 1 year ago
                CRM_Rating_Base::ACTIVITY_CATEGORY => 1, // livestock
                CRM_Rating_Base::ACTIVITY_KIND => 10,    // vote on law
                CRM_Rating_Base::ACTIVITY_SCORE => 10,   // good
                CRM_Rating_Base::ACTIVITY_WEIGHT => 15,  // 1.5
            ]
        );
        $this->assertNotEmpty($activity_2, "Political Activity not created");
        $this->refreshRating($activity_2['id'], 'Activity');
        $activity_2 = $this->reloadActivity($activity_2);
        $this->assertEquals(148.43, $activity_2[CRM_Rating_Base::ACTIVITY_RATING_WEIGHTED], "The weight calculation seems wrong.", self::DOUBLE_PRECISION_LOW);

        // ballast => now IMPLICIT
//        $activity_3 = $this->createPoliticalActivity(
//            $contact['id'],
//            [
//                'activity_date_time' => date('YmdHis', strtotime("now")), // now
//                CRM_Rating_Base::ACTIVITY_CATEGORY => 1, // livestock
//                CRM_Rating_Base::ACTIVITY_KIND => 3,     // ballast?
//                CRM_Rating_Base::ACTIVITY_SCORE => 5,    // average
//                CRM_Rating_Base::ACTIVITY_WEIGHT => 10,  // 1.0
//            ]
//        );
//        $this->assertNotEmpty($activity_3, "Political Activity not created");
//        $this->refreshRating($activity_3['id'], 'Activity');
//        $activity_3 = $this->reloadActivity($activity_3);
//        $this->assertEquals(15.0, $activity_3[CRM_Rating_Base::ACTIVITY_RATING_WEIGHTED], "The weight calculation seems wrong.", self::DOUBLE_PRECISION_LOW);

        // calculate contact ratings
        $this->refreshRating($contact['id'], 'Contact', 1);
        $category_rating = $this->getRating($contact['id'], CRM_Rating_Base::LIVESTOCK_RATING);
        $this->assertEquals(8.942065491, $category_rating, "Calculated livestock rating differs from expected rating.", self::DOUBLE_PRECISION_LOW);
    }


    /**
     * Helper function to add an explicit ballast activity.
     *  This is for testing only and should later be removed in favour of a 'virtual' ballast that's part of the algorithm
     *
     * @param $contact_id
     * @param $category_value
     *
     * @return void
     */
    protected function addBallastActivity($contact_id, $category_value)
    {
        $ballast_activity = $this->createPoliticalActivity(
            $contact_id,
            [
                'subject' => 'BALLAST',
                'activity_date_time' => date('YmdHis', strtotime("now")), // now
                CRM_Rating_Base::ACTIVITY_CATEGORY => $category_value,
                CRM_Rating_Base::ACTIVITY_KIND => 3,    // ballast?
                CRM_Rating_Base::ACTIVITY_SCORE => 5,   // average
                CRM_Rating_Base::ACTIVITY_WEIGHT => 20,  // 2.0
            ]
        );
        $this->assertNotEmpty($ballast_activity, "Political Activity not created");
    }
}
