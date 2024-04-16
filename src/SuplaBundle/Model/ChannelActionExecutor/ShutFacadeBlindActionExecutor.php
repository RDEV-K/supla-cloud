<?php
namespace SuplaBundle\Model\ChannelActionExecutor;

use Assert\Assertion;
use SuplaBundle\Entity\ActionableSubject;
use SuplaBundle\Enums\ChannelFunctionAction;

class ShutFacadeBlindActionExecutor extends ShutPartiallyFacadeBlindActionExecutor {
    public function getSupportedAction(): ChannelFunctionAction {
        return ChannelFunctionAction::SHUT();
    }

    public function validateAndTransformActionParamsFromApi(ActionableSubject $subject, array $actionParams): array {
        Assertion::noContent($actionParams, 'This action is not supposed to have any parameters.');
        return [];
    }

    public function execute(ActionableSubject $subject, array $actionParams = []) {
        parent::execute($subject, ['percentage' => 100, 'percentageAsDelta' => false, 'tilt' => 100, 'tiltAsDelta' => false]);
    }
}
