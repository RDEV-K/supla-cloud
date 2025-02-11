<?php
/*
 Copyright (C) AC SOFTWARE SP. Z O.O.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace SuplaBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use SuplaBundle\Entity\EntityUtils;

class AccessIDTest extends TestCase {
    /** @dataProvider validActiveHours */
    public function testSetActiveHoursToHours($validActiveHours) {
        $accessId = new \SuplaBundle\Entity\Main\AccessID();
        $accessId->setActiveHours($validActiveHours);
        $this->assertEquals($validActiveHours, $accessId->getActiveHours());
    }

    public static function validActiveHours() {
        return [
            [[1 => [22], 6 => [23]]],
            [[1 => [22], 6 => [23, 0, 4]]],
        ];
    }

    public function testDatabaseRepresentation() {
        $accessId = new \SuplaBundle\Entity\Main\AccessID();
        $accessId->setActiveHours([1 => [22], 6 => [23, 0, 4]]);
        $databaseRepresentation = EntityUtils::getField($accessId, 'activeHours');
        $this->assertEquals(',122,623,60,64,', $databaseRepresentation);
    }

    public function testSetActiveHoursToNull() {
        $accessId = new \SuplaBundle\Entity\Main\AccessID();
        $accessId->setActiveHours(null);
        $this->assertNull($accessId->getActiveHours());
    }
}
