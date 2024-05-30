<?php

namespace core\systems\scene\managers;

use pocketmine\entity\object\ItemEntity;

class DroppedItemManager
{

  /**
   * @var ItemEntity[]
   */
  private array $droppedItems = array();

  public function addDroppedItem(ItemEntity $entity): void
  {
    $entity->setDespawnDelay(-1); // do not de-spawn ever
    $this->droppedItems[$entity->getId()] = $entity;
  }

  public function removeDroppedItem(ItemEntity $entity): void
  {
    unset($this->droppedItems[$entity->getId()]);
  }

  public function despawnAll(): void
  {
    foreach ($this->droppedItems as $item) {
      $item->kill();
    }
    // clear
    $this->droppedItems = array();
  }

}