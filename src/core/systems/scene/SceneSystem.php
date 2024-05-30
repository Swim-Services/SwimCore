<?php

namespace core\systems\scene;

use core\scenes\hub\EventQueue;
use core\scenes\hub\GodMode;
use core\scenes\hub\Hub;
use core\scenes\hub\Loading;
use core\scenes\hub\Queue;
use core\systems\entity\EntitySystem;
use core\systems\player\SwimPlayer;
use core\systems\System;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use ReflectionException;

class SceneSystem extends System
{

  /**
   * @var Scene[]
   * key is string scene name
   */
  private array $scenes;

  // add in all scenes (FFA, Hub, etc)
  private EntitySystem $entitySystem;

  /**
   * @throws ReflectionException
   * @brief nodebuff and midfight ffa is disabled for this lightweight engine build, but the scenes are fully implemented,
   * it's just a matter of setting up the worlds in each scene and uncommenting the instantiations.
   * You will have these same missing worlds you will need to fix and specify in json for the misc duels.
   */
  public function init(): void
  {
    $this->entitySystem = $this->core->getSystemManager()->getEntitySystem();

    $this->scenes['Hub'] = new Hub($this->core, "Hub");
    $this->scenes['GodMode'] = new GodMode($this->core, "GodMode");
    $this->scenes['Loading'] = new Loading($this->core, "Loading");
    // $this->scenes['NodebuffFFA'] = new NodebuffFFA($this->core, "NodebuffFFA"); // disabled for this lightweight build
    // $this->scenes['MidFightFFA'] = new MidFightFFA($this->core, "MidFightFFA"); // disabled for this lightweight build
    $this->scenes['Queue'] = new Queue($this->core, 'Queue');
    $this->scenes['EventQueue'] = new EventQueue($this->core, "EventQueue");

    // init each scene
    foreach ($this->scenes as $scene) {
      $scene->init();
    }
  }

  /**
   * @breif calls the scene's exit function and then removes from the array, also calls scene exiting from the entity system
   */
  public function removeScene(string $sceneName): void
  {
    if (isset($this->scenes[$sceneName])) {
      $this->scenes[$sceneName]->exit();
      $this->entitySystem->sceneExiting($this->scenes[$sceneName]); // entity system must delete all entities belonging to that scene
      unset($this->scenes[$sceneName]);
    }
  }

  public function registerScene(Scene $scene, string $sceneName, bool $callInit = true): void
  {
    $this->scenes[$sceneName] = $scene;
    if ($callInit) {
      $scene->init();
    }
  }

  public function getScene(string $sceneName): ?Scene
  {
    return $this->scenes[$sceneName] ?? null;
  }

  /**
   * @throws ScoreFactoryException|JsonException
   */
  public function setScene(SwimPlayer $player, Scene $newScene)
  {
    $currentScene = $player->getSceneHelper()->getScene();

    // same scene check which instead does a restart
    if ($newScene === $currentScene) {
      $currentScene->restart($player);
      return;
    }

    // remove from current scene if it exists
    $currentScene->removePlayer($player);
    $this->entitySystem->playerLeavingScene($player, $currentScene);
    $player->cleanPlayerState(); // then clean the player's state

    // then add to new scene
    $player->getSceneHelper()->setScene($newScene); // cache scene pointer
    $newScene->addPlayer($player);
    $this->entitySystem->playerJoiningScene($player, $newScene);
  }

  // update all scenes each tick
  public function updateTick(): void
  {
    foreach ($this->scenes as $scene) {
      $scene->updateTick();
    }
  }

  // update all scenes each second
  public function updateSecond(): void
  {
    foreach ($this->scenes as $scene) {
      $scene->updateSecond();
    }
  }

  // exit all scenes
  public function exit(): void
  {
    foreach ($this->scenes as $scene) {
      $scene->exit();
    }
    $this->scenes = []; // clear
  }

  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    $scene = $swimPlayer->getSceneHelper()?->getScene();
    $scene?->removePlayer($swimPlayer);
  }

  public function getScenes(): array
  {
    return $this->scenes;
  }

}