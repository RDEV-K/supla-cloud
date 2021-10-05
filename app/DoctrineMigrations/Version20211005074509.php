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

namespace Supla\Migrations;

/**
 * Limit actions per schedule.
 */
class Version20211005074509 extends NoWayBackMigration {
    public function migrate() {
        $this->addSql('ALTER TABLE supla_user ADD limit_actions_per_schedule INT DEFAULT 20 NOT NULL, CHANGE limit_aid limit_aid INT DEFAULT 10 NOT NULL, CHANGE limit_loc limit_loc INT DEFAULT 10 NOT NULL, CHANGE limit_iodev limit_iodev INT DEFAULT 100 NOT NULL, CHANGE limit_client limit_client INT DEFAULT 200 NOT NULL');
    }
}
