<?php

namespace core\systems\event;

use core\systems\player\SwimPlayer;
use core\systems\System;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\utils\TextFormat;

// not to be confused with the behavior events, this is for stuff like scrims and survival games, big server games

class EventSystem extends System
{

  /**
   * @var ServerGameEvent[]
   * key is game event name string
   */
  private array $inQueueEvents = array();

  /**
   * @var ServerGameEvent[]
   * key is game event name string
   */
  private array $inProgressEvents = array();

  // removes from the in queue event array and into the in progress events
  public function eventStarted(ServerGameEvent $event): void
  {
    $name = $event->getInternalName();
    unset($this->inQueueEvents[$name]);
    $this->inProgressEvents[$name] = $event;
  }

  public function eventNameExists(string $eventName): bool
  {
    // this could probably be a simple isset() check actually
    return in_array($eventName, array_keys($this->inQueueEvents)) || in_array($eventName, array_keys($this->inProgressEvents));
  }

  public function createEvent(ServerGameEvent $event): void
  {
    $this->inQueueEvents[$event->getInternalName()] = $event;
    $event->eventCreated();
  }

  public function getInQueueEventsCount(): int
  {
    return count($this->inQueueEvents);
  }

  public function getInProgressEventsCount(): int
  {
    return count($this->inProgressEvents);
  }

  /**
   * @return ServerGameEvent[]
   */
  public function getInQueueEvents(): array
  {
    return $this->inQueueEvents;
  }

  /**
   * @return ServerGameEvent[]
   */
  public function getInProgressEvents(): array
  {
    return $this->inProgressEvents;
  }

  public function init(): void
  {
    // TODO: Implement init() method.
  }

  public function updateTick(): void
  {
    // TODO: Implement updateTick() method.
  }

  /**
   * @throws ScoreFactoryException
   */
  public function updateSecond(): void
  {
    foreach ($this->inQueueEvents as $event) {
      $event->updateSecond();
    }

    foreach ($this->inProgressEvents as $event) {
      $event->updateSecond();
    }
  }

  public function exit(): void
  {
    foreach ($this->inQueueEvents as $event) {
      $event->exit();
    }

    foreach ($this->inProgressEvents as $event) {
      $event->exit();
    }
  }

  // returns the second it was told that it removed the player from the event

  /**
   * @throws JsonException
   * @throws ScoreFactoryException
   */
  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    foreach ($this->inQueueEvents as $event) {
      if ($this->leave($swimPlayer, $event)) return;
    }

    foreach ($this->inProgressEvents as $event) {
      if ($this->leave($swimPlayer, $event)) return;
    }
  }

  // returns true or false if it removed them

  /**
   * @throws JsonException
   * @throws ScoreFactoryException
   */
  private function leave(SwimPlayer $swimPlayer, ServerGameEvent $event): bool
  {
    $removed = $event->removeIfContains($swimPlayer);
    if ($removed) {
      $event->removeMessage($swimPlayer); // tell that event they left
      return true;
    }
    return false;
  }

  // below here are functions to create and register server events into this system

  /**
   * @throws ScoreFactoryException
   */
  public function registerEvent(SwimPlayer $swimPlayerHost, ServerGameEvent $event): void
  {
    if (!$event::canCreate()) {
      $swimPlayerHost->sendMessage(TextFormat::YELLOW . "The max amount of Survival Games event instances is currently running. Try again later.");
      return;
    }

    // update instance count
    $event::setInstances($event::getInstances() + 1);

    // create
    $this->createEvent($event);
    $swimPlayerHost->getSceneHelper()->setNewScene("EventQueue");
  }

  public function removeEvent(ServerGameEvent $event): void
  {
    // must set the pointers to null manually (cringe)
    foreach ($event->getPlayers() as $player) {
      $player->getSceneHelper()->setEvent(null);
    }

    $name = $event->getInternalName();
    if (isset($this->inQueueEvents[$name])) {
      unset($this->inQueueEvents[$name]);
    } else if (isset($this->inProgressEvents[$name])) {
      unset($this->inProgressEvents[$name]);
    }
  }

}