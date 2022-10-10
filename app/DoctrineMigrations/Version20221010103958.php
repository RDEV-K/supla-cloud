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
 * Add supla_em_voltage_log.
 */
class Version20221010103958 extends NoWayBackMigration {
    public function migrate() {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE supla_em_voltage_log (channel_id INT NOT NULL, date DATETIME NOT NULL COMMENT \'(DC2Type:stringdatetime)\', phase_no TINYINT NOT NULL COMMENT \'(DC2Type:tinyint)\', count_total INT NOT NULL, count_above INT NOT NULL, count_below INT NOT NULL, total_sec INT NOT NULL, total_sec_above INT NOT NULL, total_sec_below INT NOT NULL, max_sec_above INT NOT NULL, max_sec_below INT NOT NULL, min_volate NUMERIC(7, 2) NOT NULL, max_voltage NUMERIC(7, 2) NOT NULL, avg_voltage NUMERIC(7, 2) NOT NULL, measurement_time_sec INT NOT NULL, PRIMARY KEY(channel_id, date, phase_no)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
