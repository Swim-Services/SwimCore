<?php

namespace core\systems\entity;

use pocketmine\entity\Entity;

class EntityBehaviorManager
{

  /**
   * @var Behavior[]
   * key is behavior class name string
   */
  private array $behaviorMap = array();

  protected Entity $parent;

  public function __construct(Entity $parent)
  {
    $this->parent = $parent;
  }

  /**
   * @return Entity
   */
  public function getParent(): Entity
  {
    return $this->parent;
  }

  public function init(): void
  {
    foreach ($this->behaviorMap as $component) {
      $component->init();
    }
  }

  public function updateSecond(): void
  {
    foreach ($this->behaviorMap as $component) {
      $component->updateSecond();
    }
  }

  public function updateTick(): void
  {
    foreach ($this->behaviorMap as $component) {
      $component->updateTick();
    }
  }

  public function exit(): void
  {
    foreach ($this->behaviorMap as $component) {
      $component->exit();
    }
  }

  public function addBehavior(Behavior $behavior): void
  {
    $this->behaviorMap[Behavior::class] = $behavior;
  }

  public function hasBehavior(string $className): bool
  {
    return isset($this->behaviorMap[$className]);
  }

  public function getBehavior(string $className): ?Behavior
  {
    return $this->behaviorMap[$className] ?? null;
  }

}