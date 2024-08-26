<?php

namespace core\systems\scene;

use core\systems\entity\EntitySystem;
use core\systems\player\SwimPlayer;
use core\systems\System;
use Exception;
use FilesystemIterator;
use jackmd\scorefactory\ScoreFactoryException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Filesystem\Path;

class SceneSystem extends System
{

  /**
   * @var Scene[]
   * key is string scene name
   */
  private array $scenes;

  // needed for spawning specific entities in a scene to player when they join or leaves a scene
  private EntitySystem $entitySystem;

  public function init(): void
  {
    $this->entitySystem = $this->core->getSystemManager()->getEntitySystem();

    // all non-abstract scenes that are marked as autoload will be automatically loaded
    $this->loadPersistentScenes();

    // init each scene
    foreach ($this->scenes as $scene) {
      $scene->init();
    }
  }

  private function loadPersistentScenes(): void
  {
    $scenesDir = Path::canonicalize(Path::join(__DIR__, '..', '..', 'scenes')); // back 2 directories hence the double '..'
    if (is_dir($scenesDir)) {
      $this->loadSceneScripts($scenesDir);
    } else {
      echo "Error: " . $scenesDir . " not found\n";
    }
  }

  private function loadSceneScripts(string $directory): void
  {
    echo "Loading Persistent Scene Scripts from: " . $directory . "\n";
    try {
      $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
      foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
          $relativePath = Path::makeRelative($file->getPathname(), $directory);
          $relativePath = str_replace('/', '\\', $relativePath); // Ensure correct namespace separators
          $relativePath = str_replace('.php', '', $relativePath); // Remove the .php extension

          // Construct the full class name with the appropriate namespace
          $fullClassName = '\\core\\scenes\\' . $relativePath;
          if (class_exists($fullClassName)) {
            $reflectionClass = new ReflectionClass($fullClassName);
            if ($reflectionClass->isSubclassOf(Scene::class) && !$reflectionClass->isAbstract()) { // must derive from Scene and not be abstract
              if ($fullClassName::AutoLoad()) {
                // Extract the short class name which will be the scene's name and array key
                $className = $reflectionClass->getShortName();
                echo "Registering Persistent Scene: " . $fullClassName . " | name: " . $className . "\n";
                $this->scenes[$className] = new $fullClassName($this->core, $className);
              }
            }
          } else {
            echo "Error: Persistent Scene class failed to register: " . $fullClassName . "\n";
          }
        }
      }
    } catch (Exception $e) {
      echo "Error while loading persistent scenes: " . $e->getMessage() . "\n";
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
   * @throws ScoreFactoryException
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

  // for when a player leaves the server
  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    $scene = $swimPlayer->getSceneHelper()?->getScene();
    $scene?->removePlayer($swimPlayer);
  }

  public function getScenes(): array
  {
    return $this->scenes;
  }

  // specific random crap here:

  /**
   * @return int
   * @breif returns the amount of players in duel scenes on the server
   */
  public function getInDuelsCount(): int
  {
    $count = 0;
    foreach ($this->scenes as $scene) {
      if ($scene->isDuel()) {
        $count += $scene->getPlayerCount();
      }
    }

    return $count;
  }

  /**
   * @return int
   * @breif returns the amount of players in ffa scenes on the server
   */
  public function getInFFACount(): int {
    $count = 0;
    foreach ($this->scenes as $scene) {
      if ($scene->isFFA()) {
        $count += $scene->getPlayerCount();
      }
    }

    return $count;
  }

  /**
   * @return int
   * @breif returns the amount of players queued on the server (count of players in the queue scene)
   */
  public function getQueuedCount(): int
  {
    $queueScene = $this->getScene("Queue");
    if ($queueScene) {
      return $queueScene->getPlayerCount();
    }

    return 0;
  }

  /**
   * @param string $classPath
   * @return int
   * @breif Returns how many of a scene type are active. This is not strict,
   *        so it will include scenes that derive from the class path if applicable.
   */
  public function getSceneInstanceOfCount(string $classPath): int
  {
    $count = 0;

    foreach ($this->scenes as $scene) {
      if ($scene instanceof $classPath) $count++;
    }

    return $count;
  }

}