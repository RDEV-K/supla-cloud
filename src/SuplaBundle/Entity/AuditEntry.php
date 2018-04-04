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

use Doctrine\ORM\Mapping as ORM;
use SuplaBundle\Enums\AuditedAction;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="SuplaBundle\Repository\AuditEntryRepository")
 * @ORM\Table(name="supla_audit", indexes={
 *     @ORM\Index(name="supla_audit_ipv4_idx", columns={"ipv4"}),
 *     @ORM\Index(name="supla_audit_created_at_idx", columns={"created_at"})
 * })
 */
class AuditEntry {
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(name="action", type="integer")
     */
    private $action;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="auditEntries")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
     */
    private $user;

    /**
     * @ORM\Column(name="created_at", type="utcdatetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(name="successful", type="boolean", options={"default"=true})
     */
    private $successful = true;

    /**
     * @ORM\Column(name="ipv4", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $ipv4;

    /**
     * @ORM\Column(name="text_param1", type="string", length=255, nullable=true)
     * @Groups({"basic"})
     */
    private $textParam1;

    /**
     * @ORM\Column(name="text_param2", type="string", length=255, nullable=true)
     * @Groups({"basic"})
     */
    private $textParam2;

    public function __construct(AuditedAction $action, $user, bool $successful, $textParam1, $textParam2) {
        $this->action = $action->getId();
        $this->user = $user;
        $this->successful = $successful;
        $this->textParam1 = $textParam1;
        $this->textParam2 = $textParam2;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getAction(): AuditedAction {
        return new AuditedAction($this->action);
    }

    /** @return User|null */
    public function getUser() {
        return $this->user;
    }

    public function getCreatedAt(): \DateTime {
        return $this->createdAt;
    }

    public function isSuccessful(): bool {
        return $this->successful;
    }

    public function getIpv4() {
        return $this->ipv4;
    }

    public function getTextParam1() {
        return $this->textParam1;
    }

    public function getTextParam2() {
        return $this->textParam2;
    }
}
