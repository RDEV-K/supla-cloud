<?php
namespace SuplaBundle\Model\ChannelActionExecutor;

use SuplaBundle\Entity\HasFunction;
use SuplaBundle\Entity\IODeviceChannelGroup;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelFunctionAction;
use SuplaBundle\Model\ChannelStateGetter\ChannelStateGetter;

class OpenActionExecutor extends SetCharValueActionExecutor {
    /** @var ChannelStateGetter */
    private $channelStateGetter;

    public function __construct(ChannelStateGetter $channelStateGetter) {
        $this->channelStateGetter = $channelStateGetter;
    }

    public function execute(HasFunction $subject, array $actionParams = []) {
        if (in_array($subject->getFunction()->getId(), [ChannelFunction::CONTROLLINGTHEGATE, ChannelFunction::CONTROLLINGTHEGARAGEDOOR])) {
            $this->openIfClosedOnly($subject, $actionParams);
        } else {
            parent::execute($subject, $actionParams);
        }
    }

    private function openIfClosedOnly(HasFunction $subject, array $actionParams = []) {
        if ($subject instanceof IODeviceChannelGroup) {
            foreach ($subject->getChannels() as $channel) {
                $this->execute($channel);
            }
        } else {
            $state = $this->channelStateGetter->getState($subject);
            if ($state['hi'] ?? false) {
                parent::execute($subject, $actionParams);
            }
        }
    }

    public function getSupportedFunctions(): array {
        return [
            ChannelFunction::CONTROLLINGTHEGATEWAYLOCK(),
            ChannelFunction::CONTROLLINGTHEDOORLOCK(),
            ChannelFunction::CONTROLLINGTHEGARAGEDOOR(),
            ChannelFunction::CONTROLLINGTHEGATE(),
        ];
    }

    public function getSupportedAction(): ChannelFunctionAction {
        return ChannelFunctionAction::OPEN();
    }
}
