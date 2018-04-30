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

namespace SuplaBundle\Entity;

use Assert\Assertion;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelType;
use SuplaBundle\Enums\RelayFunctionBits;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="SuplaBundle\Repository\IODeviceChannelRepository")
 * @ORM\Table(name="supla_dev_channel",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="UNIQUE_CHANNEL", columns={"iodevice_id","channel_number"})})
 */
class IODeviceChannel implements HasFunction {
    use BelongsToUser;

    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"basic"})
     */
    private $id;

    /**
     * @ORM\Column(name="channel_number", type="integer", nullable=false)
     * @Groups({"basic"})
     */
    private $channelNumber;

    /**
     * @ORM\ManyToOne(targetEntity="IODevice", inversedBy="channels")
     * @ORM\JoinColumn(name="iodevice_id", referencedColumnName="id", nullable=false)
     * @Groups({"iodevice"})
     */
    private $iodevice;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="channels")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
     */
    private $user;

    /**
     * @var Schedule[]
     * @ORM\OneToMany(targetEntity="Schedule", mappedBy="channel", cascade={"remove"})
     */
    private $schedules;

    /**
     * @ORM\ManyToOne(targetEntity="Location", inversedBy="ioDeviceChannels")
     * @ORM\JoinColumn(name="location_id", referencedColumnName="id", nullable=true)
     * @Groups({"location"})
     */
    private $location;

    /**
     * @ORM\Column(name="caption", type="string", length=100, nullable=true)
     * @Groups({"basic"})
     */
    private $caption;

    /**
     * @ORM\Column(name="type", type="integer", nullable=false)
     * @Groups({"basic"})
     */
    private $type;

    /**
     * @ORM\Column(name="func", type="integer", nullable=false)
     * @Groups({"basic"})
     */
    private $function;

    /**
     * @ORM\Column(name="flist", type="integer", nullable=true)
     */
    private $funcList;

    /**
     * @ORM\Column(name="param1", type="integer", nullable=false)
     * @Groups({"basic"})
     */
    private $param1 = 0;

    /**
     * @ORM\Column(name="param2", type="integer", nullable=false)
     * @Groups({"basic"})
     */
    private $param2 = 0;

    /**
     * @ORM\Column(name="param3", type="integer", nullable=false)
     * @Groups({"basic"})
     */
    private $param3 = 0;

    /**
     * @ORM\Column(name="alt_icon", type="integer", nullable=true)
     * @Groups({"basic"})
     */
    private $altIcon = 0;

    /**
     * @ORM\Column(name="hidden", type="boolean", nullable=false, options={"default"=0})
     * @Groups({"basic"})
     */
    private $hidden = false;

    /**
     * @ORM\ManyToMany(targetEntity="IODeviceChannelGroup", mappedBy="channels", cascade={"persist"})
     */
    private $channelGroups;

    public function getId(): int {
        return $this->id;
    }

    public function getChannelNumber() {
        return $this->channelNumber;
    }

    public function getCaption() {
        return $this->caption;
    }

    public function setCaption($caption) {
        $this->caption = $caption;
    }

    public function getType(): ChannelType {
        return new ChannelType($this->type);
    }

    /** @return IODevice */
    public function getIoDevice() {
        return $this->iodevice;
    }

    public function getUser(): User {
        return $this->user;
    }

    public function getLocation(): Location {
        return $this->location ?: $this->getIoDevice()->getLocation();
    }

    /**
     * @param Location|null $location
     */
    public function setLocation($location) {
        $this->location = $location;
    }

    /** @Groups({"basic"}) */
    public function hasInheritedLocation(): bool {
        return !$this->location;
    }

    /** @return Collection|Schedule[] */
    public function getSchedules(): Collection {
        return $this->schedules;
    }

    public function getFunction(): ChannelFunction {
        return new ChannelFunction($this->function);
    }

    /** @param $function ChannelFunction|int */
    public function setFunction($function) {
        if ($function instanceof ChannelFunction) {
            $function = $function->getValue();
        } else {
            $function = intval($function);
        }
        Assertion::true(ChannelFunction::isValid($function), "Not valid channel function: " . $function);
        $this->function = $function;
        $this->param1 = $this->param2 = $this->param3 = 0;
        $this->altIcon = 0;
    }

    /**
     * @see RelayFunctionBits
     * @return int
     */
    public function getFuncList(): int {
        return $this->funcList ?: 0;
    }

    /**
     * @Groups({"supportedFunctions"})
     * @return ChannelFunction
     */
    public function getSupportedFunctions(): array {
        return ChannelFunction::forChannel($this);
    }

    /** @deprecated ridiculous */
    public function getChannel() {
        return $this;
    }

    public function getParam(int $paramNo): int {
        Assertion::inArray($paramNo, [1, 2, 3], 'Invalid param number: ' . $paramNo);
        $getter = "getParam$paramNo";
        return $this->{$getter}();
    }

    public function setParam(int $paramNo, int $value) {
        Assertion::inArray($paramNo, [1, 2, 3], 'Invalid param number: ' . $paramNo);
        $setter = "setParam$paramNo";
        return $this->{$setter}($value);
    }

    public function getParam1(): int {
        return $this->param1;
    }

    public function setParam1(int $param1) {
        $this->param1 = $param1;
    }

    public function getParam2(): int {
        return $this->param2;
    }

    public function setParam2(int $param2) {
        $this->param2 = $param2;
    }

    public function getParam3(): int {
        return $this->param3;
    }

    public function setParam3(int $param3) {
        $this->param3 = $param3;
    }

    public function getAltIcon(): int {
        return intval($this->altIcon);
    }

    public function setAltIcon($altIcon) {
        $this->altIcon = intval($altIcon);
    }

    public function getIconSuffix() {
        return ($this->getAltIcon() > 0 ? '_' . $this->getAltIcon() : '') . '.svg';
    }

    public function getIconFilename() {
        return $this->getFunction() . $this->getIconSuffix();
    }

    public function getHidden() {
        return $this->hidden;
    }

    public function setHidden($hidden) {
        $this->hidden = $hidden;
    }

    /** @return Collection|IODeviceChannelGroup[] */
    public function getChannelGroups(): Collection {
        return $this->channelGroups;
    }

    public function removeFromAllChannelGroups(EntityManagerInterface $entityManager) {
        foreach ($this->getChannelGroups() as $channelGroup) {
            $channelGroup->removeChannel($this, $entityManager);
        }
    }

    public function buildServerSetCommand(string $type, array $actionParams): string {
        $params = array_merge([$this->getUser()->getId(), $this->getIoDevice()->getId(), $this->getId()], $actionParams);
        $params = implode(',', $params);
        return "SET-$type-VALUE:$params";
    }
}
