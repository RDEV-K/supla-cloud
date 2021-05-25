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

namespace SuplaBundle\Model\Schedule\SchedulePlanners;

use Assert\Assertion;
use Cron\CronExpression;
use DateTime;
use SuplaBundle\Entity\Schedule;
use SuplaBundle\Entity\ScheduledExecution;
use SuplaBundle\Enums\ScheduleMode;

class OnceSchedulePlanner implements SchedulePlanner {
    public function calculateNextScheduleExecution(Schedule $schedule, DateTime $currentDate): ScheduledExecution {
        $parts = explode(' ', $schedule->getTimeExpression());
        Assertion::count($parts, 6);
        $year = array_pop($parts);
        $withoutAYear = implode(' ', $parts);
        if ($currentDate->format('Y') < $year) {
            $currentDate = new DateTime(($year - 1) . '-12-31 23:59:59', $currentDate->getTimezone());
        }
        $cron = new CronExpression($withoutAYear);
        $nextRunDate = $cron->getNextRunDate($currentDate);
        Assertion::eq($year, $nextRunDate->format('Y'), 'Impossible cron expression.');
        return new ScheduledExecution($schedule, $nextRunDate);
    }

    public function canCalculateFor(Schedule $schedule): bool {
        return $schedule->getMode()->getValue() === ScheduleMode::ONCE && count(explode(' ', $schedule->getTimeExpression())) === 6;
    }

    public function validate(Schedule $schedule) {
        $parts = explode(' ', $schedule->getTimeExpression());
        Assertion::count($parts, 6);
        $year = array_pop($parts);
        $withoutAYear = implode(' ', $parts);
        $valid = CronExpression::isValidExpression($withoutAYear);
        Assertion::true($valid, 'Invalid CRON expression.');
        Assertion::numeric($year);
        Assertion::between($year, date('Y'), date('Y') + 100, 'Invalid year.');
    }
}
