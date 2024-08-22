<?php

namespace core\systems\player\components;

use core\SwimCore;
use core\systems\event\EventTeam;
use core\systems\party\Party;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use core\utils\ServerSounds;
use pocketmine\utils\TextFormat;

class Invites extends Component
{

  /**
   * @var Party[]
   * key is party name
   */
  private array $partyInvites = []; // invites parties have sent to this player

  private array $duelInvites = []; // invites players have sent to this player for duels

  private ?Settings $settings;

  /**
   * @var EventTeam[]
   * key is event ID
   */
  private array $teamInvites;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer);
    $this->clearAllInvites();
  }

  public function init(): void
  {
    $this->settings = $this->swimPlayer->getSettings();
  }

  // type must be 'duelInvites' or 'partyInvites'
  public function canSendInvite(string $type): bool
  {
    if ($type !== 'duelInvites' && $type !== 'partyInvites') {
      return false;
    }
    if (strtolower($this->swimPlayer->getSceneHelper()->getScene()->getSceneName()) != "hub") {
      return false;
    }
    if (!$this->swimPlayer->getSettings()->getToggle($type)) {
      return false;
    }
    if ($this->core->getSystemManager()->getPartySystem()->isInParty($this->swimPlayer)) {
      return false;
    }
    return true;
  }

  // should probably check canSendInvite('duelInvites') before doing this
  public function duelInvitePlayer(SwimPlayer $sender, string $mode, string $map = 'random'): void
  {
    if (!$this->settings->getToggle('duelInvites')) {
      $sender->sendMessage(TextFormat::RED . "This player has duel invites disabled!");
      return;
    }

    $senderName = $sender->getName();
    if (isset($this->duelInvites[$senderName]) && $this->duelInvites[$senderName]['mode'] === $mode && $this->duelInvites[$senderName]['map'] === $map) {
      $sender->sendMessage(TextFormat::RED . "You already sent this player this duel request!");
    } else {
      $this->duelInvites[$senderName] = ['mode' => $mode, 'map' => $map];
      $name = $sender->getNicks()->getNick();
      $sender->sendMessage(TextFormat::GREEN . "Sent " . $this->swimPlayer->getNicks()->getNick() . " a " . $mode . " duel request on map " . $map);
      $this->swimPlayer->sendMessage(TextFormat::GREEN . $name . TextFormat::YELLOW . " has sent you a " . TextFormat::AQUA . $mode . TextFormat::YELLOW . " duel request!");
      ServerSounds::playSoundToPlayer($this->swimPlayer, "mob.chicken.plop", 2, 1);
    }
  }

  // should probably check settings->canSendInvite('partyInvites') before doing this
  public function partyInvitePlayer(SwimPlayer $sender, Party $party): void
  {
    if (isset($this->partyInvites[$party->getPartyName()])) {
      $sender->sendMessage(TextFormat::YELLOW . "You already sent this player a party invite!");
    } else {
      $partyName = $party->getPartyName();
      $this->partyInvites[$partyName] = $party;
      $name = $sender->getNicks()->getNick();

      // send messages
      $party->partyMessage(TextFormat::GREEN . $name . TextFormat::YELLOW . " Invited " . TextFormat::GREEN
        . $this->swimPlayer->getNicks()->getNick() . TextFormat::YELLOW . " to the Party!");

      $this->swimPlayer->sendMessage(TextFormat::GREEN . $name . TextFormat::YELLOW . " has invited you to join their party: " . TextFormat::AQUA . $partyName);
    }
  }

  public function teamInvitePlayer(EventTeam $team): void
  {
    $this->teamInvites[$team->getID()] = $team;
  }

  public function removeTeamInvite(?EventTeam $team): void
  {
    if ($team) unset($this->teamInvites[$team->getID()]);
  }

  public function getDuelInvites(): array
  {
    return $this->duelInvites;
  }

  /**
   * @return Party[]
   */
  public function getPartyInvites(): array
  {
    return $this->partyInvites;
  }

  /**
   * @return EventTeam[]
   */
  public function getTeamInvites(): array
  {
    return $this->teamInvites;
  }

  public function clear(): void
  {
    $this->clearAllInvites();
  }

  // should be called on cleaning up scenes
  public function clearAllInvites(): void
  {
    $this->partyInvites = [];
    $this->duelInvites = [];
    $this->teamInvites = [];
  }

  public function prunePlayerFromDuelInvites(string $name): void
  {
    unset($this->duelInvites[$name]);
  }

}