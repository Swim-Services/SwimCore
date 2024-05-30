<?php

namespace core\systems\player\components\behaviors;

use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use core\Utils\BehaviorEventEnums;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
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
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\event\server\DataPacketReceiveEvent;

// this is a scriptable component that has a tick lifetime and hook-able events
// canceling the events will cause them to not be sent to the scene the player is in!

abstract class EventBehaviorComponent extends Component
{

  protected bool $hasLifeTime;
  protected int $tickLifeTime;
  protected int $ticksAlive = 0;
  protected string $componentName;

  protected bool $enabled = true;
  protected bool $destroyMe = false;
  protected bool $doesEvents;
  protected bool $removeOnReset;

  public function __construct
  (
    string     $componentName,
    SwimCore   $core,
    SwimPlayer $swimPlayer,
    bool       $doesUpdate = true,
    bool       $hasLifeTime = false,
    int        $tickLifeTime = 20,
    bool       $removeOnReset = true,
    bool       $doesEvents = true
  )
  {
    parent::__construct($core, $swimPlayer, $doesUpdate);
    $this->hasLifeTime = $hasLifeTime;
    $this->tickLifeTime = $tickLifeTime;
    $this->componentName = $componentName;
    $this->removeOnReset = $removeOnReset;
    $this->doesEvents = $doesEvents;
  }

  // if not marked for destroy and is enabled
  public final function shouldUpdate(): bool
  {
    return !$this->destroyMe && $this->enabled;
  }

  /**
   * @param bool $removeOnReset
   */
  public function setRemoveOnReset(bool $removeOnReset): void
  {
    $this->removeOnReset = $removeOnReset;
  }

  public function isRemoveOnReset(): bool
  {
    return $this->removeOnReset;
  }

  /**
   * @return string
   */
  public final function getComponentName(): string
  {
    return $this->componentName;
  }

  /**
   * @param string $componentName
   */
  public final function setComponentName(string $componentName): void
  {
    $this->componentName = $componentName;
  }

  /**
   * @return bool
   */
  public final function isDestroyMe(): bool
  {
    return $this->destroyMe;
  }

  /**
   * @param bool $destroy
   */
  public final function setDestroy(bool $destroy = true): void
  {
    $this->destroyMe = $destroy;
  }

  /**
   * @param int $tickLifeTime
   */
  public function setTickLifeTime(int $tickLifeTime): void
  {
    $this->tickLifeTime = $tickLifeTime;
  }

  /**
   * @return int
   */
  public final function getTickLifeTime(): int
  {
    return $this->tickLifeTime;
  }

  /**
   * @return bool
   */
  public final function isEnabled(): bool
  {
    return $this->enabled;
  }

  /**
   * @param bool $enabled
   */
  public final function setEnabled(bool $enabled): void
  {
    $this->enabled = $enabled;
  }

  /**
   * @return bool
   */
  public function isDoesEvents(): bool
  {
    return $this->doesEvents;
  }

  /**
   * @param bool $doesEvents
   */
  public function setDoesEvents(bool $doesEvents): void
  {
    $this->doesEvents = $doesEvents;
  }

  public final function updateTick(): void
  {
    $this->ticksAlive++;
    if ($this->hasLifeTime && ($this->ticksAlive > $this->tickLifeTime)) {
      $this->destroyMe = true;
    }
    if (!$this->destroyMe) {
      $this->eventUpdateTick();
    }
  }

  // optional override
  protected function eventUpdateTick(): void
  {
    // no op default
  }

  public final function updateSecond(): void
  {
    $this->eventUpdateSecond();
  }

  // optional override, could also override updateTick,
  // but we need naming consistency between eventUpdateTick, which is called from the implemented updateTick()
  protected function eventUpdateSecond(): void
  {
    // no op default
  }

  public final function event(mixed $event, int $eventEnum): void
  {
    if (!$this->doesEvents) return;
    match ($eventEnum) {
      BehaviorEventEnums::BLOCK_BREAK_EVENT => $this->blockBreakEvent($event),
      BehaviorEventEnums::BLOCK_PLACE_EVENT => $this->blockPlaceEvent($event),
      BehaviorEventEnums::ENTITY_DAMAGE_BY_CHILD_ENTITY_EVENT => $this->entityDamageByChildEntityEvent($event),
      BehaviorEventEnums::ENTITY_DAMAGE_BY_ENTITY_EVENT => $this->entityDamageByEntityEvent($event),
      BehaviorEventEnums::ENTITY_DAMAGE_EVENT => $this->entityDamageEvent($event),
      BehaviorEventEnums::ENTITY_ITEM_PICKUP_EVENT => $this->playerPickupItem($event),
      BehaviorEventEnums::ENTITY_REGAIN_HEALTH_EVENT => $this->entityRegainHealthEvent($event),
      BehaviorEventEnums::ENTITY_SPAWN_EVENT => $this->playerSpawnChildEvent($event),
      BehaviorEventEnums::ENTITY_TELEPORT_EVENT => $this->entityTeleportEvent($event),
      BehaviorEventEnums::PROJECTILE_HIT_ENTITY_EVENT => $this->projectileHitEntityEvent($event),
      BehaviorEventEnums::PROJECTILE_HIT_EVENT => $this->projectileHitEvent($event),
      BehaviorEventEnums::PROJECTILE_LAUNCH_EVENT => $this->projectileLaunchEvent($event),
      BehaviorEventEnums::INVENTORY_TRANSACTION_EVENT => $this->inventoryUseEvent($event),
      BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT => $this->itemDropEvent($event),
      BehaviorEventEnums::PLAYER_INTERACT_EVENT => $this->playerInteractEvent($event),
      BehaviorEventEnums::PLAYER_ITEM_CONSUME_EVENT => $this->playerConsumeEvent($event),
      BehaviorEventEnums::PLAYER_ITEM_USE_EVENT => $this->itemUseEvent($event),
      BehaviorEventEnums::PLAYER_TOGGLE_FLIGHT_EVENT => $this->playerToggleFlightEvent($event),
      BehaviorEventEnums::DATA_PACKET_RECEIVE_EVENT => $this->dataPacketReceiveEvent($event),
      BehaviorEventEnums::PLAYER_JUMP_EVENT => $this->playerJumpEvent($event),
      default => null, // do we need unknown event handling?
    };
  }

  // below are hooked events, by default they do nothing, $this->swimPlayer is the player to all these events

  protected function entityDamageByChildEntityEvent(EntityDamageByChildEntityEvent $event): void
  {

  }

  protected function entityDamageByEntityEvent(EntityDamageByEntityEvent $event): void
  {

  }

  protected function entityDamageEvent(EntityDamageEvent $event): void
  {

  }

  protected function itemDropEvent(PlayerDropItemEvent $event): void
  {

  }

  protected function itemUseEvent(PlayerItemUseEvent $event): void
  {

  }

  protected function inventoryUseEvent(InventoryTransactionEvent $event): void
  {

  }

  protected function entityTeleportEvent(EntityTeleportEvent $event): void
  {

  }

  protected function playerConsumeEvent(PlayerItemConsumeEvent $event): void
  {

  }

  protected function playerPickupItem(EntityItemPickupEvent $event): void
  {

  }

  protected function projectileLaunchEvent(ProjectileLaunchEvent $event): void
  {

  }

  protected function entityRegainHealthEvent(EntityRegainHealthEvent $event): void
  {

  }

  protected function projectileHitEvent(ProjectileHitEvent $event): void
  {

  }

  protected function projectileHitEntityEvent(ProjectileHitEntityEvent $event): void
  {

  }

  // not called back for performance reasons
  protected function playerInteractEvent(PlayerInteractEvent $event): void
  {

  }

  protected function blockPlaceEvent(BlockPlaceEvent $event): void
  {

  }

  protected function blockBreakEvent(BlockBreakEvent $event): void
  {

  }

  protected function playerSpawnChildEvent(EntitySpawnEvent $event): void
  {

  }

  protected function playerToggleFlightEvent(PlayerToggleFlightEvent $event): void
  {

  }

  protected function playerJumpEvent(PlayerJumpEvent $event): void
  {

  }

  // this event can not be cancelled and should not be cancelled, so we don't allow it being registered and checked to be cancelled
  protected function dataPacketReceiveEvent(DataPacketReceiveEvent $event): void
  {

  }

  // below here are super special events for custom call backs, currently the code for calling them is very bad in player lister

  public function attackedPlayer(EntityDamageByEntityEvent $event, SwimPlayer $victim): void
  {

  }

}