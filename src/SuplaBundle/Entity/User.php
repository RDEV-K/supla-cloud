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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="SuplaBundle\Repository\UserRepository")
 * @UniqueEntity(fields="email", message="Email already exists")
 * @ORM\Table(name="supla_user", indexes={
 *     @ORM\Index(name="client_reg_enabled_idx", columns={"client_reg_enabled"}),
 *     @ORM\Index(name="iodevice_reg_enabled_idx", columns={"iodevice_reg_enabled"})
 * })
 */
class User implements AdvancedUserInterface, EncoderAwareInterface {
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"basic"})
     */
    private $id;

    /**
     * @ORM\Column(name="salt", type="string", length=32)
     */
    private $salt;

    /**
     * @ORM\Column(name="email", type="string", length=255, unique=true)
     * @Assert\NotBlank()
     * @Assert\Email()
     * @Assert\Length(max=255)
     * @Groups({"basic"})
     */
    private $email;

    /**
     * @ORM\Column(name="password", type="string", length=64, nullable=true)
     */
    private $password;

    /**
     * @ORM\Column(name="legacy_password", type="string", length=64, nullable=true)
     */
    private $legacyPassword;

    /**
     * Plain password. Used for model validation. Must not be persisted.
     * @Assert\NotBlank()
     * @Assert\Length(min=8)
     */
    private $plainPassword;

    /**
     * @ORM\Column(name="enabled", type="boolean")
     * @Assert\NotNull()
     */
    private $enabled;

    /**
     * @ORM\Column(name="reg_date", type="utcdatetime")
     * @Assert\NotBlank()
     */
    private $regDate;

    /**
     * @ORM\Column(name="token", type="string")
     */
    private $token;

    /**
     * @ORM\Column(name="password_requested_at", type="utcdatetime", nullable=true)
     */
    private $passwordRequestedAt;

    private $recaptcha;

    /**
     * @ORM\Column(name="timezone", type="string", length=50, nullable=false)
     * @Groups({"basic"})
     */
    private $timezone;

    /**
     * @ORM\Column(name="limit_aid", type="integer")
     * @Assert\NotBlank()
     */
    private $limitAid;

    /**
     * @ORM\Column(name="limit_loc", type="integer")
     * @Assert\NotBlank()
     */
    private $limitLoc;

    /**
     * @ORM\Column(name="limit_iodev", type="integer")
     * @Assert\NotBlank()
     */
    private $limitIoDev;

    /**
     * @ORM\Column(name="limit_client", type="integer")
     * @Assert\NotBlank()
     */
    private $limitClientApp;

    /**
     * @ORM\Column(name="limit_schedule", type="integer", options={"default"=20})
     */
    private $limitSchedule;

    /**
     * @ORM\Column(name="limit_channel_group", type="integer", options={"default"=20})
     */
    private $limitChannelGroup;

    /**
     * @ORM\Column(name="limit_channel_per_group", type="integer", options={"default"=10})
     */
    private $limitChannelPerGroup;

    /**
     * @ORM\OneToMany(targetEntity="AccessID", mappedBy="user", cascade={"persist"})
     **/
    private $accessids;

    /**
     * @ORM\OneToMany(targetEntity="ClientApp", mappedBy="user", cascade={"persist"})
     **/
    private $clientApps;

    /**
     * @ORM\OneToMany(targetEntity="SuplaBundle\Entity\IODeviceChannel", mappedBy="user", cascade={"persist"})
     **/
    private $channels;

    /**
     * @ORM\OneToMany(targetEntity="Location", mappedBy="user", cascade={"persist"})
     **/
    private $locations;

    /**
     * @ORM\OneToMany(targetEntity="IODevice", mappedBy="user", cascade={"persist"})
     **/
    private $iodevices;

    /**
     * @ORM\OneToMany(targetEntity="Schedule", mappedBy="user", cascade={"persist"})
     **/
    private $schedules;

    /**
     * @ORM\OneToMany(targetEntity="IODeviceChannelGroup", mappedBy="user", cascade={"persist"})
     **/
    private $channelGroups;

    /**
     * @ORM\OneToMany(targetEntity="AuditEntry", mappedBy="user", cascade={"persist"})
     **/
    private $auditEntries;

    /**
     * @ORM\Column(name="iodevice_reg_enabled", type="utcdatetime", nullable=true)
     * @Groups({"basic"})
     */
    private $ioDevicesRegistrationEnabled;

    /**
     * @ORM\Column(name="client_reg_enabled", type="utcdatetime", nullable=true)
     * @Groups({"basic"})
     */
    private $clientsRegistrationEnabled;

    /**
     * @ORM\Column(name="rules_agreement", type="boolean", options={"default"=false})
     * @Assert\NotNull()
     */
    private $rulesAgreement = false;

    /**
     * @ORM\Column(name="cookies_agreement", type="boolean", options={"default"=false})
     * @Assert\NotNull()
     */
    private $cookiesAgreement = false;

    public function __construct() {
        $this->limitAid = 10;
        $this->limitLoc = 10;
        $this->limitIoDev = 100;
        $this->limitClientApp = 200;
        $this->limitSchedule = 20;
        $this->limitChannelGroup = 20;
        $this->limitChannelPerGroup = 10;
        $this->accessids = new ArrayCollection();
        $this->locations = new ArrayCollection();
        $this->iodevices = new ArrayCollection();
        $this->schedules = new ArrayCollection();
        $this->clientApps = new ArrayCollection();
        $this->salt = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
        $this->regDate = new \DateTime();
        $this->passwordRequestedAt = null;
        $this->enabled = false;
        $this->setTimezone(null);
    }

    public function getId() {
        return $this->id;
    }

    public function getUsername() {
        return $this->email;
    }

    public function getRecaptcha() {
        return $this->recaptcha;
    }

    public function setRecaptcha($recaptcha) {
        $this->recaptcha = $recaptcha;
    }

    public function getSalt() {
        return $this->salt;
    }

    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;

        return $this;
    }

    public function getPassword() {
        return null === $this->password ? $this->legacyPassword : $this->password;
    }

    public function setPassword($password) {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword() {
        return $this->plainPassword;
    }

    public function setPlainPassword($password) {
        $this->plainPassword = $password;
        return $this;
    }

    public function eraseCredentials() {
        $this->plainPassword = null;
    }

    /**
     * Gets the registration time.
     *
     * @return \DateTime
     */
    public function getRegDate() {
        return $this->regDate;
    }

    public function getToken() {
        return $this->token;
    }

    public function genToken() {
        $bytes = false;

        if (function_exists('openssl_random_pseudo_bytes')) {
            $crypto_strong = true;
            $bytes = openssl_random_pseudo_bytes(32, $crypto_strong);
        }

        if ($bytes === false) {
            $logger = $this->get('logger');

            if ($logger !== null) {
                $logger->info('OpenSSL did not produce a secure random number.');
            }

            $bytes = hash('sha256', uniqid(mt_rand(), true), true);
        }

        $this->setToken(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='));
    }

    public function setToken($token) {
        $this->token = $token;
        return $this;
    }

    public function setPasswordRequestedAt(\DateTime $date = null) {
        $this->passwordRequestedAt = $date;
        return $this;
    }

    /**
     * Gets the timestamp that the user requested a password reset.
     *
     * @return null|\DateTime
     */
    public function getPasswordRequestedAt() {
        return $this->passwordRequestedAt;
    }

    public function getRoles() {
        return ['ROLE_USER'];
    }

    public function isEnabled() {
        return $this->enabled;
    }

    public function setEnabled($boolean) {
        $this->enabled = (Boolean)$boolean;

        return $this;
    }

    public function isAccountNonExpired() {
        return true;
    }

    public function isAccountNonLocked() {
        return true;
    }

    public function isCredentialsNonExpired() {
        return true;
    }

    public function getAccessIDS() {
        return $this->accessids;
    }

    /** @return Collection|ClientApp[] */
    public function getClientApps() {
        return $this->clientApps;
    }

    /** @return Collection|Selectable|IODeviceChannel[] */
    public function getChannels() {
        return $this->channels;
    }

    /** @return Collection|IODeviceChannelGroup[] */
    public function getChannelGroups() {
        return $this->channelGroups;
    }

    /** @return Collection|Location[] */
    public function getLocations(): Collection {
        return $this->locations;
    }

    /** @return IODevice[] */
    public function getIODevices() {
        return $this->iodevices;
    }

    public function getLimitAid() {
        return $this->limitAid;
    }

    public function getLimitLoc() {
        return $this->limitLoc;
    }

    public function getLimitChannelGroup(): int {
        return $this->limitChannelGroup;
    }

    public function getLimitChannelPerGroup(): int {
        return $this->limitChannelPerGroup;
    }

    public function getTimezone() {
        return $this->timezone;
    }

    public function setTimezone($timezone) {
        try {
            new \DateTimeZone($timezone);
            $this->timezone = $timezone;
        } catch (\Exception $e) {
            $this->timezone = date_default_timezone_get();
        }
    }

    public function getLimitSchedule() {
        return $this->limitSchedule;
    }

    /** Schedule[] */
    public function getSchedules() {
        return $this->schedules;
    }

    public function setSchedules($schedules) {
        $this->schedules = $schedules;
    }

    public function isLimitScheduleExceeded() {
        return $this->getLimitSchedule() > 0 && count($this->getSchedules()) >= $this->getLimitSchedule();
    }

    /** @return \DateTime|null */
    public function getClientsRegistrationEnabled() {
        if ($this->clientsRegistrationEnabled) {
            $now = new \DateTime();
            if ($now->getTimestamp() > $this->clientsRegistrationEnabled->getTimestamp()) {
                $this->clientsRegistrationEnabled = null;
            }
        }
        return $this->clientsRegistrationEnabled;
    }

    public function enableClientsRegistration(int $forHowLongInSeconds) {
        $this->clientsRegistrationEnabled = new \DateTime('@' . (time() + $forHowLongInSeconds));
    }

    public function disableClientsRegistration() {
        $this->clientsRegistrationEnabled = null;
    }

    /** @return \DateTime|null */
    public function getIoDevicesRegistrationEnabled() {
        if ($this->ioDevicesRegistrationEnabled) {
            $now = new \DateTime();
            if ($now->getTimestamp() > $this->ioDevicesRegistrationEnabled->getTimestamp()) {
                $this->ioDevicesRegistrationEnabled = null;
            }
        }
        return $this->ioDevicesRegistrationEnabled;
    }

    public function enableIoDevicesRegistration(int $forHowLongInSeconds) {
        $this->ioDevicesRegistrationEnabled = new \DateTime('@' . (time() + $forHowLongInSeconds));
    }

    public function disableIoDevicesRegistration() {
        $this->ioDevicesRegistrationEnabled = null;
    }

    public function getRulesAgreement(): bool {
        return $this->rulesAgreement;
    }

    public function agreeOnRules(): User {
        $this->rulesAgreement = true;
        return $this;
    }

    public function agreeOnCookies(): User {
        $this->cookiesAgreement = true;
        return $this;
    }

    public function getCookiesAgreement(): bool {
        return $this->cookiesAgreement;
    }

    public function hasLegacyPassword(): bool {
        return null !== $this->legacyPassword;
    }

    public function clearLegacyPassword() {
        $this->legacyPassword = null;
    }

    public function getEncoderName() {
        if ($this->hasLegacyPassword()) {
            return 'legacy_encoder';
        }
        return null; // uses the default encoder
    }

    public function fill(array $data) {
        $this->setEmail($data['email']);
        $this->setTimezone($data['timezone']);
        $this->setPlainPassword($data['password']);
    }
}
