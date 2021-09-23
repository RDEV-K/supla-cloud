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

namespace SuplaBundle\Serialization;

use SuplaBundle\Entity\Scene;
use SuplaBundle\Repository\SceneRepository;

class SceneSerializer extends AbstractSerializer {
    /** @var SceneRepository */
    private $sceneRepository;

    public function __construct(SceneRepository $sceneRepository) {
        parent::__construct();
        $this->sceneRepository = $sceneRepository;
    }

    /**
     * @param Scene $scene
     * @inheritdoc
     */
    protected function addExtraFields(array &$normalized, $scene, array $context) {
        $normalized['userId'] = $scene->getUser()->getId();
        $normalized['locationId'] = $scene->getLocation()->getId();
        $normalized['functionId'] = $scene->getFunction()->getId();
        $normalized['userIconId'] = $scene->getUserIcon() ? $scene->getUserIcon()->getId() : null;
        if (!isset($normalized['relationsCount']) && $this->isSerializationGroupRequested('scene.relationsCount', $context)) {
            $normalized['relationsCount'] = $this->sceneRepository->find($scene->getId())->getRelationsCount();
        }
    }

    public function supportsNormalization($entity, $format = null) {
        return $entity instanceof Scene;
    }
}
