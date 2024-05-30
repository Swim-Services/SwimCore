<?php

namespace core\systems\event;

use core\scenes\hub\EventQueue;
use core\SwimCore;
use core\systems\player\SwimPlayer;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\utils\TextFormat;

class EventTeam
{

  private SwimCore $core;
  private ServerGameEvent $event;
  private SwimPlayer $owner;

  private int $currentTeamSize = 1;
  private int $maxTeamSize = 1;

  /**
   * @var SwimPlayer[]
   * key is player ID
   */
  private array $members;

  /**
   * @var SwimPlayer[]
   * key is player ID
   */
  private array $joinRequests;

  /**
   * @var SwimPlayer[]
   * key is player ID
   */
  private array $outgoingInvites;

  private int $id;

  /**
   * Constructor for the EventTeam class.
   *
   * @param SwimCore $core Instance of SwimCore.
   * @param ServerGameEvent $event Instance of ServerGameEvent.
   * @param SwimPlayer $owner Instance of SwimPlayer representing the team owner.
   */
  public function __construct(SwimCore $core, ServerGameEvent $event, SwimPlayer $owner, int $id)
  {
    $this->core = $core;
    $this->event = $event;
    $this->owner = $owner;
    $this->members[$owner->getId()] = $owner;
    $this->joinRequests = [];
    $this->outgoingInvites = [];
    $this->id = $id;
    $owner->getSceneHelper()->setTeamNumber($this->id);
  }

  /**
   * @return int
   */
  public function getMaxTeamSize(): int
  {
    return $this->maxTeamSize;
  }

  /**
   * @param int $maxTeamSize
   */
  public function setMaxTeamSize(int $maxTeamSize): void
  {
    $this->maxTeamSize = $maxTeamSize;
  }

  /**
   * Handles a join request from a player.
   *
   * @param SwimPlayer $player The player requesting to join.
   */
  public function joinRequest(SwimPlayer $player): void
  {
    // Check if player is already a member or has already requested to join
    $playerId = $player->getId();
    if (isset($this->members[$playerId]) || isset($this->joinRequests[$playerId])) {
      return; // Player is already a member or has an active request
    }

    // Add to join requests
    $this->messageAll($player->getRank()->rankString() . TextFormat::GREEN . " has requested to join your team " . $this->formatSize());
    $this->joinRequests[$playerId] = $player;
  }

  public function formatSize(): string
  {
    return TextFormat::DARK_GRAY . "(" . TextFormat::YELLOW . $this->currentTeamSize
      . TextFormat::DARK_GRAY . "/" . TextFormat::YELLOW . $this->maxTeamSize . TextFormat::DARK_GRAY . ")";
  }

  /**
   * Sends an invitation to a player to join the team.
   *
   * @param SwimPlayer $player The player to invite.
   */
  public function invite(SwimPlayer $player): void
  {
    // Check if player is already invited or is a member
    $playerId = $player->getId();
    if (isset($this->members[$playerId]) || isset($this->outgoingInvites[$playerId])) {
      return; // Player is already a member or has been invited
    }

    // Add to outgoing invites
    $this->outgoingInvites[$playerId] = $player;
    $player->getInvites()->teamInvitePlayer($this);
    $player->sendMessage($this->owner->getRank()->rankString() . TextFormat::GREEN . " has invited you to join their team " . $this->formatSize());
    $this->messageAll($player->getRank()->rankString() . TextFormat::GREEN . " was invited to the team");
  }

  public function messageAll(string $message): void
  {
    foreach ($this->members as $member) {
      $member->sendMessage($message);
    }
  }

  public function attemptRequest(SwimPlayer $requester): void
  {
    if ($this->hasAlreadyRequested($requester)) {
      $requester->sendMessage(TextFormat::YELLOW . "You already requested to join!");
      return;
    }
    if (!$this->canJoin()) {
      $this->teamFullMessage($requester);
    } else {
      $this->joinRequest($requester);
    }
  }

  public function attemptInvite(SwimPlayer $inviter, SwimPlayer $swimPlayer): void
  {
    var_dump("attempted the invite");
    if ($this->hasAlreadyInvited($swimPlayer)) {
      $inviter->sendMessage(TextFormat::YELLOW . "You already invited this player!");
      return;
    }
    if (!$this->canJoin()) {
      $this->teamFullMessage($inviter);
    } else {
      $this->invite($swimPlayer);
    }
  }

  /**
   * @throws ScoreFactoryException
   * @throws JsonException
   */
  public function attemptJoin(SwimPlayer $swimPlayer): void
  {
    if ($this->canJoin()) {
      $this->joined($swimPlayer);
    } else {
      $this->teamFullMessage($swimPlayer);
    }
  }

  public function teamFullMessage(SwimPlayer $swimPlayer): void
  {
    $swimPlayer->sendMessage(TextFormat::YELLOW . "This team is now full, cannot add anymore players");
  }

  private function clearPlayerData(SwimPlayer $swimPlayer): void
  {
    unset($this->joinRequests[$swimPlayer->getId()]);
    unset($this->outgoingInvites[$swimPlayer->getId()]);
    $swimPlayer->getInvites()->clearAllInvites();
    EventQueue::kit($swimPlayer);
  }

  /**
   * @throws ScoreFactoryException
   * @throws JsonException
   */
  public function joined(SwimPlayer $swimPlayer): void
  {
    $this->event->getTeamPlayerIsIn($swimPlayer)?->leave($swimPlayer, false); // remove from their old team
    $swimPlayer->getSceneHelper()->setTeamNumber($this->id);
    $this->clearPlayerData($swimPlayer);
    $this->currentTeamSize++;
    $size = $this->formatSize();
    $this->messageAll($swimPlayer->getRank()->rankString() . TextFormat::GREEN . " has joined the team " . $size);
    $this->members[$swimPlayer->getId()] = $swimPlayer;
    $swimPlayer->sendMessage(TextFormat::GREEN . "Successfully joined the team " . $size);
  }

  /**
   * @param bool $leavingNormally if player should join their own fresh solo team if this team is non-solo
   * @throws ScoreFactoryException
   * @throws JsonException
   */
  public function leave(SwimPlayer $swimPlayer, bool $leavingNormally = true): void
  {
    $this->clearPlayerData($swimPlayer);
    $this->currentTeamSize--;
    unset($this->members[$swimPlayer->getId()]);
    $this->messageAll($swimPlayer->getRank()->rankString() . TextFormat::YELLOW . " has left the team");

    if ($leavingNormally) {
      if ($this->currentTeamSize <= 0) {
        $this->event->leave($swimPlayer); // if they leave a solo team they leave the event as a whole
      } else { //otherwise left a non solo team
        $this->event->createSoloTeam($swimPlayer); // put them back on their solo team
        EventQueue::kit($swimPlayer); // re-kit them
      }
    }

    // logic for if we need to disband the team or assign a new owner
    if ($this->currentTeamSize <= 0) {
      $this->disband();
    } else if ($swimPlayer->getId() == $this->owner->getId()) {
      $this->ownerLeft();
    }
  }

  // find a new leader if there is one, if not then disband
  public function ownerLeft(): void
  {
    if (!empty($this->members)) {
      // Reset the owner to the first available member in the team
      reset($this->members);
      $newOwnerKey = key($this->members);
      $this->owner = $this->members[$newOwnerKey];

      // notify all team members about the new owner
      $this->messageAll("New team owner is now " . $this->owner->getName());
      EventQueue::kit($this->owner); // re-kit them
    } else {
      // If no members left, disband the team
      $this->disband();
    }
  }

  public function disband(): void
  {
    $this->event->removeTeam($this);
  }

  public function canJoin(): bool
  {
    return $this->currentTeamSize < $this->maxTeamSize;
  }

  public function hasAlreadyInvited(SwimPlayer $swimPlayer): bool
  {
    return isset($this->outgoingInvites[$swimPlayer->getId()]);
  }

  public function hasAlreadyRequested(SwimPlayer $swimPlayer): bool
  {
    return isset($this->joinRequests[$swimPlayer->getId()]);
  }

  public function hasPlayer(SwimPlayer $swimPlayer): bool
  {
    return isset($this->members[$swimPlayer->getId()]);
  }

  /**
   * @return SwimPlayer
   */
  public function getOwner(): SwimPlayer
  {
    return $this->owner;
  }

  public function isOwner(SwimPlayer $swimPlayer): bool
  {
    return $swimPlayer->getId() == $this->owner->getId();
  }

  /**
   * @return SwimPlayer[]
   */
  public function getMembers(): array
  {
    return $this->members;
  }

  public function getID(): int
  {
    return $this->id;
  }

  /**
   * @return int
   */
  public function getCurrentTeamSize(): int
  {
    return $this->currentTeamSize;
  }

}