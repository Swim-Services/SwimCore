<?php

namespace core\systems\scene;

use core\SwimCore;
use core\systems\entity\EntitySystem;
use core\systems\party\PartiesSystem;
use core\systems\player\PlayerSystem;
use core\systems\player\SwimPlayer;
use core\systems\scene\managers\TeamManager;
use core\systems\scene\misc\SpectatorCompass;
use core\systems\scene\misc\Team;
use core\systems\SystemManager;
use core\Utils\BehaviorEventEnums;
use core\utils\ServerSounds;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\entity\Entity;
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
use pocketmine\event\Event;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

abstract class Scene
{

  protected SwimCore $core;
  protected SystemManager $systemManager;
  protected PlayerSystem $playerSystem;
  protected PartiesSystem $partiesSystem;
  protected SceneSystem $sceneSystem;
  protected EntitySystem $entitySystem;

  /**
   * @var Event[] Array of events
   */
  private array $canceledEvents = [];

  /**
   * @var SwimPlayer[] Array of SwimPlayer objects indexed by int ID keys
   */
  protected array $players = []; // all swim players unsorted

  protected TeamManager $teamManager;

  protected string $sceneName;
  private int $sceneCreationTimestamp; // the unix timestamp of when the scene is created, used for calculating tick age
  protected bool $isDuel; // if this is a duel or not
  protected bool $ranked; // for ranked and scrim modes

  protected World $world;

  public function __construct(SwimCore $core, string $name)
  {
    $this->core = $core;
    $this->sceneName = $name;
    $this->sceneCreationTimestamp = time();

    // add in the systems a scene might need to touch
    $this->systemManager = $this->core->getSystemManager();
    $this->playerSystem = $this->systemManager->getPlayerSystem();
    $this->partiesSystem = $this->systemManager->getPartySystem();
    $this->sceneSystem = $this->systemManager->getSceneSystem();
    $this->entitySystem = $this->systemManager->getEntitySystem();
    $this->isDuel = false; // duel class will set this to true in its constructor
    $this->ranked = false; // duel class will set this to true if needed

    // make team manager
    $this->teamManager = new TeamManager($this);
  }

  /**
   * @return TeamManager
   */
  public function getTeamManager(): TeamManager
  {
    return $this->teamManager;
  }

  public function getPlayerTeam(SwimPlayer $swimPlayer): ?Team
  {
    return $this->teamManager->getTeamByID($swimPlayer->getSceneHelper()->getTeamNumber());
  }

  public final function isDuel(): bool
  {
    return $this->isDuel;
  }

  public final function isRanked(): bool
  {
    return $this->ranked;
  }

  public final function setWorld(World $world): void
  {
    $this->world = $world;
  }

  public final function getWorld(): World
  {
    return $this->world;
  }

  // gets the amount of ticks this scene has been active
  public final function getSceneTickAge(): int
  {
    return (time() - $this->sceneCreationTimestamp) * 20; // lazy getter
  }

  // should be an int array of event enums
  public final function registerCanceledEvents(array $canceledEvents): void
  {
    $this->canceledEvents = $canceledEvents;
  }

  // I don't know if this will ever be needed
  public final function getCanceledEvents(): array
  {
    return $this->canceledEvents;
  }

  // checks if the event in this scene is scheduled to be cancelled, and if it is then it cancels it
  private function cancelCheck(int $eventEnum, Event $event): void
  {
    if (!empty($this->canceledEvents)) {
      if (in_array($eventEnum, $this->canceledEvents, true)) {
        /*
        if (method_exists($event, 'cancel')) {
          $event->cancel();
        }
        */
        $event->cancel(); // reflection is slow, we know this works, assuming the developer doesn't register non-cancellable events as cancelled
      }
    }
  }

  // use this for performance instead of $this->playerSystem->getSwimPlayer($player);
  // this is now redundant
  public final function getSwimPlayerInScene(Player $player): ?SwimPlayer
  {
    $id = $player->getId();
    foreach ($this->players as $swimPlayer) {
      if ($swimPlayer->getId() === $id) {
        return $swimPlayer;
      }
    }
    // last resort
    return $this->playerSystem->getSwimPlayer($player);
  }

  // called once on creation
  abstract public function init(): void;

  // called every tick
  public function updateTick(): void
  {
  }

  // called every second
  public function updateSecond(): void
  {
  }

  // called once on server shutdown
  public function exit(): void
  {
    // no op default
  }

  // handling for when a player needs to restart in a scene individually (rekit, warp back to a spawn point, etc)
  // this function is also called by our scene framework when you try to put a player in a scene they are already in
  public function restart(SwimPlayer $swimPlayer): void
  {
    // no op default
  }

  public final function getSceneName(): string
  {
    return $this->sceneName;
  }

  public final function getPlayers(): array
  {
    return $this->players;
  }

  // Probably should use scene helper component instead
  public final function isInScene(SwimPlayer $player): bool
  {
    return in_array($player, $this->players, true);
  }

  public final function arePlayersInSameTeam(SwimPlayer $playerOne, SwimPlayer $playerTwo): bool
  {
    // not on a team if both team numbers -1
    if ($playerOne->getSceneHelper()->getTeamNumber() == -1 || $playerTwo->getSceneHelper()->getTeamNumber() == -1) {
      return false;
    }

    return $playerOne->getSceneHelper()->getTeamNumber() == $playerTwo->getSceneHelper()->getTeamNumber();
  }

  // warning, make sure team exists if you want to add a player to a team

  public final function addPlayer(SwimPlayer $player, string $team = 'none'): void
  {
    $team = $this->teamManager->getTeam($team);
    $team?->addPlayer($player); // big problem if team does not exist

    $this->players[] = $player; // push back into players array too
    $this->playerAdded($player);
  }

  public final function removePlayer(SwimPlayer $player): void
  {
    // Called before a player is removed. this is for the processing steps
    $this->playerRemoved($player);

    // Remove the player from the team they are in
    $this->getPlayerTeam($player)?->removePlayer($player);

    // Remove the player from the players array
    $key = array_search($player, $this->players, true);
    if ($key !== false) {
      unset($this->players[$key]);
    }

    // Then handle what to do after full removal from a team
    $this->playerElimination($player);
  }

  /**
   * @return bool determining if we did an action or not
   * @throws ScoreFactoryException|JsonException
   */
  protected final function spectatorControls(PlayerItemUseEvent $event, SwimPlayer $swimPlayer): bool
  {
    if ($swimPlayer->getGamemode() == GameMode::SPECTATOR) {
      $itemName = $event->getItem()->getCustomName();
      if ($itemName == TextFormat::RED . "Leave") {
        $swimPlayer->getSceneHelper()->setNewScene('Hub');
        $swimPlayer->sendMessage("§7Teleporting to hub...");
        $this->sceneAnnouncement(TextFormat::AQUA . $swimPlayer->getNicks()->getNick() . " Stopped Spectating");
        return true;
      }
    }

    return false;
  }

  public final function sceneAnnouncement(string $msg): void
  {
    foreach ($this->players as $player) {
      $player->sendMessage($msg);
    }
  }

  public function sceneBossBar(string $title, float $healthPercent, bool $darkenScreen = false, int $color = BossBarColor::PURPLE, int $overlay = 0): void
  {
    foreach ($this->players as $player) {
      $player->removeBossBar(); // refresh
      $packet = BossEventPacket::show($player->getId(), $title, $healthPercent, $darkenScreen, $color, $overlay);
      $player->getNetworkSession()->sendDataPacket($packet);
    }
  }

  public function removeBossBarForAll(): void
  {
    foreach ($this->players as $player) {
      $packet = BossEventPacket::hide($player->getId());
      $player->getNetworkSession()->sendDataPacket($packet);
    }
  }

  public function sceneJukeBoxMessage(string $message): void
  {
    foreach ($this->players as $player) {
      $player->sendJukeboxPopup($message);
    }
  }

  public function sceneTitle(string $title, string $subtitle = "", int $fadeIn = -1, int $stay = -1, int $fadeOut = -1): void
  {
    foreach ($this->players as $player) {
      $player->sendTitle($title, $subtitle, $fadeIn, $stay, $fadeOut);
    }
  }

  public function sceneSound(string $soundName, int $volume = 2, int $pitch = 1): void
  {
    foreach ($this->players as $player) {
      ServerSounds::playSoundToPlayer($player, $soundName, $volume, $pitch);
    }
  }

  // can optionally be overridden, also intended for when a player suddenly hubs or leaves the server
  public function playerElimination(SwimPlayer $swimPlayer): void
  {
    // no op default
  }

  // what happens when a player is added
  public function playerAdded(SwimPlayer $player): void
  {
    // no op default
  }

  // what happens when a player is removed
  public function playerRemoved(SwimPlayer $player): void
  {
    // no op default
  }

  // functions to work as event call backs from the player listener

  public function sceneEntityDamageByChildEntityEvent(EntityDamageByChildEntityEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::ENTITY_DAMAGE_BY_CHILD_ENTITY_EVENT, $event);
  }

  public function sceneEntityDamageByEntityEvent(EntityDamageByEntityEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::ENTITY_DAMAGE_BY_ENTITY_EVENT, $event);
  }

  public function sceneEntityDamageEvent(EntityDamageEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::ENTITY_DAMAGE_EVENT, $event);
  }

  public function sceneItemDropEvent(PlayerDropItemEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT, $event);
  }

  /**
   * @throws ScoreFactoryException|JsonException
   */
  public function sceneItemUseEvent(PlayerItemUseEvent $event, SwimPlayer $swimPlayer): void
  {
    if (!$this->spectatorControls($event, $swimPlayer)) {
      $this->cancelCheck(BehaviorEventEnums::PLAYER_ITEM_USE_EVENT, $event);
    }
  }

  public function sceneInventoryUseEvent(InventoryTransactionEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::INVENTORY_TRANSACTION_EVENT, $event);
  }

  public function sceneEntityTeleportEvent(EntityTeleportEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::ENTITY_TELEPORT_EVENT, $event);
  }

  public function scenePlayerConsumeEvent(PlayerItemConsumeEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PLAYER_ITEM_CONSUME_EVENT, $event);
  }

  public function scenePlayerPickupItem(EntityItemPickupEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::ENTITY_ITEM_PICKUP_EVENT, $event);
  }

  public function sceneProjectileLaunchEvent(ProjectileLaunchEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PROJECTILE_LAUNCH_EVENT, $event);
  }

  public function sceneEntityRegainHealthEvent(EntityRegainHealthEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::ENTITY_REGAIN_HEALTH_EVENT, $event);
  }

  public function sceneProjectileHitEvent(ProjectileHitEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PROJECTILE_HIT_EVENT, $event);
  }

  public function sceneProjectileHitEntityEvent(ProjectileHitEntityEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PROJECTILE_HIT_ENTITY_EVENT, $event);
  }

  // not called back normally for performance reasons, only called back for chest opening at the moment
  public function scenePlayerInteractEvent(PlayerInteractEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PLAYER_INTERACT_EVENT, $event);
  }

  public function sceneBlockPlaceEvent(BlockPlaceEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::BLOCK_PLACE_EVENT, $event);
  }

  public function sceneBlockBreakEvent(BlockBreakEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::BLOCK_BREAK_EVENT, $event);
  }

  public function scenePlayerSpawnChildEvent(EntitySpawnEvent $event, SwimPlayer $swimPlayer, Entity $spawnedEntity): void
  {
    $this->cancelCheck(BehaviorEventEnums::ENTITY_SPAWN_EVENT, $event);
  }

  public function scenePlayerToggleFlightEvent(PlayerToggleFlightEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PLAYER_TOGGLE_FLIGHT_EVENT, $event);
  }

  public function scenePlayerJumpEvent(PlayerJumpEvent $event, SwimPlayer $swimPlayer): void
  {
    $this->cancelCheck(BehaviorEventEnums::PLAYER_JUMP_EVENT, $event);
  }

  // this event can not be cancelled and should not be cancelled, so we don't allow it being registered and checked to be cancelled
  public function sceneDataPacketReceiveEvent(DataPacketReceiveEvent $event, SwimPlayer $swimPlayer): void
  {
    // optional override (like the rest, just not cancel checked)
  }

}