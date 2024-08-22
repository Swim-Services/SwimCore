<?php

namespace core\systems\entity;

use core\systems\entity\entities\CustomEntity;
use core\systems\scene\Scene;
use pocketmine\entity\Entity;

abstract class Behavior
{

  protected Entity|CustomEntity $parent;
  protected ?Scene $scene;

  public function __construct(Entity|CustomEntity $customEntity, ?Scene $scene = null)
  {
    $this->parent = $customEntity;
    $this->scene = $scene;
  }

  abstract public function init();

  abstract public function updateSecond();

  abstract public function updateTick();

  abstract public function exit();

  /**
   * @return Entity|CustomEntity
   */
  public function getParent(): Entity|CustomEntity
  {
    return $this->parent;
  }

  /**
   * @param CustomEntity|Entity $parent
   */
  public function setParent(Entity|CustomEntity $parent): void
  {
    $this->parent = $parent;
  }

  /**
   * @return ?Scene
   */
  public function getScene(): ?Scene
  {
    return $this->scene ?? null;
  }

  /**
   * @param Scene $scene
   */
  public function setScene(Scene $scene): void
  {
    $this->scene = $scene;
  }

}