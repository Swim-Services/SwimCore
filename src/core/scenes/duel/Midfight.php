<?php

namespace core\scenes\duel;

use core\custom\prefabs\boombox\KnockerBox;
use core\custom\prefabs\pearl\SwimPearlItem;
use core\systems\player\SwimPlayer;
use core\systems\scene\misc\Team;
use core\utils\BehaviorEventEnums;
use core\utils\CustomDamage;
use core\utils\InventoryUtil;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat;

class Midfight extends Duel
{

  private int $rounds = 0;
  private int $maxRounds = 5;

  const PEARL_ONLY = 0;
  const BOX_ONLY = 1;
  const BOTH = 2;

  public static function getIcon(): string
  {
    return "textures/items/diamond_chestplate";
  }

  public function init(): void
  {
    $this->registerCanceledEvents([
      // BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT,
      BehaviorEventEnums::BLOCK_BREAK_EVENT
    ]);
    $this->kb = 0.4;
    $this->vertKB = 0.4;
    $this->pearlSpeed = 2;
    $this->duelCountDownTime = 3;
    $this->warpUnits = 40;
    $this->spawnCloser = true;
  }

  // the way this is designed right now is to give 1 pearl on your first point, and a knock back box on your 2nd point
  // not sure if we want this, or to just give both items each life no matter what
  protected function playerHit(SwimPlayer $attacker, SwimPlayer $victim, EntityDamageByEntityEvent $event): void
  {
    // apply no critical custom damage
    CustomDamage::customDamageHandle($event);
  }

  /**
   * @throws ScoreFactoryException
   */
  protected function playerKilled(SwimPlayer $attacker, SwimPlayer $victim, EntityDamageByEntityEvent $event): void
  {
    $team = $this->getPlayerTeam($attacker);
    if (!$team) {
      return;
    }

    // set victim to spectator
    $victim->setGamemode(GameMode::SPECTATOR);

    // do death effect
    $this->deathEffect($victim);

    // update attributes for both players
    $attacker->getAttributes()->emplaceIncrementIntegerAttribute("kills");
    $victim->getAttributes()->emplaceIncrementIntegerAttribute("deaths");
    $victim->getCombatLogger()->clear(); // to fix a bug with final message kills

    // check if whole team in spec (dead)
    $victimTeam = $this->getPlayerTeam($victim); // this is awful if it's null
    if ($victimTeam) {
      $this->teamCheck($team, $victimTeam);
    }
  }

  /**
   * @throws ScoreFactoryException
   */
  private function teamCheck(Team $killerTeam, Team $victimTeam)
  {
    foreach ($victimTeam->getPlayers() as $player) {
      if ($player->getGamemode() !== GameMode::SPECTATOR) {
        return;
      }
    }

    // if all players in that team ended up being spectator mode (dead) increase rounds and score
    $this->rounds++;

    // increase killer score
    $score = $killerTeam->getScore() + 1;
    $killerTeam->setScore($score);
    $this->updateBoardsForAll($this);

    // check if we hit the goal
    if ($score >= $killerTeam->getTargetScore()) {
      $this->scoreBasedDuelEnd($killerTeam);
      return;
    }

    // pick kit type TO DO : have good game design for this item pool
    switch ($this->rounds) {
      case 1:
        $this->kit(self::PEARL_ONLY);
        break;
      case 2:
        $this->kit(self::BOX_ONLY);
        break;
      case 3: // 3 or higher start giving both gear types
      default:
        $this->kit(self::BOTH);
    }

    // if we aren't at max rounds yet then warp them back to spawn positions
    if ($this->rounds < $this->maxRounds) {
      $this->warpPlayersIn();
    }
  }

  // override to use the score tracking board
  public function duelScoreboard(SwimPlayer $player): void
  {
    $this->duelScoreboardWithScore($player);
  }

  private function kit(int $enum)
  {
    foreach ($this->teamManager->getTeams() as $team) {
      if ($team->isSpecTeam()) continue; // skip spectators
      foreach ($team->getPlayers() as $player) {
        InventoryUtil::fullPlayerReset($player);
        $player->setGamemode(GameMode::SURVIVAL); // so they can place boom box
        InventoryUtil::midfKit($player);

        // this part is ugly and needs to improve + maybe support kits later
        if ($enum == self::PEARL_ONLY) {
          $player->getInventory()->setItem(1, new SwimPearlItem($player));
        } else if ($enum == self::BOX_ONLY) {
          $knockerBox = (new KnockerBox())->asItem();
          $knockerBox->setCustomName(TextFormat::RESET . TextFormat::LIGHT_PURPLE . "Knocker Box");
          $player->getInventory()->setItem(1, $knockerBox);
        } else {
          $player->getInventory()->setItem(1, new SwimPearlItem($player));
          $knockerBox = (new KnockerBox())->asItem();
          $knockerBox->setCustomName(TextFormat::RESET . TextFormat::LIGHT_PURPLE . "Knocker Box");
          $player->getInventory()->setItem(2, $knockerBox);
        }
      }
    }
  }

  protected function applyKit(SwimPlayer $swimPlayer): void
  {
    $swimPlayer->setGamemode(GameMode::SURVIVAL);
    InventoryUtil::midfKit($swimPlayer);
  }

  protected function duelStart(): void
  {
    foreach ($this->teamManager->getTeams() as $team) {
      $team->setTargetScore(3);
    }
  }

  protected function duelOver(Team $winners, Team $losers): void
  {
    if ($winners->getTeamSize() > 1) {
      $this->partyWin($winners->getTeamName());
    } else { // assumes that it was a 1v1 and the 1 loser in the losers array is who we just beat
      $loser = $this->losers[array_key_first($this->losers)];
      $players = $winners->getPlayers();
      $winner = $players[array_key_first($players)];
      if ($loser && $winner) {
        $attackerName = $winner->getNicks()->getNick();
        $loserName = $loser->getNicks()->getNick();

        // format their scores
        $attackerString = TextFormat::GRAY . " [" . TextFormat::GREEN . $winners->getScore() . " Kills" . TextFormat::GRAY . "]";
        $loserString = TextFormat::GRAY . " [" . TextFormat::GREEN . $losers->getScore() . " Kills" . TextFormat::GRAY . "]";

        // game title text
        $midfight = TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::AQUA . "Midfight" . TextFormat::GRAY . "]" . TextFormat::RESET . " ";

        $msg = $midfight . TextFormat::GREEN . $attackerName . $attackerString . TextFormat::YELLOW
          . " Killed " . TextFormat::RED . $loserName . $loserString;
        $this->core->getSystemManager()->getSceneSystem()->getScene("Hub")?->sceneAnnouncement($msg);
        $this->sceneAnnouncement($msg);

        // then do spectators message
        $this->specMessage();
      }
    }
  }

  // this logic seems wrong, needs to be rewritten anyway
  private function partyWin(string $winnerTeamName): void
  {
    $midf = TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::AQUA . "Midfight Parties" . TextFormat::GRAY . "]" . TextFormat::RESET . " ";

    $loserTeamsArray = [];
    foreach ($this->teamManager->getTeams() as $team) {
      if (!$team->isSpecTeam() && $team->getTeamSize() == 0) {
        $loserTeamsArray[] = $team->getTeamName();
      }
    }
    $loserTeams = implode(', ', $loserTeamsArray);

    // $this->core->getServer()->broadcastMessage($midf . TextFormat::YELLOW . $winnerTeamName . TextFormat::GREEN . " Defeated " . TextFormat::YELLOW . $loserTeams);
    $msg = $midf . TextFormat::YELLOW . $winnerTeamName . TextFormat::GREEN . " Defeated " . TextFormat::YELLOW . $loserTeams;
    $this->core->getSystemManager()->getSceneSystem()->getScene("Hub")?->sceneAnnouncement($msg);
    $this->specMessage();
  }

}