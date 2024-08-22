<?php

namespace core\systems\player\components\behaviors;

use core\systems\player\SwimPlayer;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;

class EventBehaviorComponentManager
{

  /**
   * @var EventBehaviorComponent[]
   */
  private array $components = array();

  public function registerComponent(EventBehaviorComponent $component): void
  {
    $this->components[] = $component;
    $component->init();
  }

  public function removeComponent(string $componentName, bool $callExit = true): void
  {
    // Find the key (index) of the component to be removed
    foreach ($this->components as $key => $component) {
      if ($component->getComponentName() === $componentName) {
        // Call exit method if required
        if ($callExit) {
          $component->exit();
        }
        // Unset the component using its key
        unset($this->components[$key]);
        // Return out of the loop once the component is found and removed
        return;
      }
    }
  }

  public function clear(): void
  {
    foreach ($this->components as $key => $component) {
      $component->clear();
      if ($component->isRemoveOnReset()) {
        $component->exit();
        unset($this->components[$key]);
      }
    }
  }

  // returns the first component with the passed in name
  public function getComponent(string $name): ?EventBehaviorComponent
  {
    foreach ($this->components as $component) {
      if ($component->getComponentName() === $name) {
        return $component;
      }
    }
    // not found
    return null;
  }

  public function updateSecond(): void
  {
    foreach ($this->components as $component) {
      if ($component->shouldUpdate()) {
        $component->updateSecond();
      }
    }
  }

  public function updateTick(): void
  {
    foreach ($this->components as $key => $component) {
      // Updating each component
      if ($component->shouldUpdate()) {
        $component->updateTick();
      }

      // Check if the component should be destroyed
      if ($component->isDestroyMe()) {
        // Exit the component
        $component->exit();
        // Unset the component directly from the original array
        unset($this->components[$key]);
      }
    }
  }

  public function eventMessage(Event $event, string $message, mixed $args = null): void
  {
    if (!empty($this->components)) {
      foreach ($this->components as $component) {
        if ($component->shouldUpdate()) {
          $component->eventMessage($event, $message, $args);
        }
      }
    }
  }

  public function event(Event $event, int $eventEnum): void
  {
    if (!empty($this->components)) {
      foreach ($this->components as $component) {
        if ($component->shouldUpdate()) {
          $component->event($event, $eventEnum);
        }
      }
    }
  }

  public function attackedPlayer(EntityDamageByEntityEvent $event, SwimPlayer $victim): void
  {
    if (!empty($this->components)) {
      foreach ($this->components as $component) {
        if ($component->shouldUpdate()) {
          $component->attackedPlayer($event, $victim);
        }
      }
    }
  }

}