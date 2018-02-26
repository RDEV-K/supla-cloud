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

namespace SuplaApiBundle\Controller;

use Assert\Assertion;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use SuplaApiBundle\Model\ApiVersions;
use SuplaApiBundle\Model\ChannelParamsUpdater\ChannelParamsUpdater;
use SuplaApiBundle\Model\ChannelStateGetter\ChannelStateGetter;
use SuplaBundle\Entity\IODeviceChannel;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelType;
use SuplaBundle\Model\IODeviceManager;
use SuplaBundle\Model\Transactional;
use SuplaBundle\Supla\SuplaConst;
use SuplaBundle\Supla\SuplaServerAware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiChannelController extends RestController {
    use SuplaServerAware;
    use Transactional;

    const RECORD_LIMIT_PER_REQUEST = 5000;

    /** @var IODeviceManager */
    private $deviceManager;
    /** @var ChannelParamsUpdater */
    private $channelParamsUpdater;
    /** @var ChannelStateGetter */
    private $channelStateGetter;

    public function __construct(
        IODeviceManager $deviceManager,
        ChannelParamsUpdater $channelParamsUpdater,
        ChannelStateGetter $channelStateGetter
    ) {
        $this->deviceManager = $deviceManager;
        $this->channelParamsUpdater = $channelParamsUpdater;
        $this->channelStateGetter = $channelStateGetter;
    }

    public function getChannelsAction(Request $request) {
        $criteria = Criteria::create();
        if (($function = $request->get('function')) !== null) {
            $functionIds = array_map(function ($fnc) {
                return ChannelFunction::isValidKey($fnc)
                    ? ChannelFunction::$fnc()->getValue()
                    : (new ChannelFunction((int)$fnc))->getValue();
            }, explode(',', $function));
            $criteria->andWhere(Criteria::expr()->in('function', $functionIds));
        }
        if (($io = $request->get('io')) !== null) {
            Assertion::inArray($io, ['input', 'output']);
            $criteria->andWhere(
                Criteria::expr()->in('type', $io == 'output'
                    ? ChannelType::outputTypes()
                    : ChannelType::inputTypes())
            );
        }
        if (($hasFunction = $request->get('hasFunction')) !== null) {
            if ($hasFunction) {
                $criteria->andWhere(Criteria::expr()->neq('function', ChannelFunction::NONE));
            } else {
                $criteria->andWhere(Criteria::expr()->eq('function', ChannelFunction::NONE));
            }
        }
        $channels = $this->getCurrentUser()->getChannels()->matching($criteria);
        $view = $this->view($channels, Response::HTTP_OK);
        $this->setSerializationGroups($view, $request, ['iodevice', 'location', 'function', 'type']);
        return $view;
    }

    protected function channelById($channelid, $functions = null, $checkConnected = false, $authorize = false) {

        $channelid = intval($channelid);

        $channel = $this->deviceManager->channelById($channelid, $this->getParentUser());

        if (!($channel instanceof IODeviceChannel)) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'The device channel could not be found');
        }

        if (is_array($functions) && !in_array($channel->getFunction()->getId(), $functions)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'The requested function is not available on this device');
        }

        if ($checkConnected === true) {
            $connected = false;

            $devid = $channel->getIoDevice()->getId();
            $userid = $this->getParentUser()->getId();

            if ($channel->getIoDevice()->getEnabled()) {
                $cids = $this->suplaServer->checkDevicesConnection($userid, [$devid]);
                $connected = in_array($devid, $cids);
            }

            if ($connected === false) {
                throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, 'The requested device is not connected');
            }
        }

        if ($authorize === true) {
            $token = $this->container->get('security.token_storage')->getToken()->getToken();
            if (true !== $this->suplaServer->oauthAuthorize($userid, $token)) {
                throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Supla server has rejected the authorization token');
            }
        }

        return $channel;
    }

    protected function getTempHumidityLogCountAction($th, $channelid) {

        $f = [];

        if ($th === true) {
            $f[] = SuplaConst::FNC_HUMIDITYANDTEMPERATURE;
        } else {
            $f[] = SuplaConst::FNC_THERMOMETER;
        }

        $channel = $this->channelById($channelid, $f);

        $em = $this->container->get('doctrine')->getManager();
        $rep = $em->getRepository('SuplaBundle:' . ($th === true ? 'TempHumidityLogItem' : 'TemperatureLogItem'));

        $query = $rep->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.channel_id = :id')
            ->setParameter('id', $channelid)
            ->getQuery();

        return $this->handleView($this->view(
            ['count' => $query->getSingleScalarResult(),
                'record_limit_per_request' => ApiChannelController::RECORD_LIMIT_PER_REQUEST],
            Response::HTTP_OK
        ));
    }

    /**
     * @Rest\Get("/channels/{channelid}/temperature-log-count")
     */
    public function getTempLogCountAction(Request $request, $channelid) {

        return $this->getTempHumidityLogCountAction(false, $channelid);
    }

    protected function temperatureLogItems($channelid, $offset, $limit) {

        $sql = "SELECT UNIX_TIMESTAMP(CONVERT_TZ(`date`, '+00:00', 'SYSTEM')) AS date_timestamp, `temperature` ";
        $sql .= "FROM `supla_temperature_log` WHERE channel_id = ? LIMIT ? OFFSET ?";

        $stmt = $this->container->get('doctrine')->getManager()->getConnection()->prepare($sql);
        $stmt->bindValue(1, $channelid, 'integer');
        $stmt->bindValue(2, $limit, 'integer');
        $stmt->bindValue(3, $offset, 'integer');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    protected function temperatureAndHumidityLogItems($channelid, $offset, $limit) {

        $sql = "SELECT UNIX_TIMESTAMP(CONVERT_TZ(`date`, '+00:00', 'SYSTEM')) AS date_timestamp, `temperature`, ";
        $sql .= "`humidity` FROM `supla_temphumidity_log` WHERE channel_id = ? LIMIT ? OFFSET ?";

        $stmt = $this->container->get('doctrine')->getManager()->getConnection()->prepare($sql);
        $stmt->bindValue(1, $channelid, 'integer');
        $stmt->bindValue(2, $limit, 'integer');
        $stmt->bindValue(3, $offset, 'integer');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    protected function getTempHumidityLogItemsAction($th, $channelid, $offset, $limit) {

        $f[] = $th === true ? SuplaConst::FNC_HUMIDITYANDTEMPERATURE : SuplaConst::FNC_THERMOMETER;

        $channel = $this->channelById($channelid, $f);

        $offset = intval($offset);
        $limit = intval($limit);

        if ($limit <= 0) {
            $limit = ApiChannelController::RECORD_LIMIT_PER_REQUEST;
        }

        if ($th === true) {
            $result = $this->temperatureAndHumidityLogItems($channelid, $offset, $limit);
        } else {
            $result = $this->temperatureLogItems($channelid, $offset, $limit);
        }

        return $this->handleView($this->view(['log' => $result], Response::HTTP_OK));
    }

    /**
     * @Rest\Get("/channels/{channelid}/temperature-log-items")
     */
    public function getTempLogItemsAction(Request $request, $channelid) {

        return $this->getTempHumidityLogItemsAction(false, $channelid, @$request->query->get('offset'), @$request->query->get('limit'));
    }

    /**
     * @Rest\Get("/channels/{channelid}/temperature-and-humidity-count")
     */
    public function getTempHumLogCountAction(Request $request, $channelid) {

        return $this->getTempHumidityLogCountAction(true, $channelid);
    }

    /**
     * @Rest\Get("/channels/{channelid}/temperature-and-humidity-items")
     */
    public function getTempHumLogItemsAction(Request $request, $channelid) {

        return $this->getTempHumidityLogItemsAction(true, $channelid, @$request->query->get('offset'), @$request->query->get('limit'));
    }

    /**
     * @Security("channel.belongsToUser(user)")
     */
    public function getChannelAction(Request $request, IODeviceChannel $channel) {
        if (ApiVersions::V2_2()->isRequestedEqualOrGreaterThan($request)) {
            $view = $this->view($channel, Response::HTTP_OK);
            $this->setSerializationGroups(
                $view,
                $request,
                ['iodevice', 'location', 'function', 'type', 'connected', 'state', 'supportedFunctions']
            );
            return $view;
        } else {
            $enabled = false;
            $connected = false;
            $devid = $channel->getIoDevice()->getId();
            $userid = $this->getUser()->getId();
            if ($channel->getIoDevice()->getEnabled()) {
                $enabled = true;
                $cids = $this->suplaServer->checkDevicesConnection($userid, [$devid]);
                $connected = in_array($devid, $cids);
            }
            $result = array_merge(['connected' => $connected, 'enabled' => $enabled], $this->channelStateGetter->getState($channel));
            return $this->handleView($this->view($result, Response::HTTP_OK));
        }
    }

    /**
     * @Security("channel.belongsToUser(user)")
     */
    public function putChannelAction(Request $request, IODeviceChannel $channel, IODeviceChannel $updatedChannel) {
        if (ApiVersions::V2_2()->isRequestedEqualOrGreaterThan($request)) {
            $functionHasBeenChanged = $channel->getFunction() != $updatedChannel->getFunction();
            if ($functionHasBeenChanged) {
                if (!$request->get('confirm') && (count($channel->getSchedules()) || count($channel->getChannelGroups()))) {
                    return $this->view([
                        'schedules' => $channel->getSchedules(),
                        'groups' => $channel->getChannelGroups(),
                    ], Response::HTTP_CONFLICT);
                }
                $channel->setFunction($updatedChannel->getFunction());
            } else {
                $channel->setAltIcon($updatedChannel->getAltIcon());
            }
            if ($updatedChannel->hasInheritedLocation()) {
                $channel->setLocation(null);
            } else {
                $channel->setLocation($updatedChannel->getLocation());
            }
            $channel->setCaption($updatedChannel->getCaption());
            $channel->setHidden($updatedChannel->getHidden());
            $this->channelParamsUpdater->updateChannelParams($channel, $updatedChannel);
            return $this->transactional(function (EntityManagerInterface $em) use ($functionHasBeenChanged, $request, $channel) {
                $em->persist($channel);
                if ($functionHasBeenChanged) {
                    foreach ($channel->getSchedules() as $schedule) {
                        $this->get('schedule_manager')->delete($schedule);
                    }
                    foreach ($channel->getChannelGroups() as $channelGroup) {
                        $channelGroup->getChannels()->removeElement($channel);
                        if ($channelGroup->getChannels()->isEmpty()) {
                            $em->remove($channelGroup);
                        } else {
                            $em->persist($channelGroup);
                        }
                    }
                } elseif ($channel->getFunction() != ChannelFunction::NONE()) {
                    $this->suplaServer->reconnect($this->getCurrentUser()->getId());
                }
                return $this->getChannelAction($request, $channel);
            });
        } else {
            $channelid = $channel->getId();
            $channel = $this->channelById($channelid, null, true, true);
            $data = json_decode($request->getContent());

            $devid = $channel->getIoDevice()->getId();
            $userid = $this->getParentUser()->getId();

            $func = $channel->getFunction()->getId();

            switch ($func) {
                case SuplaConst::FNC_DIMMER:
                case SuplaConst::FNC_RGBLIGHTING:
                case SuplaConst::FNC_DIMMERANDRGBLIGHTING:
                    $color = intval(@$data->color);
                    $color_brightness = intval(@$data->color_brightness);
                    $brightness = intval(@$data->brightness);

                    if ($func == SuplaConst::FNC_RGBLIGHTING
                        || $func == SuplaConst::FNC_DIMMERANDRGBLIGHTING
                    ) {
                        if ($color <= 0
                            || $color > 0xffffff
                            || $color_brightness < 0
                            || $color_brightness > 100
                        ) {
                            throw new HttpException(Response::HTTP_BAD_REQUEST);
                        }
                    }

                    if ($func == SuplaConst::FNC_DIMMER
                        || $func == SuplaConst::FNC_DIMMERANDRGBLIGHTING
                    ) {
                        if ($brightness < 0
                            || $brightness > 100
                        ) {
                            throw new HttpException(Response::HTTP_BAD_REQUEST);
                        }
                    }

                    if (false === $this->suplaServer->setRgbwValue($userid, $devid, $channelid, $color, $color_brightness, $brightness)) {
                        throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE);
                    }

                    break;

                default:
                    throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid action.');
            }

            return $this->handleView($this->view(null, Response::HTTP_OK));
        }
    }

    private function checkPatchAllowed($action, $func) {

        switch ($action) {
            case 'turn-on':
            case 'turn-off':
                switch ($func) {
                    case SuplaConst::FNC_POWERSWITCH:
                    case SuplaConst::FNC_LIGHTSWITCH:
                        return true;
                }
                break;

            case 'open':
                switch ($func) {
                    case SuplaConst::FNC_CONTROLLINGTHEGATEWAYLOCK:
                    case SuplaConst::FNC_CONTROLLINGTHEDOORLOCK:
                        return true;
                }
                break;

            case 'open-close':
                switch ($func) {
                    case SuplaConst::FNC_CONTROLLINGTHEGATE:
                    case SuplaConst::FNC_CONTROLLINGTHEGARAGEDOOR:
                        return true;
                }
                break;

            case 'shut':
            case 'reveal':
            case 'stop':
                if ($func == SuplaConst::FNC_CONTROLLINGTHEROLLERSHUTTER) {
                    return true;
                }

                break;
        }

        throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid action.');
    }

    /**
     * @Rest\Patch("/channels/{channelid}")
     */
    public function patchChannelsAction(Request $request, $channelid) {
        $channel = $this->channelById($channelid, null, true, true);
        $data = json_decode($request->getContent());

        $devid = $channel->getIoDevice()->getId();
        $userid = $this->getParentUser()->getId();
        $action = @$data->action;

        $func = $channel->getFunction()->getId();
        $this->checkPatchAllowed($action, $func);

        $value = 0;

        switch ($action) {
            case 'turn-on':
            case 'open':
            case 'open-close':
                $value = 1;
                break;
            case 'shut':
                $value = 1;

                $percent = intval(@$data->percent);

                if ($percent >= 0 && $percent <= 100) {
                    $value = 10 + $percent;
                }

                break;
            case 'reveal':
                $value = 2;

                $percent = intval(@$data->percent);

                if ($percent >= 0 && $percent <= 100) {
                    $value = 110 - $percent;
                }

                break;
        }

        if (false === $this->suplaServer->setCharValue($userid, $devid, $channelid, $value)) {
            throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->handleView($this->view(null, Response::HTTP_OK));
    }
}
