<?php

namespace SuplaBundle\Model\UserConfigTranslator;

use Assert\Assert;
use Assert\Assertion;
use OpenApi\Annotations as OA;
use SuplaBundle\Entity\Main\IODevice;

/**
 * @OA\Schema(schema="DeviceConfig", description="Configuration of the IO Device.",
 *   @OA\Property(property="statusLed", type="string", enum={"OFF_WHEN_CONNECTED", "ALWAYS_OFF", "ON_WHEN_CONNECTED"}),
 *   @OA\Property(property="screenBrightness", oneOf={
 *     @OA\Schema(type="integer", minimum=0, maximum=100),
 *     @OA\Schema(type="string", enum={"auto"})
 *   }),
 *   @OA\Property(property="buttonVolume", type="integer", minimum=0, maximum=100),
 *   @OA\Property(property="userInterfaceDisabled", type="boolean"),
 *   @OA\Property(property="automaticTimeSync", type="boolean"),
 *   @OA\Property(property="screenSaver", type="object",
 *     @OA\Property(property="mode", type="string", enum={"OFF", "TEMPERATURE", "HUMIDITY", "TIME", "TIME_DATE", "TEMPERATURE_TIME", "MAIN_AND_AUX_TEMPERATURE"}),
 *     @OA\Property(property="delay", type="integer", description="ms"),
 *   ),
 *   @OA\Property(property="screenSaverModesAvailable", type="string", enum={"OFF", "TEMPERATURE", "HUMIDITY", "TIME", "TIME_DATE", "TEMPERATURE_TIME", "MAIN_AND_AUX_TEMPERATURE"}),
 * )
 */
class IODeviceConfigTranslator {
    public function getConfig(IODevice $device): array {
        $config = $device->getUserConfig();
        $properties = $device->getProperties();
        if ($properties['screenSaverModesAvailable'] ?? false) {
            $config['screenSaverModesAvailable'] = $properties['screenSaverModesAvailable'];
        }
        return $config;
    }

    public function setConfig(IODevice $device, array $config): void {
        $currentConfig = $device->getUserConfig();
        $config = array_diff_key($config, ['screenSaverModesAvailable' => '']);
        Assertion::allInArray(array_keys($config), array_keys($currentConfig));
        foreach ($config as $settingName => $value) {
            Assertion::keyExists($currentConfig, $settingName, 'Cannot set this setting in this device: ' . $settingName);
            if ($settingName === 'statusLed') {
                Assertion::inArray($value, ['OFF_WHEN_CONNECTED', 'ALWAYS_OFF', 'ON_WHEN_CONNECTED'], null, 'statusLed');
            }
            if ($settingName === 'screenBrightness') {
                if ($value !== 'auto') {
                    Assert::that($value, null, 'screenBrightness')->integer()->between(0, 100);
                }
            }
            if ($settingName === 'buttonVolume') {
                Assert::that($value, null, 'buttonVolume')->integer()->between(0, 100);
            }
            if ($settingName === 'userInterfaceDisabled') {
                Assert::that($value, null, 'userInterfaceDisabled')->boolean();
            }
            if ($settingName === 'automaticTimeSync') {
                Assert::that($value, null, 'automaticTimeSync')->boolean();
            }
            if ($settingName === 'screenSaver') {
                Assert::that($value)->isArray()->keyExists('mode')->keyExists('delay')->count(2);
                $availableModes = $device->getProperties()['screenSaverModesAvailable'] ?? [];
                Assertion::inArray($value['mode'], $availableModes, null, 'screenSaver.mode');
                Assert::that($value['delay'], null, 'screenSaver.delay')->integer()->between(500, 300000);
            }
            $device->setUserConfigValue($settingName, $value);
        }
    }
}
