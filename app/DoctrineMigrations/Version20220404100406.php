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

use SuplaBundle\Enums\ChannelFunction;

/**
 * AT captions.
 */
class Version20220404100406 extends NoWayBackMigration {
    public function migrate() {
        $atFunction = ChannelFunction::ACTION_TRIGGER;
        $this->addSql(
            <<<SQL
            UPDATE supla_dev_channel c 
            SET caption = CONCAT(
                (SELECT COALESCE(caption, '') FROM (SELECT * FROM supla_dev_channel) AS t WHERE id=c.param1),
                ' AT'
            ) 
            WHERE func = $atFunction AND param1 > 0 AND (caption IS NULL OR caption = '');
SQL
        );
    }
}
