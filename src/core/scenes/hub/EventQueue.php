<?php

namespace core\scenes\hub;

use core\custom\prefabs\hub\HubEntities;
use core\systems\event\EventForms;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use core\utils\BehaviorEventEnums;
use jackmd\scorefactory\ScoreFactory;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\block\utils\DyeColor;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use ReflectionException;

class EventQueue extends Scene
{

  /**
   * @throws ReflectionException
   */
  function init(): void
  {
    $this->registerCanceledEvents([
      BehaviorEventEnums::ENTITY_DAMAGE_EVENT,
      BehaviorEventEnums::ENTITY_DAMAGE_BY_ENTITY_EVENT,
      BehaviorEventEnums::ENTITY_DAMAGE_BY_CHILD_ENTITY_EVENT,
      BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT,
      BehaviorEventEnums::PROJECTILE_LAUNCH_EVENT,
      BehaviorEventEnums::BLOCK_BREAK_EVENT,
      BehaviorEventEnums::BLOCK_PLACE_EVENT,
      BehaviorEventEnums::PLAYER_ITEM_CONSUME_EVENT
    ]);
    // spawn in our hub entities for the scene
    HubEntities::spawnToScene($this);
  }

  /**
   * @throws ScoreFactoryException
   */
  public function playerAdded(SwimPlayer $player): void
  {
    $this->restart($player);
  }

  /**
   * @throws ScoreFactoryException
   */
  public function restart(SwimPlayer $swimPlayer): void
  {
    $this->teleportToHub($swimPlayer);
    $this->hubBoard($swimPlayer);
    $this->setHubTags($swimPlayer);
    $swimPlayer->setGamemode(GameMode::ADVENTURE);
    // $swimPlayer->getCosmetics()->refresh();
    self::kit($swimPlayer);
  }

  private function teleportToHub(SwimPlayer $player): void
  {
    $hub = $this->core->getServer()->getWorldManager()->getWorldByName("hub");
    $safeSpawn = $hub->getSafeSpawn();
    $player->teleport(new Position($safeSpawn->getX() + 0.5, $safeSpawn->getY(), $safeSpawn->getZ() + 0.5, $hub));
  }

  // we will call this method when owner is changed or joined a new team
  public static function kit(SwimPlayer $player): void
  {
    $inventory = $player->getInventory();
    $inventory->setHeldItemIndex(0);
    $inventory->clearAll();
    $event = $player->getSceneHelper()->getEvent();
    $team = $event->getTeamPlayerIsIn($player);

    // owner items
    if ($team->isOwner($player)) {
      $inventory->setItem(0, VanillaItems::TOTEM()->setCustomName(TextFormat::GREEN . "Manage Team"));
    }

    $inventory->setItem(1, VanillaItems::NAME_TAG()->setCustomName(TextFormat::YELLOW . "Team Invites"));

    // event host items
    if ($event->getHost()->getId() == $player->getId()) {
      $inventory->setItem(2, VanillaItems::BOOK()->setCustomName(TextFormat::GREEN . "Manage Event"));
    }

    $inventory->setItem(8, VanillaItems::DYE()->setColor(DyeColor::RED)->setCustomName(TextFormat::RED . "Leave"));
  }

  private function setHubTags(SwimPlayer $swimPlayer): void
  {
    $swimPlayer->genericNameTagHandling();
    // $swimPlayer->getCosmetics()->tagNameTag();

    $event = $swimPlayer->getSceneHelper()?->getEvent();
    if ($event) {
      $team = $event->getTeamPlayerIsIn($swimPlayer);
      if (!$team) return; // this is bad if this happens

      $lineOne = TextFormat::LIGHT_PURPLE . $event->getEventName() . TextFormat::DARK_GRAY . " | " . $event->formatPlayerCount();

      $finalText = $lineOne;
      if ($team->getMaxTeamSize() > 1) {
        $teamID = $team->getID();
        $lineTwo = "§bTeam §3#" . $teamID . TextFormat::GRAY . " | " . $team->formatSize();
        $finalText = $lineOne . "\n" . $lineTwo;
      }

      $swimPlayer->setScoreTag($finalText);
    } else {
      $swimPlayer->setScoreTag("");
    }
  }

  // this code is pretty bad looking, quick and dirty,
  // I have so much repeat code for server event and team getting just to avoid log from spammed logic when not using an item of the possible names
  public function sceneItemUseEvent(PlayerItemUseEvent $event, SwimPlayer $swimPlayer): void
  {
    $name = $event->getItem()->getCustomName();
    if ($name == TextFormat::RED . "Leave") {
      $serverEvent = $swimPlayer->getSceneHelper()->getEvent();
      $team = $serverEvent->getTeamPlayerIsIn($swimPlayer);
      EventForms::leaveTeam($swimPlayer, $team);
    } else if ($name == TextFormat::GREEN . "Manage Team") {
      $serverEvent = $swimPlayer->getSceneHelper()->getEvent();
      $team = $serverEvent->getTeamPlayerIsIn($swimPlayer);
      EventForms::manageTeam($swimPlayer, $serverEvent, $team);
    } else if ($name == TextFormat::YELLOW . "Team Invites") {
      EventForms::viewTeamInvites($swimPlayer);
    } else if ($name == TextFormat::GREEN . "Manage Event") {
      $serverEvent = $swimPlayer->getSceneHelper()->getEvent();
      $team = $serverEvent->getTeamPlayerIsIn($swimPlayer);
      EventForms::manageEventForm($swimPlayer, $serverEvent, $team);
    }
  }

  // at scene update we call the scoreboard behavior function

  /**
   * @throws ScoreFactoryException
   */
  function updateSecond(): void
  {
    foreach ($this->players as $player) {
      $this->hubBoard($player);
      $this->setHubTags($player);
    }
  }

  /**
   * @throws ScoreFactoryException
   */
  private function hubBoard(SwimPlayer $swimPlayer): void
  {
    $player = $swimPlayer;
    if ($swimPlayer->isScoreboardEnabled()) {
      try {
        $swimPlayer->refreshScoreboard(TextFormat::AQUA . "Swimgg.club");
        ScoreFactory::sendObjective($player);
        // variables needed
        $onlineCount = count($player->getServer()->getOnlinePlayers());
        $maxPlayers = $player->getServer()->getMaxPlayers();
        $ping = $swimPlayer->getNslHandler()->getPing();
        $indent = "  ";

        // event variables needed
        $event = $swimPlayer->getSceneHelper()->getEvent();
        if (!$event) return; // this is pretty bad if this happens

        $eventName = $event->getEventName();
        $playerCount = $event->formatPlayerCount();
        $timeToStart = $event->formatTimeToStart();

        $team = $event->getTeamPlayerIsIn($swimPlayer);
        if (!$team) return; // this is bad if this happens
        $teamID = $team->getID();
        $teamText = "§bTeam §3#" . $teamID;
        if ($team->getMaxTeamSize() > 1) {
          $teamText .= TextFormat::GRAY . " | " . $team->formatSize();
        }

        // define lines
        ScoreFactory::setScoreLine($swimPlayer, 1, "  =============   ");
        ScoreFactory::setScoreLine($swimPlayer, 2, $indent . "§bOnline: §f" . $onlineCount . "§7 / §3" . $maxPlayers . $indent);
        ScoreFactory::setScoreLine($swimPlayer, 3, $indent . "§bPing: §3" . $ping . $indent);
        ScoreFactory::setScoreLine($swimPlayer, 4, $indent . TextFormat::LIGHT_PURPLE . $eventName . $indent);
        ScoreFactory::setScoreLine($swimPlayer, 5, $indent . $teamText . $indent);
        ScoreFactory::setScoreLine($swimPlayer, 6, $indent . $playerCount . TextFormat::GRAY . " | §b" . $timeToStart . $indent);
        ScoreFactory::setScoreLine($swimPlayer, 7, $indent . "§bdiscord.gg/§3swim" . $indent);
        ScoreFactory::setScoreLine($swimPlayer, 8, "  =============  ");
        // send lines
        ScoreFactory::sendLines($player);
      } catch (ScoreFactoryException $e) {
        Server::getInstance()->getLogger()->info($e->getMessage());
      }
    }
  }

}