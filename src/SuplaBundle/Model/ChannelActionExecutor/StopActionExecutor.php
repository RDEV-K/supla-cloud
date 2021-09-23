<?php
namespace SuplaBundle\Model\ChannelActionExecutor;

use SuplaBundle\Entity\ActionableSubject;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelFunctionAction;

class StopActionExecutor extends SetCharValueActionExecutor {
    public function getSupportedFunctions(): array {
        return [
            ChannelFunction::CONTROLLINGTHEROLLERSHUTTER(),
            ChannelFunction::CONTROLLINGTHEROOFWINDOW(),
        ];
    }

    public function getSupportedAction(): ChannelFunctionAction {
        return ChannelFunctionAction::STOP();
    }

    protected function getCharValue(ActionableSubject $subject, array $actionParams = []): int {
        return 0;
    }
}
