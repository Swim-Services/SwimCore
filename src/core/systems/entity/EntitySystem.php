<?php

namespace core\systems\entity;

use Closure;
use core\systems\entity\entities\Actor;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use core\systems\System;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionException;

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

  public function init(): void
  {
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
   * @brief called when a scene is being exited, delete all entities within that scene
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
   * @breif Taken from Customies, call this function when you register a new derived Actor class (only has to happen once per custom entity class on server init)
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