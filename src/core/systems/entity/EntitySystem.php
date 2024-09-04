<?php

namespace core\systems\entity;

use Closure;
use core\systems\entity\entities\Actor;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use core\systems\System;
use Exception;
use FilesystemIterator;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\World;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Filesystem\Path;

class EntitySystem extends System
{

  /**
   * @var Actor[]
   * key is int id of the entity
   */
  private array $entities = array();

  public function registerEntity(Actor $entity, bool $callInit = true): void
  {
    $this->entities[$entity->getId()] = $entity;
    if ($callInit) {
      $entity->init();
    }
  }

  /**
   * @throws ReflectionException
   */
  public function deregisterEntity(Actor $entity, bool $deSpawn = true, bool $kill = false, bool $callExit = true): void
  {
    if ($callExit) $entity->exit();
    if ($kill) $entity->kill();
    if ($deSpawn) $entity->deSpawnActorFromAll();
    unset($this->entities[$entity->getId()]);
  }

  public function playerLeavingScene(Player $player, Scene $scene): void
  {
    if (!$player->isOnline()) return;  // avoid login exception
    foreach ($this->entities as $entity) {
      if ($entity->getParentScene() === $scene) {
        $entity->deSpawnActorFrom($player);
      }
    }
  }

  public function playerJoiningScene(Player $player, Scene $scene): void
  {
    foreach ($this->entities as $entity) {
      if ($entity->getParentScene() === $scene) {
        $entity->spawnTo($player);
      }
    }
  }

  /**
   * @throws ReflectionException
   */
  public function init(): void
  {
    $this->deserialize();

    // Looking back, we don't actually have any entities to init, as system init is called at tick 0.
    // And entities will automatically init on their own on registration when instantiated.
    foreach ($this->entities as $entity) {
      $entity->init();
    }
  }

  /**
   * @throws ReflectionException
   */
  public function updateTick(): void
  {
    foreach ($this->entities as $entity) {
      $entity->updateTick();
    }
  }

  public function updateSecond(): void
  {
    foreach ($this->entities as $entity) {
      $entity->updateSecond();
    }
  }

  public function exit(): void
  {
    foreach ($this->entities as $entity) {
      $entity->exit();
    }
  }

  public function eventMessage(string $message, mixed $args = null): void
  {
    foreach ($this->entities as $entity) {
      $entity->event($message, $args);
    }
  }

  /**
   * @brief Called when a scene is being exited, exits and de-spawns + deletes all entities within that scene from memory.
   *  Reading stuff like this makes me think it would be a lot better to have some kind of sub scene entity handler.
   *  This is a full entity list iteration when we only care about the entities in the parameter scene.
   */
  public function sceneExiting(Scene $scene): void
  {
    foreach ($this->entities as $key => $entity) {
      if ($entity->getParentScene() === $scene) {
        $entity->exit();
        $entity->flagForDespawn();
        unset($this->entities[$key]);
      }
    }
  }

  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    // no op
  }

  /**
   * @throws ReflectionException
   * @breif Deserializes all valid actor script prefabs and loads them into the entity factory
   */
  private function deserialize(): void
  {
    $prefabsDir = Path::canonicalize(Path::join(__DIR__, '..', '..', 'custom', 'prefabs')); // back 2 directories hence the double '..'
    if (is_dir($prefabsDir)) {
      $this->loadActorScripts($prefabsDir);
    } else {
      echo "Error: " . $prefabsDir . " not found\n";
    }
  }

  /**
   * @throws ReflectionException
   */
  private function loadActorScripts(string $directory): void
  {
    echo "Loading Actor Scripts from: " . $directory . "\n";
    try {
      $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
      foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
          $relativePath = Path::makeRelative($file->getPathname(), $directory);
          $relativePath = str_replace('/', '\\', $relativePath); // Ensure correct namespace separators
          $relativePath = str_replace('.php', '', $relativePath); // Remove the .php extension
          // Construct the full class name with the appropriate namespace
          $fullClassName = '\\core\\custom\\prefabs\\' . $relativePath;
          if (class_exists($fullClassName)) {
            $reflectionClass = new ReflectionClass($fullClassName);
            if ($reflectionClass->isSubclassOf(Actor::class) && !$reflectionClass->isAbstract()) { // must derive from Actor and not be abstract
              // type must be something unique that isn't just an NPC
              $type = $fullClassName::getNetworkTypeId();
              if ($type != EntityIds::NPC) {
                echo "Registering Custom Entity: " . $fullClassName . " | Type: " . $type . "\n";
                $this->registerCustomEntity($fullClassName, $type);
              }
            }
          } else {
            echo "Error: Actor class failed to register: " . $fullClassName . "\n";
          }
        }
      }
    } catch (Exception $e) {
      echo "Error while loading entities: " . $e->getMessage() . "\n";
    }
  }

  /**
   * @throws ReflectionException
   * @breif Taken from Customies, call this function to register a new derived Actor class (only has to happen once per custom entity class on server init)
   */
  public function registerCustomEntity(string $className, string $identifier, ?Closure $creationFunc = null, string $behaviourId = ""): void
  {
    EntityFactory::getInstance()->register($className, $creationFunc ?? static function (World $world, CompoundTag $nbt) use ($className): Entity {
        return new $className(EntityDataHelper::parseLocation($nbt, $world), $nbt);
      }, [$identifier]);
    $this->updateStaticPacketCache($identifier, $behaviourId);
  }

  /**
   * @throws ReflectionException
   * @brief Taken from Customies
   */
  private function updateStaticPacketCache(string $identifier, string $behaviourId): void
  {
    $instance = StaticPacketCache::getInstance();
    $property = (new ReflectionClass($instance))->getProperty("availableActorIdentifiers");
    /** @var AvailableActorIdentifiersPacket $packet */
    $packet = $property->getValue($instance);
    /** @var CompoundTag $root */
    $root = $packet->identifiers->getRoot();
    ($root->getListTag("idlist") ?? new ListTag())->push(CompoundTag::create()
      ->setString("id", $identifier)
      ->setString("bid", $behaviourId));
    $packet->identifiers = new CacheableNbt($root);
  }

}