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
class CRM_Rating_BasicTest extends CRM_Rating_TestBase
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
     * Just create a contact, add an activity, recalculate,
     */
    public function testCreateContactAndActivity()
    {
        // create a contact
        $contact = $this->createContact();
        $this->assertNotEmpty($contact, "Contact not created");
        $contact_rating = $this->getOverallContactRating($contact['id']);
        $this->assertEmpty($contact_rating, "Contact rating should be zero/empty");


        // create an activity
        $activity = $this->createPoliticalActivity($contact['id']);
        $this->assertNotEmpty($activity, "Political Activity not created");

        // recalculate
        $this->refreshRating($contact['id'], 'Contact', 1);

        // verify that the rating has been propagated
        $contact_rating = $this->getOverallContactRating($contact['id']);
        $this->assertNotEmpty($contact_rating, "Contact rating should not be zero/empty any more");
    }
}
