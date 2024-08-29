<?php

namespace core\systems\player;

use core\SwimCore;
use core\systems\player\components\AckHandler;
use core\systems\player\components\AntiCheatData;
use core\systems\player\components\Attributes;
use core\systems\player\components\behaviors\EventBehaviorComponent;
use core\systems\player\components\behaviors\EventBehaviorComponentManager;
use core\systems\player\components\ChatHandler;
use core\systems\player\components\ClickHandler;
use core\systems\player\components\CombatLogger;
use core\systems\player\components\CoolDowns;
use core\systems\player\components\Invites;
use core\systems\player\components\NetworkStackLatencyHandler;
use core\systems\player\components\Nicks;
use core\systems\player\components\Rank;
use core\systems\player\components\SceneHelper;
use core\systems\player\components\Settings;
use core\utils\InventoryUtil;
use jackmd\scorefactory\ScoreFactory;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\entity\Entity;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use poggit\libasynql\libs\SOFe\AwaitGenerator\Await;

class SwimPlayer extends Player
{

  private SwimCore $core;

  /**
   * @var Component[]
   */
  private array $components = [];

  private ?EventBehaviorComponentManager $eventBehaviorComponentManager = null;

  private ?ChatHandler $chatHandler = null;
  private ?ClickHandler $clickHandler = null;
  private ?CoolDowns $coolDowns = null;
  private ?Invites $invites = null;
  private ?Nicks $nicks = null;
  private ?Rank $rank = null;
  private ?SceneHelper $sceneHelper = null;
  private ?Settings $settings = null;
  private ?NetworkStackLatencyHandler $nslHandler = null;
  private ?Attributes $attributes = null;
  private ?CombatLogger $combatLogger = null;
  private ?AckHandler $ackHandler = null;
  private ?AntiCheatData $antiCheatData = null;

  private Vector3 $exactPosition;

  public function init(SwimCore $core)
  {
    $this->core = $core;
    $this->eventBehaviorComponentManager = new EventBehaviorComponentManager();

    // then construct all the components

    $this->chatHandler = new ChatHandler($core, $this);
    $this->components['chatHandler'] = $this->chatHandler;

    $this->clickHandler = new ClickHandler($core, $this);
    $this->components['clickHandler'] = $this->clickHandler;

    $this->coolDowns = new CoolDowns($core, $this);
    $this->components['coolDowns'] = $this->coolDowns;

    $this->invites = new Invites($core, $this);
    $this->components['invites'] = $this->invites;

    $this->nicks = new Nicks($core, $this);
    $this->components['nicks'] = $this->nicks;

    $this->rank = new Rank($core, $this);
    $this->components['rank'] = $this->rank;

    $this->sceneHelper = new SceneHelper($core, $this);
    $this->components['sceneHelper'] = $this->sceneHelper;

    $this->antiCheatData = new AntiCheatData($core, $this, true);
    $this->components['antiCheatData'] = $this->antiCheatData;

    $this->settings = new Settings($core, $this);
    $this->components['settings'] = $this->settings;

    $this->nslHandler = new NetworkStackLatencyHandler($core, $this);
    $this->components['nslHandler'] = $this->nslHandler;

    $this->attributes = new Attributes($core, $this);
    $this->components['attributes'] = $this->attributes;

    $this->combatLogger = new CombatLogger($core, $this);
    $this->components['combatLogger'] = $this->combatLogger;

    $this->ackHandler = new AckHandler($core, $this, true);
    $this->components["ackHandler"] = $this->ackHandler;

    // then init each component
    foreach ($this->components as $component) {
      $component->init();
    }
  }

  public function getBehaviorManager(): ?EventBehaviorComponentManager
  {
    return $this->eventBehaviorComponentManager ?? null;
  }

  public function registerBehavior(EventBehaviorComponent $behaviorComponent): void
  {
    if (isset($this->eventBehaviorComponentManager)) {
      $this->eventBehaviorComponentManager->registerComponent($behaviorComponent);
    }
  }

  public function event(Event $event, int $eventEnum): void
  {
    if (isset($this->eventBehaviorComponentManager)) {
      $this->eventBehaviorComponentManager->event($event, $eventEnum);
    }
  }

  public function getEventBehaviorComponentManager(): ?EventBehaviorComponentManager
  {
    return $this->eventBehaviorComponentManager ?? null;
  }

  // this is now public since it is very useful,
  // we could also maybe attach a class bool field for saying if we take fall damage
  public function calculateFallDamage(float $fallDistance): float
  {
    return parent::calculateFallDamage($fallDistance);
  }

  public function getSwimCore(): SwimCore
  {
    return $this->core;
  }

  public function updateTick(): void
  {
    foreach ($this->components as $component) {
      if ($component->doesUpdate()) {
        $component->updateTick();
      }
    }
    $this->eventBehaviorComponentManager->updateTick();
  }

  public function updateSecond(): void
  {
    foreach ($this->components as $component) {
      if ($component->doesUpdate()) {
        $component->updateSecond();
      }
    }
    $this->eventBehaviorComponentManager->updateSecond();
  }

  public function exit(): void
  {
    $this->saveData(); // save data first then exit all components
    foreach ($this->components as $component) {
      $component->exit();
    }
    $this->components = [];
  }

  public function loadData(): void
  {
    Await::f2c(/**
     * @throws ScoreFactoryException
     * @throws JsonException
     * @breif it would be better for component base class to have a load method to override, and we iterate components calling that method
     */ function () {
      if ($this->isConnected())
        yield from $this->settings->load();
      if ($this->isConnected())
        yield from $this->rank->load();
      if ($this->isConnected())
        $this->getSceneHelper()->setNewScene("Hub"); // once done loading data we can put the player into the hub scene
    });
  }

  // save player data (right now is only settings, this later would be Elo, kits, and so on)
  public function saveData(): void
  {
    $this->settings?->saveSettings();
    // $this->kits?->saveKits();
    // $this->cosmetics?->saveData();
  }

  public function getPitchTowards(Vector3 $target): float
  {
    $horizontal = sqrt(($target->x - $this->location->x) ** 2 + ($target->z - $this->location->z) ** 2);
    $vertical = $target->y - ($this->location->y + $this->getEyeHeight());
    return -atan2($vertical, $horizontal) / M_PI * 180; //negative is up, positive is down
  }

  public function getYawTowards(Vector3 $target): float
  {
    $xDist = $target->x - $this->location->x;
    $zDist = $target->z - $this->location->z;

    $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
    if ($yaw < 0) {
      $yaw += 360.0;
    }

    return $yaw;
  }

  /**
   * @throws ScoreFactoryException
   * Quick helper for refreshing the scoreboard
   */
  public function refreshScoreboard($scoreboardTitle): void
  {
    $this->removeScoreboard();
    ScoreFactory::setObjective($this, $scoreboardTitle);
  }

  /**
   * @throws ScoreFactoryException
   * handles if the scoreboard setting is enabled, while also handling removing the scoreboard if it is not enabled
   */
  public function isScoreboardEnabled(): bool
  {
    $enabled = $this->settings->getToggle('showScoreboard');
    if (!$enabled) {
      $this->removeScoreboard();
    }
    return $enabled;
  }

  /**
   * @throws ScoreFactoryException
   * Delete a player's scoreboard
   */
  public function removeScoreboard(): void
  {
    if (ScoreFactory::hasObjective($this)) {
      ScoreFactory::removeObjective($this);
    }
  }

  // deletes all child entities of the player, such as projectiles they have thrown
  public function removeChildEntities(): void
  {
    $worlds = $this->core->getServer()->getWorldManager()->getWorlds();
    foreach ($worlds as $world) {
      $entities = $world->getEntities();
      foreach ($entities as $entity) {
        if ($entity instanceof Entity && $entity->getOwningEntity() === $this) {
          $entity->kill();
        }
      }
    }
  }

  public function bossBar(string $title, float $healthPercent, bool $darkenScreen = false, int $color = BossBarColor::PURPLE, int $overlay = 0): void
  {
    $packet = BossEventPacket::show($this->id, $title, $healthPercent, $darkenScreen, $color, $overlay);
    $this->getNetworkSession()->sendDataPacket($packet);
  }

  public function removeBossBar(): void
  {
    $packet = BossEventPacket::hide($this->id);
    $this->getNetworkSession()->sendDataPacket($packet);
  }

  // default name tag handling for showing rank or the nick if there is one
  public function genericNameTagHandling(): void
  {
    if ($this->nicks->isNicked()) {
      $this->setNameTag(TextFormat::GRAY . $this->nicks->getNick());
    } else {
      $this->rank->rankNameTag();
    }
  }

  /**
   * @throws ScoreFactoryException
   * Some stuff we do have to do manually, like removing scoreboard and inventory
   * All of these options can be manually switched off to not do a full clearance via parameters
   */
  public function cleanPlayerState
  (
    bool $clearComponents = true,
    bool $clearBehaviors = true,
    bool $clearInventory = true,
    bool $clearScoreBoard = true,
    bool $clearTags = true,
    bool $clearBossBar = true,
  ): void
  {
    if (!$this->isConnected()) return;

    // clear inventory
    if ($clearInventory) {
      InventoryUtil::fullPlayerReset($this);
    }

    // remove scoreboard
    if ($clearScoreBoard) {
      $this->removeScoreboard();
    }

    // clear all components
    if ($clearComponents) {
      foreach ($this->components as $component) {
        $component->clear();
      }
    }

    // do the same for the event behavior components
    if ($clearBehaviors) {
      $this->eventBehaviorComponentManager->clear();
    }

    // remove score tag and set the name tag back to the player's name
    if ($clearTags) {
      $this->setScoreTag(""); // setting it to an empty string hides it
      $this->setNameTag($this->getName());
    }

    // remove the boss bar
    if ($clearBossBar) {
      $this->removeBossBar();
    }
  }

  // below are getters for each component

  public function getChatHandler(): ?ChatHandler
  {
    return $this->chatHandler;
  }

  public function getClickHandler(): ?ClickHandler
  {
    return $this->clickHandler;
  }

  public function getCoolDowns(): ?CoolDowns
  {
    return $this->coolDowns;
  }

  public function getInvites(): ?Invites
  {
    return $this->invites;
  }

  public function getNicks(): ?Nicks
  {
    return $this->nicks;
  }

  public function getRank(): ?Rank
  {
    return $this->rank;
  }

  public function getSceneHelper(): ?SceneHelper
  {
    return $this->sceneHelper;
  }

  public function getSettings(): ?Settings
  {
    return $this->settings;
  }

  public function getNslHandler(): ?NetworkStackLatencyHandler
  {
    return $this->nslHandler;
  }

  public function getAttributes(): ?Attributes
  {
    return $this->attributes;
  }

  public function getCombatLogger(): ?CombatLogger
  {
    return $this->combatLogger;
  }

  public function getAckHandler(): ?AckHandler
  {
    return $this->ackHandler;
  }

  public function getAntiCheatData(): ?AntiCheatData
  {
    return $this->antiCheatData;
  }

  public function getExactPosition(): Vector3
  {
    return $this->exactPosition;
  }

  public function setExactPosition(Vector3 $vector3): void
  {
    $this->exactPosition = $vector3;
  }

}