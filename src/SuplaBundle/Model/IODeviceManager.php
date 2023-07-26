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

namespace SuplaBundle\Model;

use Doctrine\Persistence\ManagerRegistry;
use SuplaBundle\Enums\ChannelFunction;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;

class IODeviceManager {
    protected $translator;
    protected $doctrine;
    protected $sec;

    public function __construct(
        TranslatorInterface $translator,
        ManagerRegistry $doctrine,
        TokenStorageInterface $security_token
    ) {
        $this->translator = $translator;
        $this->doctrine = $doctrine;
        $this->sec = $security_token;
    }

    /** @deprecated */
    public function channelFunctionToString($func) {
        if (!($func instanceof ChannelFunction)) {
            $func = new ChannelFunction(intval($func));
        }
        $result = $func->getCaption();
        return $this->translator->trans($result);
    }
}
