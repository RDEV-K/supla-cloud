<?php
namespace SuplaBundle\ParamConverter;

use Assert\Assertion;
use SuplaBundle\Entity\DirectLink;
use SuplaBundle\Entity\EntityUtils;
use SuplaBundle\Enums\ChannelFunctionAction;
use SuplaBundle\Model\CurrentUserAware;
use SuplaBundle\Repository\ChannelGroupRepository;
use SuplaBundle\Repository\IODeviceChannelRepository;

class DirectLinkParamConverter extends AbstractBodyParamConverter {
    use CurrentUserAware;

    /** @var IODeviceChannelRepository */
    private $channelRepository;
    /** @var ChannelGroupRepository */
    private $channelGroupRepository;

    public function __construct(IODeviceChannelRepository $channelRepository, ChannelGroupRepository $channelGroupRepository) {
        $this->channelRepository = $channelRepository;
        $this->channelGroupRepository = $channelGroupRepository;
    }

    public function getConvertedClass(): string {
        return DirectLink::class;
    }

    public function convert(array $data) {
        $user = $this->getCurrentUserOrThrow();
        $subjectIds = array_intersect(['channelId', 'channelGroupId'], array_keys($data));
        Assertion::count($subjectIds, 1, 'You must set either channelId or channelGroupId for the link.');
        Assertion::keyExists($data, 'allowedActions', 'AllowedActions must be set.');
        Assertion::isArray($data['allowedActions'], 'AllowedActions must be an array.');
        $subjectIdKey = current($subjectIds);
        $subjectId = $data[$subjectIdKey];
        $subject = $subjectIdKey === 'channelId'
            ? $this->channelRepository->findForUser($user, $subjectId)
            : $this->channelGroupRepository->findForUser($user, $subjectId);
        $link = new DirectLink($subject);
        $link->setCaption($data['caption'] ?? '');
        $possibleActions = EntityUtils::mapToIds($subject->getFunction()->getPossibleActions());
        $possibleActions[] = ChannelFunctionAction::READ;
        $allowedActions = array_map(function ($allowedActionName) use ($possibleActions) {
            $allowedAction = ChannelFunctionAction::fromString($allowedActionName);
            Assertion::inArray($allowedAction->getId(), $possibleActions, "Action $allowedActionName cannot be executed on this channel.");
            return $allowedAction;
        }, $data['allowedActions']);
        $link->setAllowedActions($allowedActions);
        return $link;
    }
}
