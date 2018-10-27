<?php
namespace SuplaBundle\Model\ChannelParamsUpdater;

use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Entity\IODeviceChannel;

class AnyMeterCurrency implements SingleChannelParamsUpdater {

    public function updateChannelParams(IODeviceChannel $channel, IODeviceChannel $updatedChannel) {
        if (preg_match('/^[A-Z]{3}$/', $updatedChannel->getParam4())) {
            $channel->setParam4($updatedChannel->getParam4());
        }
    }

    public function supports(IODeviceChannel $channel): bool {
        return in_array($channel->getFunction(), [ChannelFunction::ELECTRICITYMETER(),
            ChannelFunction::GASMETER(),
            ChannelFunction::WATERMETER()]);
    }
}
