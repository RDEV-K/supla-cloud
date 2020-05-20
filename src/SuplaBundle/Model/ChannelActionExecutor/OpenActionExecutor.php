<?php
namespace SuplaBundle\Model\ChannelActionExecutor;

use Assert\Assertion;
use SuplaBundle\Entity\HasFunction;
use SuplaBundle\Entity\IODeviceChannel;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelFunctionAction;

class OpenActionExecutor extends SetCharValueActionExecutor {
    protected function getCharValue(HasFunction $subject, array $actionParams = []): int {
        if ($this->isOpenCloseSubject($subject)) {
            return 2;
        } else {
            return 1;
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

    private function isOpenCloseSubject(HasFunction $subject): bool {
        return in_array($subject->getFunction()->getId(), [ChannelFunction::CONTROLLINGTHEGATE, ChannelFunction::CONTROLLINGTHEGARAGEDOOR]);
    }

    public function validateActionParams(HasFunction $subject, array $actionParams): array {
        Assertion::true(
            !$this->isOpenCloseSubject($subject) || $subject instanceof IODeviceChannel,
            "Cannot execute the requested action CLOSE on channel group."
        );
        return parent::validateActionParams($subject, $actionParams);
    }
}
