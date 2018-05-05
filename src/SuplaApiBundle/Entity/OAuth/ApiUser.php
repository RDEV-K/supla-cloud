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

namespace SuplaApiBundle\Entity\OAuth;

use Doctrine\ORM\Mapping as ORM;
use SuplaBundle\Entity\AccessID;
use SuplaBundle\Entity\User as ParentUser;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="supla_oauth_user")
 */
class ApiUser implements AdvancedUserInterface {
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"basic"})
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="SuplaBundle\Entity\User")
     */
    protected $parent;

    /**
     * @ORM\Column(name="password", type="string", length=64)
     */
    protected $password;

    /**
     * @ORM\Column(name="enabled", type="boolean")
     * @Assert\NotNull()
     * @Groups({"basic"})
     */
    protected $enabled;

    /**
     * @ORM\ManyToOne(targetEntity="SuplaBundle\Entity\AccessID")
     */
    protected $accessId;

    public function __construct(ParentUser $parent) {
        $this->enabled = false;
        $this->parent = $parent;
        $this->password = $this->generateNewPassword();
    }

    public function generateNewPassword() {
        return base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
    }

    public function getId() {
        return $this->id;
    }

    public function getParentUser() {
        return $this->parent;
    }

    /** @Groups({"basic"}) */
    public function getUsername() {
        return 'api_' . $this->id;
    }

    public function getSalt() {
        return $this->parent instanceof ParentUser ? $this->parent->getSalt() : null;
    }

    public function getPassword() {
        return $this->isEnabled() ? $this->password : $this->generateNewPassword();
    }

    public function setPassword($password) {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials() {
    }

    public function getRoles() {
        return ['RESTAPI_USER'];
    }

    public function isEnabled() {
        return $this->parent instanceof ParentUser ? $this->enabled : false;
    }

    public function setEnabled($enabled) {
        $this->enabled = $enabled;
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

    public function getAccessId() {
        return $this->accessId;
    }

    public function setAccessId(AccessID $accessId) {
        $this->accessId = $accessId;
    }
}
