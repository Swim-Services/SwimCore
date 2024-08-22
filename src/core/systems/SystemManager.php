<?php

namespace core\systems;

use core\SwimCore;
use core\systems\entity\EntitySystem;
use core\systems\event\EventSystem;
use core\systems\map\MapsData;
use core\systems\party\PartiesSystem;
use core\systems\player\PlayerSystem;
use core\systems\player\SwimPlayer;
use core\systems\scene\SceneSystem;
use ReflectionException;

class SystemManager
{

  private array $systems;
  private SwimCore $core;

  private PlayerSystem $playerSystem;
  private SceneSystem $sceneSystem;
  private PartiesSystem $partySystem;
  private MapsData $mapsData;
  private EventSystem $eventSystem;
  private EntitySystem $entitySystem;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
  }

  public function getPlayerSystem(): PlayerSystem
  {
    return $this->playerSystem;
  }

  public function getSceneSystem(): SceneSystem
  {
    return $this->sceneSystem;
  }

  public function getPartySystem(): PartiesSystem
  {
    return $this->partySystem;
  }

  public function getMapsData(): MapsData
  {
    return $this->mapsData;
  }

  public function getEventSystem(): EventSystem
  {
    return $this->eventSystem;
  }

  public function getEntitySystem(): EntitySystem
  {
    return $this->entitySystem;
  }

  // create all the systems

  /**
   * @throws ReflectionException
   */
  public function init(): void
  {
    $this->playerSystem = new PlayerSystem($this->core);
    $this->systems[] = $this->playerSystem;

    $this->sceneSystem = new SceneSystem($this->core);
    $this->systems[] = $this->sceneSystem;

    $this->partySystem = new PartiesSystem($this->core);
    $this->systems[] = $this->partySystem;

    $this->mapsData = new MapsData($this->core);
    $this->systems[] = $this->mapsData;

    $this->eventSystem = new EventSystem($this->core);
    $this->systems[] = $this->eventSystem;

    $this->entitySystem = new EntitySystem($this->core);
    $this->systems[] = $this->entitySystem;

    // then init all the systems
    foreach ($this->systems as $system) {
      $system->init();
    }
  }

  public function updateTick(): void
  {
    foreach ($this->systems as $system) {
      $system->updateTick();
    }
  }

  public function updateSecond(): void
  {
    foreach ($this->systems as $system) {
      $system->updateSecond();
    }
  }

  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    foreach ($this->systems as $system) {
      $system->handlePlayerLeave($swimPlayer);
    }
  }

  public function exit(): void
  {
    foreach ($this->systems as $system) {
      $system->exit();
    }
    $this->systems = [];
  }

}