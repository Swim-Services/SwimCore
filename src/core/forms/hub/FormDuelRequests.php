<?php

namespace core\forms\hub;

use core\scenes\duel\Duel;
use core\scenes\duel\IconHelper;
use core\scenes\hub\Queue;
use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use jackmd\scorefactory\ScoreFactoryException;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

/* this class will have 4 forms
* 1. selecting if you are going to view possible opponents or going to view duel requests
* 2. viewing players to send a duel request to
* 3. sending a duel request to a specific player
* 4. viewing duel requests to accept
*/

class FormDuelRequests
{

  public static function duelSelectionBase(SwimCore $core, SwimPlayer $swimPlayer): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core) {
      if ($data === null) {
        return;
      }

      if ($data == 0) {
        self::viewDuelRequests($core, $player);
      } elseif ($data == 1) {
        self::viewPossibleOpponents($core, $player);
      } else {
        $player->sendMessage(TextFormat::RED . "Error");
      }
    });

    $form->setTitle(TextFormat::GREEN . "Duel Manager");

    // make buttons
    $form->addButton(TextFormat::RED . "View Duel Requests " . TextFormat::DARK_GRAY . "["
      . TextFormat::AQUA . count($swimPlayer->getInvites()->getDuelInvites()) . TextFormat::DARK_GRAY . "]");
    $form->addButton(TextFormat::DARK_GREEN . "Send a Duel Request");

    $swimPlayer->sendForm($form);
  }

  private static function viewDuelRequests(SwimCore $core, SwimPlayer $swimPlayer): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core, &$buttons) {
      if ($data === null) {
        return;
      }

      // First fetch sender name
      $senders = array_keys($buttons);
      if (isset($senders[$data])) {
        // get as player
        $sender = $senders[$data];
        $senderPlayer = $core->getServer()->getPlayerExact($sender);
        if ($senderPlayer instanceof SwimPlayer) {
          // check if this sender is in the hub and the mode has an available map
          $inviteData = $buttons[$sender];
          if ($senderPlayer->getSceneHelper()->getScene()->getSceneName() === "Hub") {
            if ($core->getSystemManager()->getMapsData()->modeHasAvailableMap($inviteData['mode'])) {
              self::startDuel($core, $senderPlayer, $player, $inviteData);
            } else {
              $player->sendMessage(TextFormat::RED . "No map is currently available for that mode, try again later");
            }
            return;
          }
        }
      }

      $player->sendMessage(TextFormat::RED . "Duel Expired");
    });

    $form->setTitle("Duel Requests");

    // make buttons from requests
    $requests = $swimPlayer->getInvites()->getDuelInvites();
    foreach ($requests as $sender => $inviteData) {
      $buttons[$sender] = $inviteData;
      $mode = $inviteData['mode'];
      $form->addButton(TextFormat::GREEN . $sender . TextFormat::GRAY . " | " . TextFormat::RED . ucfirst($mode), 0, IconHelper::getIcon($mode));
    }

    $swimPlayer->sendForm($form);
  }

  /**
   * @throws ScoreFactoryException
   */
  private static function startDuel(SwimCore $core, SwimPlayer $user, SwimPlayer $inviter, $inviteData): void
  {
    // insanely based method to get the queue scene and use one of its functions to start a duel that way
    $queue = $core->getSystemManager()->getSceneSystem()->getScene('Queue');
    if ($queue instanceof Queue) {
      $queue->publicDuelStart($user, $inviter, $inviteData['mode'], $inviteData['map']);
    }
  }

  // get all players in hub scene with duel invites on
  private static function viewPossibleOpponents(SwimCore $core, SwimPlayer $swimPlayer): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core, &$buttons) {
      if ($data === null) {
        return;
      }

      // Fetch Swim Player from button
      if (isset($buttons[$data])) {
        $playerToDuel = $buttons[$data];
        if ($playerToDuel instanceof SwimPlayer) {
          self::duelSelection($core, $player, $playerToDuel);
          return;
        }
      }

      $player->sendMessage(TextFormat::RED . "Error");
    });

    $form->setTitle(TextFormat::GREEN . "Choose an Opponent");

    // get the array of swim players in the hub
    $players = $core->getSystemManager()->getSceneSystem()->getScene("Hub")->getPlayers();

    $id = $swimPlayer->getId();
    foreach ($players as $plr) {
      if ($plr instanceof SwimPlayer) {
        // skip self
        if ($plr->getId() != $id) {
          if ($plr->getSettings()->getToggle('duelInvites')) {
            $buttons[] = $plr;
            $form->addButton($plr->getRank()->rankString());
          }
        }
      }
    }

    $swimPlayer->sendForm($form);
  }

  private static function duelSelection(SwimCore $core, SwimPlayer $user, SwimPlayer $invited): void
  {
    $modes = Duel::$MODES;
    $maps = ['random']; // TO DO : fill in properly

    $form = new CustomForm(function (SwimPlayer $player, $data) use ($core, $invited, $user, &$modes, &$maps) {
      if ($data === null) {
        return;
      }

      // verify person we are inviting still exists
      if ($invited && $invited->isOnline() && isset($modes[$data[0]])) {
        // get game mode
        $mode = $modes[$data[0]];

        // map selection parsing
        $map = 'random';
        if (isset($data[1]) && isset($maps[$data[1]])) {
          $map = $maps[$data[1]];
        }

        // then send duel invite
        $invited->getInvites()->duelInvitePlayer($player, $mode, $map);
      } else {
        $player->sendMessage(TextFormat::RED . "Error, other player might not be connected");
      }
    });

    $form->setTitle(TextFormat::GREEN . "Select Game Mode");

    $form->addDropdown("Game Mode", $modes, 0);

    $rankLevel = $user->getRank()->getRankLevel();
    if ($rankLevel > Rank::DEFAULT_RANK) {
      $form->addDropdown("Pick Map", $maps, 0);
    } else {
      $form->addLabel(TextFormat::AQUA . "To Choose a Map, Buy a Rank at " . TextFormat::DARK_AQUA . "swim.tebex.io"
        . TextFormat::LIGHT_PURPLE . " or Boost " . TextFormat::DARK_PURPLE . "discord.gg/swim");
    }

    $user->sendForm($form);
  }

}