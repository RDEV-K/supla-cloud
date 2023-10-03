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

namespace SuplaDeveloperBundle\DataFixtures\ORM;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use SuplaBundle\Entity\EntityUtils;
use SuplaBundle\Entity\Main\IODevice;
use SuplaBundle\Entity\Main\IODeviceChannel;
use SuplaBundle\Entity\Main\Location;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelFunctionBitsFlags;
use SuplaBundle\Enums\ChannelFunctionBitsFlist;
use SuplaBundle\Enums\ChannelType;
use SuplaBundle\Enums\IoDeviceFlags;
use SuplaBundle\Tests\AnyFieldSetter;

class DevicesFixture extends SuplaFixture {
    const ORDER = LocationsFixture::ORDER + 1;
    const NUMBER_OF_RANDOM_DEVICES = 15;

    const DEVICE_SONOFF = 'deviceSonoff';
    const DEVICE_FULL = 'deviceFull';
    const DEVICE_RGB = 'deviceRgb';
    const DEVICE_HVAC = 'deviceHvac';
    const DEVICE_SUPLER = 'deviceSupler';
    const DEVICE_EVERY_FUNCTION = 'ALL-IN-ONE MEGA DEVICE';
    const RANDOM_DEVICE_PREFIX = 'randomDevice';

    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var Generator */
    private $faker;

    public function setObjectManager(ObjectManager $m): self {
        $this->entityManager = $m;
        $this->faker = Factory::create('pl_PL');
        return $this;
    }

    public function load(ObjectManager $manager) {
        $this->setObjectManager($manager);
        $this->createDeviceSonoff($this->getReference(LocationsFixture::LOCATION_OUTSIDE));
        $this->createDeviceFull($this->getReference(LocationsFixture::LOCATION_GARAGE));
        $this->createDeviceRgb($this->getReference(LocationsFixture::LOCATION_BEDROOM));
        $this->createEveryFunctionDevice($this->getReference(LocationsFixture::LOCATION_OUTSIDE), self::DEVICE_EVERY_FUNCTION);
        $hvac = $this->createDeviceHvac($this->getReference(LocationsFixture::LOCATION_BEDROOM));
        $this->setReference(self::DEVICE_HVAC, $hvac);
        $device = $this->createEveryFunctionDevice($this->getReference(LocationsFixture::LOCATION_OUTSIDE), 'SECOND MEGA DEVICE');
        foreach ($this->faker->randomElements($device->getChannels(), 3) as $noFunctionChannel) {
            $noFunctionChannel->setFunction(ChannelFunction::NONE());
            $this->entityManager->persist($noFunctionChannel);
        }
        $this->createDeviceManyGates($this->getReference(LocationsFixture::LOCATION_OUTSIDE));
        $nonDeviceLocations = [null, $this->getReference(LocationsFixture::LOCATION_OUTSIDE), $this->getReference(LocationsFixture::LOCATION_BEDROOM)];
        for ($i = 0; $i < self::NUMBER_OF_RANDOM_DEVICES; $i++) {
            $name = strtoupper(implode('-', $this->faker->words($this->faker->numberBetween(1, 3))));
            $device = $this->createDeviceFull($this->getReference(LocationsFixture::LOCATION_GARAGE), $name);
            foreach ($device->getChannels() as $channel) {
                $channel->setLocation($nonDeviceLocations[rand(0, count($nonDeviceLocations) - 1)]);
                $manager->persist($channel);
            }
            $this->setReference(self::RANDOM_DEVICE_PREFIX . $i, $device);
        }
        $suplerDevice = $this->createEveryFunctionDevice($this->getReference(LocationsFixture::LOCATION_SUPLER), 'SUPLER MEGA DEVICE', '');
        $this->setReference(self::DEVICE_SUPLER, $suplerDevice);
        $manager->flush();
    }

    protected function createDeviceSonoff(Location $location): IODevice {
        $device = $this->createDevice('SONOFF-DS', $location, [
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH, ['funcList' => ChannelFunctionBitsFlist::LIGHTSWITCH | ChannelFunctionBitsFlist::POWERSWITCH]],
            [ChannelType::THERMOMETERDS18B20, ChannelFunction::THERMOMETER],
            [ChannelType::ACTION_TRIGGER, ChannelFunction::ACTION_TRIGGER],
        ], self::DEVICE_SONOFF);
        $at = $device->getChannels()[2];
        $at->setParam1($device->getChannels()[0]->getId());
        $this->entityManager->persist($at);
        return $device;
    }

    protected function createDeviceFull(Location $location, $name = 'UNI-MODULE'): IODevice {
        return $this->createDevice($name, $location, [
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH, ['funcList' => ChannelFunctionBitsFlist::LIGHTSWITCH | ChannelFunctionBitsFlist::POWERSWITCH]],
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEDOORLOCK, ['funcList' => ChannelFunctionBitsFlist::getAllFeaturesFlag()]],
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEGATE, ['funcList' => ChannelFunctionBitsFlist::getAllFeaturesFlag()]],
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEROLLERSHUTTER, ['funcList' => ChannelFunctionBitsFlist::CONTROLLINGTHEROLLERSHUTTER, 'flags' => ChannelFunctionBitsFlags::RECALIBRATE_ACTION_AVAILABLE]],
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATEWAY],
            [ChannelType::SENSORNC, ChannelFunction::OPENINGSENSOR_DOOR],
            [ChannelType::THERMOMETERDS18B20, ChannelFunction::THERMOMETER],
            [ChannelType::ACTION_TRIGGER, ChannelFunction::ACTION_TRIGGER],
            [ChannelType::BRIDGE, ChannelFunction::CONTROLLINGTHEROLLERSHUTTER, ['funcList' => ChannelFunctionBitsFlist::getAllFeaturesFlag(), 'flags' => ChannelFunctionBitsFlags::AUTO_CALIBRATION_AVAILABLE]],
            [ChannelType::IMPULSECOUNTER, ChannelFunction::IC_WATERMETER, ['flags' => ChannelFunctionBitsFlags::RESET_COUNTERS_ACTION_AVAILABLE]],
        ], self::DEVICE_FULL);
    }

    protected function createEveryFunctionDevice(
        Location $location,
        $name = 'ALL-IN-ONE MEGA DEVICE'
    ): IODevice {
        $functionableTypes = array_filter(ChannelType::values(), function (ChannelType $type) {
            return count(ChannelType::functions()[$type->getValue()] ?? []);
        });
        $channels = array_values(array_map(function (ChannelType $type) {
            return array_map(function (ChannelFunction $function) use ($type) {
                return [$type->getValue(), $function->getValue()];
            }, ChannelType::functions()[$type->getValue()]);
        }, $functionableTypes));
        $channels = call_user_func_array('array_merge', $channels);
        return $this->createDevice($name, $location, $channels, $name);
    }

    protected function createDeviceRgb(Location $location): IODevice {
        return $this->createDevice('RGB-801', $location, [
            [ChannelType::RGBLEDCONTROLLER, ChannelFunction::DIMMERANDRGBLIGHTING],
            [ChannelType::RGBLEDCONTROLLER, ChannelFunction::RGBLIGHTING],
        ], self::DEVICE_RGB);
    }

    private function createDeviceManyGates(Location $location) {
        $channels = [];
        for ($i = 0; $i < 10; $i++) {
            $channels[] = [
                ChannelType::RELAY,
                ChannelFunction::CONTROLLINGTHEGATE,
                ['funcList' => ChannelFunctionBitsFlist::getAllFeaturesFlag()],
            ];
        }
        return $this->createDevice('OH-MY-GATES. This device also has ridiculously long name!', $location, $channels, 'gatesDevice');
    }

    public function createDeviceHvac(Location $location) {
        $sampleQuarters1 = array_map(
            'intval',
            str_split(
                str_repeat(
                    str_repeat('0', 6 * 4) .
                    str_repeat('1', 2 * 4) .
                    str_repeat('3', 6 * 4) .
                    str_repeat('2', 2 * 4) .
                    str_repeat('1', 6 * 4) .
                    str_repeat('0', 2 * 4),
                    5
                ) . str_repeat(
                    str_repeat('0', 8 * 4 + 2) .
                    str_repeat('2', 12 * 4) .
                    str_repeat('4', 2 * 4) .
                    str_repeat('0', 4 + 2),
                    2
                )
            )
        );
        $sampleQuarters2 = array_map('intval', str_split(str_replace('4', '1', implode('', $sampleQuarters1))));
        $hvac = $this->createDevice('HVAC-Monster', $location, [
            [ChannelType::THERMOMETERDS18B20, ChannelFunction::THERMOMETER],
            [ChannelType::HUMIDITYANDTEMPSENSOR, ChannelFunction::HUMIDITYANDTEMPERATURE],
            [
                ChannelType::HVAC,
                ChannelFunction::HVAC_THERMOSTAT,
                [
                    'funcList' => ChannelFunctionBitsFlist::HVAC_THERMOSTAT | ChannelFunctionBitsFlist::HVAC_DOMESTIC_HOT_WATER,
                    'properties' => json_encode([
                        'availableAlgorithms' => ['ON_OFF_SETPOINT_MIDDLE', 'ON_OFF_SETPOINT_AT_MOST'],
                        'temperatures' => [
                            'roomMin' => 1000,
                            'roomMax' => 4000,
                            'auxMin' => 500,
                            'auxMax' => 5000,
                            'histeresisMin' => 100,
                            'histeresisMax' => 500,
                            'autoOffsetMin' => 100,
                            'autoOffsetMax' => 200,
                        ],
                    ]),
                    'userConfig' => json_encode([
                        'subfunction' => 'HEAT',
                        'mainThermometerChannelNo' => 2,
                        'auxThermometerChannelNo' => null,
                        'usedAlgorithm' => 'ON_OFF_SETPOINT_MIDDLE',
                        'temperatures' => [
                            'freezeProtection' => 1000,
                            'heatProtection' => 3300,
                            'histeresis' => 200,
                            'auxMinSetpoint' => 550,
                            'auxMaxSetpoint' => 4000,
                        ],
                        'weeklySchedule' => [
                            'programSettings' => [
                                '1' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 2400, 'setpointTemperatureCool' => 0],
                                '2' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 2100, 'setpointTemperatureCool' => 0],
                                '3' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 1800, 'setpointTemperatureCool' => 0],
                                '4' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 2800, 'setpointTemperatureCool' => 0],
                            ],
                            'quarters' => $sampleQuarters1,
                        ],
                        'altWeeklySchedule' => [
                            'programSettings' => [
                                '1' => ['mode' => 'COOL', 'setpointTemperatureHeat' => 0, 'setpointTemperatureCool' => 2400],
                                '2' => ['mode' => 'COOL', 'setpointTemperatureHeat' => 0, 'setpointTemperatureCool' => 2100],
                                '3' => ['mode' => 'COOL', 'setpointTemperatureHeat' => 0, 'setpointTemperatureCool' => 1800],
                                '4' => ['mode' => 'COOL', 'setpointTemperatureHeat' => 0, 'setpointTemperatureCool' => 2800],
                            ],
                            'quarters' => $sampleQuarters1,
                        ],
                    ]),
                ],
            ],
            [
                ChannelType::HVAC,
                ChannelFunction::HVAC_THERMOSTAT_AUTO,
                [
                    'funcList' => ChannelFunctionBitsFlist::HVAC_THERMOSTAT_AUTO,
                    'properties' => json_encode([
                        'availableAlgorithms' => ['ON_OFF_SETPOINT_MIDDLE'],
                        'temperatures' => [
                            'roomMin' => 1000,
                            'roomMax' => 4000,
                            'auxMin' => 500,
                            'auxMax' => 5000,
                            'histeresisMin' => 100,
                            'histeresisMax' => 500,
                            'autoOffsetMin' => 100,
                            'autoOffsetMax' => 200,
                        ],
                    ]),
                    'userConfig' => json_encode([
                        'mainThermometerChannelNo' => 3,
                        'auxThermometerChannelNo' => 1,
                        'auxThermometerType' => 'FLOOR',
                        'antiFreezeAndOverheatProtectionEnabled' => true,
                        'temperatureSetpointChangeSwitchesToManualMode' => true,
                        'usedAlgorithm' => 'ON_OFF_SETPOINT_MIDDLE',
                        'minOnTimeS' => 60,
                        'minOffTimeS' => 120,
                        'outputValueOnError' => 42,
                        'temperatures' => [
                            'freezeProtection' => 1000,
                            'eco' => 1800,
                            'comfort' => 2000,
                            'boost' => 2500,
                            'heatProtection' => 3300,
                            'histeresis' => 200,
                            'belowAlarm' => 1200,
                            'aboveAlarm' => 3600,
                            'auxMinSetpoint' => 1000,
                            'auxMaxSetpoint' => 2000,
                        ],
                        'weeklySchedule' => [
                            'programSettings' => [
                                '1' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 2100, 'setpointTemperatureCool' => 0],
                                '2' => ['mode' => 'COOL', 'setpointTemperatureHeat' => 0, 'setpointTemperatureCool' => 2400],
                                '3' => ['mode' => 'AUTO', 'setpointTemperatureHeat' => 1800, 'setpointTemperatureCool' => 2200],
                                '4' => ['mode' => 'NOT_SET', 'setpointTemperatureHeat' => 0, 'setpointTemperatureCool' => 0],
                            ],
                            'quarters' => $sampleQuarters2,
                        ],
                    ]),
                ],
            ],
            [
                ChannelType::HVAC,
                ChannelFunction::HVAC_DOMESTIC_HOT_WATER,
                [
                    'funcList' => ChannelFunctionBitsFlist::HVAC_THERMOSTAT_AUTO | ChannelFunctionBitsFlist::HVAC_DOMESTIC_HOT_WATER |
                        ChannelFunctionBitsFlist::HVAC_THERMOSTAT | ChannelFunctionBitsFlist::HVAC_THERMOSTAT_DIFFERENTIAL,
                    'properties' => json_encode([
                        'availableAlgorithms' => ['ON_OFF_SETPOINT_MIDDLE', 'ON_OFF_SETPOINT_AT_MOST'],
                        'temperatures' => [
                            'roomMin' => 1000,
                            'roomMax' => 4000,
                            'auxMin' => 500,
                            'auxMax' => 5000,
                            'histeresisMin' => 100,
                            'histeresisMax' => 500,
                            'autoOffsetMin' => 100,
                            'autoOffsetMax' => 200,
                        ],
                    ]),
                    'userConfig' => json_encode([
                        'mainThermometerChannelNo' => 4,
                        'auxThermometerChannelNo' => null,
                        'binarySensorChannelNo' => 5,
                        'usedAlgorithm' => 'ON_OFF_SETPOINT_AT_MOST',
                        'weeklySchedule' => [
                            'programSettings' => [
                                '1' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 2400, 'setpointTemperatureCool' => 0],
                                '2' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 2100, 'setpointTemperatureCool' => 0],
                                '3' => ['mode' => 'HEAT', 'setpointTemperatureHeat' => 1800, 'setpointTemperatureCool' => 0],
                                '4' => ['mode' => 'NOT_SET', 'setpointTemperatureHeat' => 2200, 'setpointTemperatureCool' => 0],
                            ],
                            'quarters' => $sampleQuarters2,
                        ],
                    ]),
                ],
            ],
            [ChannelType::SENSORNO, ChannelFunction::HOTELCARDSENSOR],
        ], '');
        AnyFieldSetter::set($hvac, [
            'userConfig' => json_encode([
                'statusLed' => 'OFF_WHEN_CONNECTED',
                'screenBrightness' => 13,
                'buttonVolume' => 14,
                'userInterfaceDisabled' => false,
                'automaticTimeSync' => false,
                'screenSaver' => ['mode' => 'TEMPERATURE', 'delay' => 30000],
            ]),
            'properties' => json_encode([
                'screenSaverModesAvailable' => [
                    'OFF', 'TEMPERATURE', 'HUMIDITY', 'TIME', 'TIME_DATE', 'TEMPERATURE_TIME', 'MAIN_AND_AUX_TEMPERATURE',
                ],
            ]),
        ]);
        $this->entityManager->persist($hvac);
        return $hvac;
    }

    private function createDevice(string $name, Location $location, array $channelTypes, string $registerAs): IODevice {
        $device = new IODevice();
        AnyFieldSetter::set($device, [
            'name' => $name,
            'guid' => rand(0, 9999999),
            'regDate' => new DateTime(),
            'lastConnected' => new DateTime(),
            'regIpv4' => implode('.', [rand(0, 255), rand(0, 255), rand(0, 255), rand(0, 255)]),
            'softwareVersion' => '2.' . rand(0, 50),
            'protocolVersion' => '2.' . rand(0, 50),
            'location' => $location,
            'user' => $location->getUser(),
            'flags' => IoDeviceFlags::getAllFeaturesFlag(),
            'userConfig' => '{"statusLed": "ON_WHEN_CONNECTED"}',
        ]);
        $this->entityManager->persist($device);
        foreach ($channelTypes as $channelNumber => $channelData) {
            $channel = new IODeviceChannel();
            AnyFieldSetter::set($channel, [
                'iodevice' => $device,
                'user' => $location->getUser(),
                'type' => $channelData[0],
                'function' => $channelData[1],
                'channelNumber' => $channelNumber,
            ]);
            if (isset($channelData[2])) {
                AnyFieldSetter::set($channel, $channelData[2]);
            }
            if ($this->faker->boolean) {
                $channel->setCaption($this->faker->sentence(3));
            }
            $this->setChannelProperties($channel);
            $this->entityManager->persist($channel);
            $this->entityManager->flush();
        }
        $this->entityManager->refresh($device);
        if ($registerAs) {
            $this->setReference($registerAs, $device);
        }
        return $device;
    }

    private function setChannelProperties(IODeviceChannel $channel) {
        $channelProperties = [];
        switch ($channel->getType()->getId()) {
            case ChannelType::ACTION_TRIGGER:
                $possibleTriggers = ['TURN_ON', 'TURN_OFF', 'TOGGLE_X1', 'TOGGLE_X2', 'TOGGLE_X3', 'TOGGLE_X4', 'TOGGLE_X5',
                    'HOLD', 'SHORT_PRESS_X1', 'SHORT_PRESS_X2', 'SHORT_PRESS_X3', 'SHORT_PRESS_X4', 'SHORT_PRESS_X5'];
                $possibleTriggersForChannel = $this->faker->randomElements($possibleTriggers, $this->faker->numberBetween(1, 5));
                $channelProperties = ['actionTriggerCapabilities' => $possibleTriggersForChannel];
                break;
            case ChannelType::ELECTRICITYMETER:
                $possibleCounters = ['forwardActiveEnergy', 'reverseActiveEnergy', 'forwardReactiveEnergy', 'reverseReactiveEnergy',
                    'forwardActiveEnergyBalanced', 'reverseActiveEnergyBalanced'];
                $numberOfCounters = $this->faker->numberBetween(2, count($possibleCounters));
                $countersAvailable = $this->faker->randomElements($possibleCounters, $numberOfCounters);
                $channelProperties['countersAvailable'] = $countersAvailable;
                break;
        }
        if ($channelProperties) {
            EntityUtils::setField($channel, 'properties', json_encode($channelProperties));
        }
    }
}
