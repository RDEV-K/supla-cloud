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

namespace SuplaBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use SuplaBundle\Entity\DirectLink;
use SuplaBundle\Entity\IODeviceChannelGroup;
use SuplaBundle\Enums\ActionableSubjectType;
use SuplaBundle\Enums\AuditedEvent;
use SuplaBundle\Enums\ChannelFunctionAction;
use SuplaBundle\Exception\ApiException;
use SuplaBundle\Model\Audit\Audit;
use SuplaBundle\Model\ChannelActionExecutor\ChannelActionExecutor;
use SuplaBundle\Model\ChannelStateGetter\ChannelStateGetter;
use SuplaBundle\Model\Transactional;
use SuplaBundle\Supla\SuplaServerAware;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class ExecuteDirectLinkController extends Controller {
    use Transactional;
    use SuplaServerAware;

    /** @var ChannelActionExecutor */
    private $channelActionExecutor;
    /** @var EncoderFactoryInterface */
    private $encoderFactory;
    /** @var ChannelStateGetter */
    private $channelStateGetter;
    /** @var Audit */
    private $audit;
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        ChannelActionExecutor $channelActionExecutor,
        EncoderFactoryInterface $encoderFactory,
        ChannelStateGetter $channelStateGetter,
        Audit $audit
    ) {
        $this->entityManager = $entityManager;
        $this->channelActionExecutor = $channelActionExecutor;
        $this->encoderFactory = $encoderFactory;
        $this->channelStateGetter = $channelStateGetter;
        $this->audit = $audit;
    }

    /**
     * @Route("/direct/{directLink}/{slug}", methods={"GET"})
     * @Template()
     */
    public function directLinkOptionsAction(DirectLink $directLink, string $slug) {
        $this->ensureLinkCanBeUsed($directLink, $slug);
        return ['directLink' => $directLink];
    }

    /**
     * @Route("/direct/{directLink}", methods={"PATCH"})
     * @Route("/direct/{directLink}/{slug}/{action}", methods={"GET", "PATCH"})
     */
    public function executeDirectLinkAction(DirectLink $directLink, Request $request, string $slug = null, string $action = null) {
        $responseType = $this->determineResponseType($request);
        try {
            $action = $this->ensureLinkCanBeExecuted($directLink, $request, $slug, $action);
            $response = $this->executeDirectLink($responseType, $directLink, $request, $action);
        } catch (ApiException $e) {
            $response = $this->errorResponseWithAudit($directLink, $responseType, $e->getMessage());
        } catch (\Exception $e) {
            $errorData = ['success' => false, 'supla_server_alive' => $this->suplaServer->isAlive()];
            if ($directLink->getSubjectType() == ActionableSubjectType::CHANNEL()) {
                $errorData['device_connected'] =
                    $this->suplaServer->isDeviceConnected($directLink->getSubject()->getIoDevice());
            } elseif ($directLink->getSubjectType() == ActionableSubjectType::CHANNEL_GROUP()) {
                $errorData['devices_connected'] = [];
                /** @var IODeviceChannelGroup $channelGroup */
                $channelGroup = $directLink->getSubject();
                foreach ($channelGroup->getChannels() as $channel) {
                    $errorData['devices_connected'][$channel->getId()] =
                        $this->suplaServer->isDeviceConnected($channel->getIoDevice());
                }
            }
            $response = $this->errorResponseWithAudit(
                $directLink,
                $responseType,
                $errorData,
                Response::HTTP_SERVICE_UNAVAILABLE,
                'Direct link execution failure.'
            );
        }
        $directLink->markExecution($request);
        $this->entityManager->persist($directLink);
        if ($response->isSuccessful()) {
            $this->audit->newEntry(AuditedEvent::DIRECT_LINK_EXECUTION())
                ->setIntParam($directLink->getId())
                ->setTextParam($action)
                ->buildAndSave();
        }
        $this->entityManager->flush();
        return $response;
    }

    private function ensureLinkCanBeExecuted(
        DirectLink $directLink,
        Request $request,
        string $slug = null,
        string $action = null
    ): ChannelFunctionAction {
        if ($directLink->getDisableHttpGet() && $request->isMethod(Request::METHOD_GET)) {
            throw new ApiException('The action was prevented from being performed using an HTTP GET method that is not permitted.'); // i18n
        }
        if (!$slug) {
            $requestPayload = $request->request->all();
            if (!is_array($requestPayload) || !isset($requestPayload['code']) || !isset($requestPayload['action'])) {
                throw new ApiException('Invalid request data: code and action required.'); // i18n
            }
            $slug = $requestPayload['code'];
            $action = $requestPayload['action'];
            if (!$slug || !is_string($slug) || !is_string($action) || !$action) {
                throw new ApiException('Invalid request data: invalid slug or action.'); // i18n
            }
        }
        try {
            $action = ChannelFunctionAction::fromString($action);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException("Action $action is not supported.");
        }
        if (!in_array($action, $directLink->getAllowedActions())) {
            throw new ApiException("The action {$action->getNameSlug()} is not allowed for this direct link.", Response::HTTP_FORBIDDEN);
        }
        $this->ensureLinkCanBeUsed($directLink, $slug);
        return $action;
    }

    private function executeDirectLink(string $responseType, DirectLink $directLink, Request $request, ChannelFunctionAction $action) {

        if ($action->getId() === ChannelFunctionAction::READ) {
            $state = $this->channelStateGetter->getState($directLink->getSubject());
            if ($state == []) {
                $state = new \stdClass();
            }
            return new JsonResponse($state, Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } else {
            $params = $request->query->all();
            try {
                $this->channelActionExecutor->validateActionParams($directLink->getSubject(), $action, $params);
            } catch (\InvalidArgumentException $e) {
                if ($responseType == 'html') {
                    $this->audit->newEntry(AuditedEvent::DIRECT_LINK_EXECUTION_FAILURE())
                        ->setIntParam($directLink->getId())
                        ->setTextParam('Invalid direct link parameters.')// i18n
                        ->buildAndSave();
                    return $this->render(
                        '@Supla/ExecuteDirectLink/directLinkParamsRequired.html.twig',
                        ['directLink' => $directLink, 'action' => $action],
                        new Response('', Response::HTTP_BAD_REQUEST)
                    );
                } else {
                    throw new ApiException('Invalid direct link parameters.'); // i18n
                }
            }
            $this->channelActionExecutor->executeAction($directLink->getSubject(), $action, $params);
            if ($responseType == 'html' || $responseType == 'plain') {
                $message = $responseType == 'html' ? '<span style="color: green">OK</span>' : 'OK';
                return new Response($message, Response::HTTP_ACCEPTED, ['Content-Type' => 'text/' . $responseType]);
            } else {
                return new JsonResponse(['success' => true], Response::HTTP_ACCEPTED);
            }
        }
    }

    private function ensureLinkCanBeUsed(DirectLink $directLink, string $slug) {
        if (!$directLink->isValidSlug($slug, $this->encoderFactory->getEncoder($directLink))) {
            throw new ApiException("Given verification code is invalid.", Response::HTTP_FORBIDDEN);
        }
        $directLink->ensureIsActive();
    }

    private function determineResponseType(Request $request): string {
        if (in_array('text/html', $request->getAcceptableContentTypes())) {
            return 'html';
        } else if (in_array('text/plain', $request->getAcceptableContentTypes())) {
            return 'plain';
        } else {
            return 'json';
        }
    }

    private function errorResponseWithAudit(
        DirectLink $directLink,
        string $responseType,
        $message,
        int $responseStatus = Response::HTTP_BAD_REQUEST,
        $messageForAudit = ''
    ): Response {
        $this->audit->newEntry(AuditedEvent::DIRECT_LINK_EXECUTION_FAILURE())
            ->setIntParam($directLink->getId())
            ->setTextParam($messageForAudit ?: $message)
            ->buildAndSave();
        return $this->errorResponse($responseType, $message, $responseStatus);
    }

    private function errorResponse(string $responseType, $message, int $responseStatus = Response::HTTP_BAD_REQUEST): Response {
        if (is_string($message) && $responseType == 'html' || $responseType == 'plain') {
            if ($responseType == 'html') {
                $message = '<span style="color: red">' . $message . '</span>';
            }
            return new Response($message, $responseStatus, ['Content-Type' => 'text/' . $responseType]);
        } else {
            if (is_string($message)) {
                $message = ['error' => $message];
            }
            return new JsonResponse(array_merge(['success' => false], $message), $responseStatus);
        }
    }
}
