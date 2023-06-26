<?php

namespace SuplaBundle\Entity\Main\Listeners;

use Doctrine\ORM\Mapping as ORM;
use SuplaBundle\Entity\Main\Scene;
use SuplaBundle\Supla\SuplaServerAware;

class SceneEntityListener {
    use SuplaServerAware;

    /** @ORM\PostPersist */
    public function postPersist(Scene $scene) {
        $this->suplaServer->userAction('ON-SCENE-ADDED', $scene->getId());
    }

    /** @ORM\PostUpdate */
    public function postUpdate(Scene $scene) {
        $this->suplaServer->userAction('ON-SCENE-CHANGED', $scene->getId());
    }

    /** @ORM\PreRemove */
    public function preRemove(Scene $scene) {
        $this->suplaServer->userAction('ON-SCENE-REMOVED', $scene->getId());
    }
}
