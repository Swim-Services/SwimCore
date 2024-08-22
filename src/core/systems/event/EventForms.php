<?php

namespace core\systems\event;

use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class EventForms
{

  public static function manageTeam(SwimPlayer $swimPlayer, ServerGameEvent $event, EventTeam $team): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($event, $team) {
      if ($data === null) return;

      switch ($data) {
        case 0:
          self::invitePlayer($player, $event, $team);
          break;
        case 1:
          self::kickPlayer($player, $team);
          break;
      }
    });

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Manage Your Team");
    $form->addButton(TextFormat::GREEN . "Invite Players");
    $form->addButton(TextFormat::RED . "Kick Players");
    $swimPlayer->sendForm($form);
  }

  private static function invitePlayer(SwimPlayer $swimPlayer, ServerGameEvent $event, EventTeam $team): void
  {
    $buttons = array();

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($event, $team, &$buttons) {
      if ($data === null) return;

      $invited = $buttons[$data];

      if ($invited instanceof SwimPlayer) {
        $team?->attemptInvite($player, $invited);
      } else {
        $player->sendMessage(TextFormat::RED . "Failed to invite player");
      }
    });

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Invite Players");
    foreach ($event->getInvitablePlayers($swimPlayer) as $player) {
      $form->addButton($player->getNicks()->getNick());
      $buttons[] = $player;
    }

    $swimPlayer->sendForm($form);
  }

  private static function kickPlayer(SwimPlayer $swimPlayer, EventTeam $team): void
  {
    $buttons = array();

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($team, &$buttons) {
      if ($data === null) return;

      $picked = $buttons[$data];
      if ($picked instanceof SwimPlayer) {
        $team?->leave($picked);
      } else {
        $player->sendMessage(TextFormat::RED . "Failed to kick player");
      }
    });

    $form->setTitle(TextFormat::RED . "Kick Players");
    foreach ($team->getMembers() as $member) {
      if ($member && $member !== $swimPlayer) {
        $form->addButton($member->getName());
        $buttons[] = $member;
      }
    }

    $swimPlayer->sendForm($form);
  }

  public static function leaveTeam(SwimPlayer $swimPlayer, EventTeam $team): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($team) {
      if ($data === null) return;

      if ($data == 0) {  // Yes
        $team?->leave($player);
      }
    });

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Confirm Leave");
    $msg = "Are you sure you want to leave? If you are in a team you will leave the team and be placed on your own solo team, but you will still be in the event.";
    $msg2 = "If you are in a solo team than this will leave the event entirely.";
    $form->setContent($msg . " " . $msg2);
    $form->addButton(TextFormat::GREEN . "Yes");
    $form->addButton(TextFormat::RED . "No");
    $swimPlayer->sendForm($form);
  }

  // event team invites are stored in the player's invite component
  public static function viewTeamInvites(SwimPlayer $swimPlayer): void
  {
    $buttons = array();
    $invites = $swimPlayer->getInvites()->getTeamInvites();

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($invites, &$buttons) {
      if ($data === null) {
        return;
      }

      $team = $buttons[$data];

      if ($team instanceof EventTeam) {
        self::handleInviteResponse($player, $team);
      }
    });

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Your Team Invites");

    foreach ($invites as $inviteTeam) {
      if ($inviteTeam instanceof EventTeam) {
        $form->addButton(TextFormat::GREEN . "Join " . $inviteTeam->getOwner()->getName() . "'s Team");
        $buttons[] = $inviteTeam;
      }
    }

    $swimPlayer->sendForm($form);
  }

  private static function handleInviteResponse(SwimPlayer $player, EventTeam $selectedTeam): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($selectedTeam) {
      if ($data === null) {
        return;
      }

      switch ($data) {
        case 0: // Accept
          if ($selectedTeam && $player->getSceneHelper()?->getEvent()?->isValidTeam($selectedTeam)) {
            $selectedTeam->attemptJoin($player);
          } else {
            $player->getInvites()?->removeTeamInvite($selectedTeam);
            $player->sendMessage(TextFormat::RED . "That team does not exist anymore.");
          }
          break;
        case 1: // Reject
          $player->sendMessage(TextFormat::RED . "You have rejected the invite.");
          break;
      }
    });

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Respond to Invite");
    $form->setContent("Do you want to join " . $selectedTeam->getOwner()->getName() . "'s team?");
    $form->addButton(TextFormat::GREEN . "Accept");
    $form->addButton(TextFormat::RED . "Reject");

    // Send the response form to the player
    $player->sendForm($form);
  }

  // 3 buttons, 1 for adding player to blocked list, 1 for removing players from blocked list, 1 for kicking players (adds them to event blocked list too)
  public static function manageEventForm(SwimPlayer $swimPlayer, ServerGameEvent $event, EventTeam $team): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($event, $team) {
      if ($data === null) return; // Handle form close

      switch ($data) {
        case 0:
          self::addPlayerToBlockedListForm($player, $event);
          break;
        case 1:
          self::removePlayerFromBlockedListForm($player, $event);
          break;
        case 2:
          self::kickPlayerForm($player, $event);
          break;
      }
    });

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Manage Event");
    $form->addButton(TextFormat::GREEN . "Add Player to Blocked List");
    $form->addButton(TextFormat::RED . "Remove Player from Blocked List");
    $form->addButton(TextFormat::GOLD . "Kick and Block Player");
    $swimPlayer->sendForm($form);
  }

  // form to block any player online, if they are in the event they get kicked
  private static function addPlayerToBlockedListForm(SwimPlayer $swimPlayer, ServerGameEvent $event): void
  {
    $buttons = array();
    $players = Server::getInstance()->getOnlinePlayers(); // block any player online, otherwise we could use $event->getPlayers()

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($players, $event, &$buttons) {
      if ($data === null) return;

      $selectedPlayer = $buttons[$data];
      if ($selectedPlayer && $event) {
        $event->addToBlockedList($selectedPlayer);
        $player->sendMessage(TextFormat::RED . "Player " . $selectedPlayer->getName() . " has been added to the blocked list.");
      }
    });

    $form->setTitle(TextFormat::RED . "Add Player to Blocked List");
    foreach ($players as $player) {
      if (!$event->isBlocked($player) && $player !== $swimPlayer) {
        $form->addButton($player->getName());
        $buttons[] = $player;
      }
    }

    $swimPlayer->sendForm($form);
  }


  private static function removePlayerFromBlockedListForm(SwimPlayer $swimPlayer, ServerGameEvent $event): void
  {
    $buttons = array();
    $blockedPlayers = $event->getBlockedPlayers();

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($blockedPlayers, $event, &$buttons) {
      if ($data === null) return;

      $selectedPlayer = $buttons[$data];
      if ($selectedPlayer) {
        $event?->removeFromBlockedList($selectedPlayer);
        $player->sendMessage(TextFormat::GREEN . "Player " . $selectedPlayer->getName() . " has been removed from the blocked list.");
      }
    });

    $form->setTitle(TextFormat::GREEN . "Remove Player from Blocked List");
    foreach ($blockedPlayers as $player) {
      if ($player) {
        $form->addButton($player->getName());
        $buttons[] = $player;
      }
    }

    $swimPlayer->sendForm($form);
  }

  /**
   * Form to kick a player from the event and add them to the blocked list.
   */
  private static function kickPlayerForm(SwimPlayer $swimPlayer, ServerGameEvent $event): void
  {
    $buttons = array();

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($event, &$buttons) {
      if ($data === null) return;

      $playerToKick = $buttons[$data];
      if ($playerToKick instanceof SwimPlayer) {
        // remove them from there team first
        if ($event) {
          $team = $event->getTeamPlayerIsIn($playerToKick);
          $team?->leave($playerToKick, false);

          // then remove them from the event and block them
          $event->leave($playerToKick);
          $event->addToBlockedList($playerToKick);
          $player->sendMessage(TextFormat::RED . "You have kicked and blocked " . $playerToKick->getNicks()->getNick());
        }
      }
    });

    $form->setTitle(TextFormat::DARK_RED . "Kick and Block Player");
    foreach ($event->getPlayers() as $player) {
      if ($player !== $swimPlayer) { // Exclude the managing player
        $form->addButton($player->getName());
        $buttons[] = $player;
      }
    }

    $swimPlayer->sendForm($form);
  }

}