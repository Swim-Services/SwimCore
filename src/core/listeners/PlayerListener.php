<?php

namespace core\listeners;

use core\database\queries\ConnectionHandler;
use core\scenes\PvP;
use core\SwimCore;
use core\systems\player\PlayerSystem;
use core\systems\player\SwimPlayer;
use core\systems\SystemManager;
use core\Utils\BehaviorEventEnums;
use core\utils\InventoryUtil;
use core\utils\PositionHelper;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\block\BlockTypeIds;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\world\Position;

class PlayerListener implements Listener
{

  private SwimCore $core;
  private PlayerSystem $playerSystem;
  private SystemManager $systemManager;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->systemManager = $this->core->getSystemManager();
    $this->playerSystem = $this->systemManager->getPlayerSystem();
  }

  public function onPlayerConstructed(PlayerCreationEvent $event)
  {
    $event->setPlayerClass(SwimPlayer::class);
  }

  // join handling
  public function onJoin(PlayerJoinEvent $event)
  {
    /* @var SwimPlayer $player */
    $player = $event->getPlayer();
    // I have hunger disabled on the server because it causes unwanted bugs. Will have my own custom hunger system when needed.
    $player->getHungerManager()->setEnabled(false);
    // don't say anything in chat
    $event->setJoinMessage("");
    // set up player session
    $this->playerSystem->registerPlayer($player);
    $player->init($this->core);
    // handle the player joining by logging connection and checking for punishments, if all checks are passed the data and session will be started and loaded
    ConnectionHandler::handlePlayerJoin($player);
  }

  // leave handling
  public function onQuit(PlayerQuitEvent $event)
  {
    /* @var SwimPlayer $player */
    $player = $event->getPlayer();
    $event->setQuitMessage("§c[-] §e" . $player->getDisplayName());

    // send them back to spawn on leaving
    InventoryUtil::fullPlayerReset($player);
    $hub = $this->core->getServer()->getWorldManager()->getWorldByName("hub");
    $safeSpawn = $hub->getSafeSpawn();
    $player->teleport(new Position($safeSpawn->getX() + 0.5, $safeSpawn->getY(), $safeSpawn->getZ() + 0.5, $hub));

    // despawn player entity just in case
    $player->flagForDespawn();

    // have player leave each system
    $this->systemManager->handlePlayerLeave($player);
    $player->exit();
  }

  // chat handling (would we want to make this a scriptable event later on?)
  public function onChat(PlayerChatEvent $event)
  {
    /* @var SwimPlayer $player */
    $player = $event->getPlayer();
    $player->getChatHandler()->handleChat($event->getMessage());
    $event->cancel();
  }

  // scoped scene event callbacks

  // we have to parse all damage events in this single function because of how pm does polymorphism for damage events
  // for example, EntityDamageByEntityEvent or EntityDamageByChildEntityEvent triggers an EntityDamageEvent
  public function damageCallback(EntityDamageEvent $event)
  {
    // prevent switch hits
    if ($event->getModifier(EntityDamageEvent::MODIFIER_PREVIOUS_DAMAGE_COOLDOWN) < 0) {
      $event->cancel();
      return;
    }

    // disable suffocation
    if ($event->getCause() == EntityDamageEvent::CAUSE_SUFFOCATION || $event->getCause() == EntityDamageEvent::CAUSE_SUICIDE) {
      $event->cancel();
      return;
    }

    // this is really special case and weird
    if ($event->getCause() == EntityDamageEvent::CAUSE_ENTITY_EXPLOSION || $event->getCause() == EntityDamageEvent::CAUSE_BLOCK_EXPLOSION) {
      $player = $event->getEntity();
      if ($player instanceof SwimPlayer) {
        $player->event($event, BehaviorEventEnums::ENTITY_DAMAGE_EVENT);
        if ($event->isCancelled()) return;
        $player->getSceneHelper()?->getScene()->sceneEntityDamageEvent($event, $player);
      }
      return;
    }

    // check if hit by a child entity like a projectile
    if ($event instanceof EntityDamageByChildEntityEvent) {
      $player = $event->getEntity();
      if ($player instanceof SwimPlayer) {
        $player->event($event, BehaviorEventEnums::ENTITY_DAMAGE_BY_CHILD_ENTITY_EVENT);
        if ($event->isCancelled()) return;
        $player->getSceneHelper()?->getScene()->sceneEntityDamageByChildEntityEvent($event, $player);
      }
    } else if ($event instanceof EntityDamageByEntityEvent) { // check if normal melee damage
      $player = $event->getEntity();
      if ($player instanceof SwimPlayer) {

        $damager = $event->getDamager();
        if ($damager instanceof SwimPlayer) {
          $damager->getEventBehaviorComponentManager()->attackedPlayer($event, $player);
        }

        // then do the actual real events
        $player->event($event, BehaviorEventEnums::ENTITY_DAMAGE_BY_ENTITY_EVENT);
        if ($event->isCancelled()) return;
        $player->getSceneHelper()?->getScene()->sceneEntityDamageByEntityEvent($event, $player);
      }
    } else { // check if just generic damage like fall damage for example
      $player = $event->getEntity();
      if ($player instanceof SwimPlayer) {
        $player->event($event, BehaviorEventEnums::ENTITY_DAMAGE_EVENT);
        if ($event->isCancelled()) return;
        $player->getSceneHelper()?->getScene()->sceneEntityDamageEvent($event, $player);
      }
    }
  }

  public function itemDropCallback(PlayerDropItemEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT);
    if ($event->isCancelled()) return;
    $sp->getSceneHelper()?->getScene()->sceneItemDropEvent($event, $sp);
  }

  /**
   * @priority HIGHEST
   * @handleCancelled
   */
  public function itemUseCallback(PlayerItemUseEvent $event)
  {
    $event->uncancel(); // this should never be cancelled before this listener is hit, this side steps around the spectator item use event always being cancelled
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::PLAYER_ITEM_USE_EVENT);
    if ($event->isCancelled()) return;
    $sp->getSceneHelper()?->getScene()->sceneItemUseEvent($event, $sp);
  }

  // this could be expensive
  public function inventoryUseCallback(InventoryTransactionEvent $event)
  {
    /* @var SwimPlayer $player */
    $player = $event->getTransaction()->getSource();
    $player->event($event, BehaviorEventEnums::INVENTORY_TRANSACTION_EVENT);
    if ($event->isCancelled()) return;
    $player->getSceneHelper()?->getScene()->sceneInventoryUseEvent($event, $player);
  }

  public function chestOpenEvent(PlayerInteractEvent $event): void
  {
    $id = $event->getBlock()->getTypeId();
    if ($id == BlockTypeIds::CHEST || $id == BlockTypeIds::ENDER_CHEST || $id == BlockTypeIds::TRAPPED_CHEST) {
      /* @var SwimPlayer $player */
      $player = $event->getPlayer();
      $player->event($event, BehaviorEventEnums::PLAYER_INTERACT_EVENT);
      if ($event->isCancelled()) return;
      $player->getSceneHelper()?->getScene()->scenePlayerInteractEvent($event, $player);
    }
  }

  public function entityTeleportCallback(EntityTeleportEvent $event)
  {
    /* @var SwimPlayer $player */
    $player = $event->getEntity();
    $player->event($event, BehaviorEventEnums::ENTITY_TELEPORT_EVENT);
    if ($event->isCancelled()) return;
    $player->getSceneHelper()?->getScene()->sceneEntityTeleportEvent($event, $player);
  }

  public function playerConsumeCallback(PlayerItemConsumeEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::PLAYER_ITEM_CONSUME_EVENT);
    if ($event->isCancelled()) return;
    $sp->getSceneHelper()?->getScene()->scenePlayerConsumeEvent($event, $sp);
  }

  public function playerPickupItem(EntityItemPickupEvent $event)
  {
    $player = $event->getEntity();
    if (!($player instanceof SwimPlayer)) return;
    $player->event($event, BehaviorEventEnums::ENTITY_ITEM_PICKUP_EVENT);
    if ($event->isCancelled()) return;
    $player->getSceneHelper()?->getScene()->scenePlayerPickupItem($event, $player);
  }

  public function projectileLaunchCallback(ProjectileLaunchEvent $event)
  {
    $player = $event->getEntity()->getOwningEntity();
    if (!($player instanceof SwimPlayer)) return;
    $player->event($event, BehaviorEventEnums::PROJECTILE_LAUNCH_EVENT);
    if ($event->isCancelled()) return;
    $player->getSceneHelper()?->getScene()->sceneProjectileLaunchEvent($event, $player);
  }

  // this is intended for when a player's thrown projectile hits (be careful with this)
  public function projectileHitCallback(ProjectileHitEvent $event)
  {
    $player = $event->getEntity()->getOwningEntity();
    if (!($player instanceof SwimPlayer)) return;
    $player->event($event, BehaviorEventEnums::PROJECTILE_HIT_EVENT);
    $player->getSceneHelper()?->getScene()->sceneProjectileHitEvent($event, $player);
  }

  public function entityRegainHealthCallback(EntityRegainHealthEvent $event)
  {
    $player = $event->getEntity();
    if (!($player instanceof SwimPlayer)) return;
    $player->event($event, BehaviorEventEnums::ENTITY_REGAIN_HEALTH_EVENT);
    if ($event->isCancelled()) return;
    $player->getSceneHelper()?->getScene()->sceneEntityRegainHealthEvent($event, $player);
  }

  // this is intended for when the thrower hits an entity
  public function projectileHitEntityCallback(ProjectileHitEntityEvent $event)
  {
    $entityHit = $event->getEntityHit();
    if ($entityHit instanceof SwimPlayer) {
      $player = $event->getEntity();
      if (!($player instanceof SwimPlayer)) return;
      $player->event($event, BehaviorEventEnums::PROJECTILE_HIT_ENTITY_EVENT);
      $player->getSceneHelper()?->getScene()->sceneProjectileHitEntityEvent($event, $player);
    }
  }

  /* turned off for performance reasons, we only do this for chest interaction if you scroll up
  public function playerInteractCallback(PlayerInteractEvent $event)
  {

  }
  */

  public function entitySpawnCallback(EntitySpawnEvent $event)
  {
    $entity = $event->getEntity();
    $owner = $entity->getOwningEntity();
    if ($owner instanceof SwimPlayer) {
      $owner->event($event, BehaviorEventEnums::ENTITY_SPAWN_EVENT);
      $owner->getSceneHelper()?->getScene()->scenePlayerSpawnChildEvent($event, $owner, $entity);
    }
  }

  public function blockPlaceCallback(BlockPlaceEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::BLOCK_PLACE_EVENT);
    if ($event->isCancelled()) return;
    $sp->getSceneHelper()?->getScene()->sceneBlockPlaceEvent($event, $sp);
  }

  public function blockBreakCallback(BlockBreakEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::BLOCK_BREAK_EVENT);
    if ($event->isCancelled()) return;
    $sp->getSceneHelper()?->getScene()->sceneBlockBreakEvent($event, $sp);
    // desperate fix
    if (!$sp->getSceneHelper()) {
      $event->cancel();
    } else if (!$sp->getSceneHelper()->getScene()) {
      $event->cancel();
    }
  }

  public function bucketEmpty(PlayerBucketEmptyEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::BUCKET_EMPTY_EVENT);
    if ($event->isCancelled()) return;

    $scene = $sp->getSceneHelper()?->getScene();
    if ($scene) {
      $scene->sceneBucketEmptyEvent($event, $sp);
      if (!$event->isCancelled() && $scene instanceof PvP) {
        $scene->getBlockManager()->handleBucketDump($event);
      }
    }
  }

  public function bucketFill(PlayerBucketFillEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::BUCKET_FILL_EVENT);
    if ($event->isCancelled()) return;
    $sp->getSceneHelper()?->getScene()->sceneBucketFillEvent($event, $sp);
  }

  public function blockSpread(BlockSpreadEvent $event)
  {
    $this->handleNaturalEvent($event);
  }

  public function blockForm(BlockFormEvent $event)
  {
    $this->handleNaturalEvent($event);
  }

  private function handleNaturalEvent(BlockSpreadEvent|BlockFormEvent $event): void
  {
    $scene = $this->getSceneFromBlockEvent($event); // attempt to get the scene the block event happened in
    if ($scene) {
      $scene->getBlockManager()->handleNaturalBlockEvent($event);
    } else {
      $event->cancel();
    }
  }

  private function getSceneFromBlockEvent(BlockSpreadEvent|BlockFormEvent $event): ?PvP
  {
    $pos = $event->getBlock()->getPosition();
    $nearest = PositionHelper::getNearestPlayer($pos); // nearest player's scene (this is only going to work well for scenes that are seperated far away)
    if ($nearest) {
      $scene = $nearest->getSceneHelper()?->getScene() ?? null;
      if ($scene instanceof PvP) { // only pvp scenes have a block manager, this could be seen as a design flaw, but almost all our scenes derive from pvp anyway
        return $scene;
      }
    }

    return null;
  }

  public function startFlying(PlayerToggleFlightEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::PLAYER_TOGGLE_FLIGHT_EVENT);
    if ($event->isCancelled()) return;
    $sp->getSceneHelper()?->getScene()->scenePlayerToggleFlightEvent($event, $sp);
  }

  public function jumped(PlayerJumpEvent $event)
  {
    /* @var SwimPlayer $sp */
    $sp = $event->getPlayer();
    $sp->event($event, BehaviorEventEnums::PLAYER_JUMP_EVENT);
    $sp->getSceneHelper()?->getScene()->scenePlayerJumpEvent($event, $sp);
  }
  
  // lag causer possibly, we do need this though but only for behavior components
  public function dataPacketReceiveEvent(DataPacketReceiveEvent $event)
  {
    $player = $event->getOrigin()->getPlayer();
    if ($player instanceof SwimPlayer && $player->isOnline()) {
      $player->event($event, BehaviorEventEnums::DATA_PACKET_RECEIVE_EVENT); // behavior components like double jump need this, we should optimize this more
      // if ($event->isCancelled()) return;
      // $player->getSceneHelper()?->getScene()->sceneDataPacketReceiveEvent($event, $player); // disabled for performance
    }
  }

}
