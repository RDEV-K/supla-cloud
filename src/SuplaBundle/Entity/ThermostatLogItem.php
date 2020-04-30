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

namespace SuplaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="supla_thermostat_log")
 */
class ThermostatLogItem {
    /**
     * @ORM\Id
     * @ORM\Column(name="channel_id", type="integer")
     */
    private $channel_id;

    /**
     * @ORM\Id
     * @ORM\Column(name="date", type="utcdatetime")
     */
    private $date;

    /**
     * @ORM\Column(name="`on`", type="boolean", nullable=false)
     */
    private $on = false;

    /**
     * @ORM\Column(name="measured_temperature", type="decimal", precision=5, scale=2)
     */
    private $measuredTemperature;

    /**
     * @ORM\Column(name="preset_temperature", type="decimal", precision=5, scale=2)
     */
    private $presetTemperature;

    public function __construct() {
    }

    public function getId() {
        return $this->id;
    }

    public function getChannelId() {
        return $this->channel_id;
    }

    public function getDate() {
        return $this->date;
    }

    public function getOn() {
        return $this->on;
    }

    public function getMeasuredTemperature() {
        return $this->measuredTemperature;
    }

    public function getPresetTemperature() {
        return $this->presetTemperature;
    }
}
