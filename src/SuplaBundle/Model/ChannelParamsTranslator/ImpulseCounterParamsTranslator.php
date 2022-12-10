<?php

namespace SuplaBundle\Model\ChannelParamsTranslator;

use OpenApi\Annotations as OA;
use SuplaBundle\Entity\Main\IODeviceChannel;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelFunctionBitsFlags;
use SuplaBundle\Utils\NumberUtils;

/**
 * @OA\Schema(schema="ChannelConfigImpulseCounter", description="Config for `IC_*` functions.",
 *   @OA\Property(property="resetCountersAvailable", type="boolean"),
 *   @OA\Property(property="pricePerUnit", type="number"),
 *   @OA\Property(property="impulsesPerUnit", type="integer"),
 *   @OA\Property(property="currency", type="string"),
 *   @OA\Property(property="unit", type="string"),
 *   @OA\Property(property="initialValue", type="integer"),
 *   @OA\Property(property="relatedChannelId", type="integer"),
 * )
 */
class ImpulseCounterParamsTranslator implements ChannelParamTranslator {
    use FixedRangeParamsTranslator;

    public function getConfigFromParams(IODeviceChannel $channel): array {
        return [
            'pricePerUnit' => NumberUtils::maximumDecimalPrecision($channel->getParam2() / 10000, 4),
            'impulsesPerUnit' => $channel->getParam3(),
            'currency' => $channel->getTextParam1() ?: null,
            'unit' => $channel->getTextParam2() ?: null,
            'initialValue' => $channel->getUserConfigValue('initialValue', 0),
            'addToHistory' => $channel->getUserConfigValue('addToHistory', false),
            'resetCountersAvailable' => ChannelFunctionBitsFlags::RESET_COUNTERS_ACTION_AVAILABLE()->isSupported($channel->getFlags()),
        ];
    }

    public function setParamsFromConfig(IODeviceChannel $channel, array $config) {
        if (array_key_exists('initialValue', $config)) {
            $initialValue = NumberUtils::maximumDecimalPrecision($this->getValueInRange($config['initialValue'], -100000000, 100000000), 3);
            $channel->setUserConfigValue('initialValue', $initialValue);
        }
        if (array_key_exists('addToHistory', $config)) {
            $channel->setUserConfigValue('addToHistory', boolval($config['addToHistory']));
        }
        if (array_key_exists('pricePerUnit', $config)) {
            $channel->setParam2($this->getValueInRange($config['pricePerUnit'], 0, 1000) * 10000);
        }
        if (array_key_exists('impulsesPerUnit', $config)) {
            $channel->setParam3($this->getValueInRange($config['impulsesPerUnit'], 0, 1000000));
        }
        if (array_key_exists('currency', $config)) {
            $currency = $config['currency'];
            if (!$currency || preg_match('/^[A-Z]{3}$/', $currency)) {
                $channel->setTextParam1($currency);
            }
        }
        if (array_key_exists('unit', $config)) {
            if (mb_strlen($config['unit'] ?? '', 'UTF-8') <= 4) {
                $channel->setTextParam2($config['unit']);
            }
        }
    }

    public function supports(IODeviceChannel $channel): bool {
        return in_array($channel->getFunction()->getId(), [
            ChannelFunction::IC_ELECTRICITYMETER,
            ChannelFunction::IC_GASMETER,
            ChannelFunction::IC_WATERMETER,
            ChannelFunction::IC_HEATMETER,
        ]);
    }
}
