<?php

namespace core\forms\parties;

use core\SwimCore;
use core\systems\party\Party;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

class FormPartyInvite
{

  public static function formPartyInvite(SwimCore $core, SwimPlayer $player, Party $party): void
  {
    if (!$party->canAddPlayerToParty()) {
      $party->sizeMessage($player);
      return;
    }

    $buttons = [];

    // first get all players in hub that we can invite
    $playersInHub = $core->getSystemManager()->getSceneSystem()->getScene('Hub')->getPlayers();
    $availablePlayers = [];
    foreach ($playersInHub as $plr) {
      if (!$plr->getSceneHelper()->isInParty() && $plr->getInvites()->canSendInvite('partyInvites')) {
        $availablePlayers[] = $plr;
      }
    }

    $form = new SimpleForm(function (SwimPlayer $swimPlayer, $data) use ($core, &$buttons, $party) {
      if ($data === null) return;

      // data in this case will be an int for the index in the buttons array that was clicked
      // Fetch the name of the player based on the index
      $playerNames = array_keys($buttons);
      if (!isset($playerNames[$data])) return;
      $playerName = $playerNames[$data];
      if (!isset($buttons[$playerName])) return;
      $invited = $buttons[$playerName];

      // if invited player still online and in the hub and party its self is valid to invite then send the invite
      if ($invited instanceof SwimPlayer) {
        $party->invitePlayer($invited, $swimPlayer);
      }
    });

    // add the players to the form
    foreach ($availablePlayers as $p) {
      $buttons[$p->getName()] = $p;
      $form->addButton($p->getNicks()->getNick());
    }

    $form->setTitle(TextFormat::GREEN . "Invite Player " . $party->formatSize());
    $player->sendForm($form);
  }

  public static function formPartyRequests(SwimCore $core, SwimPlayer $player, Party $party): void
  {
    if (!$party->canAddPlayerToParty()) {
      $party->sizeMessage($player);
      return;
    }

    $buttons = [];

    // first get all players in our join requests
    $players = $party->getJoinRequests();
    $availablePlayers = [];
    foreach ($players as $plr) {
      if ($plr->isConnected() && !$plr->getSceneHelper()->isInParty()) {
        $availablePlayers[] = $plr;
      }
    }

    $form = new SimpleForm(function (SwimPlayer $swimPlayer, $data) use ($core, &$buttons, $party) {
      if ($data === null) return;

      // data in this case will be an int for the index in the buttons array that was clicked
      // Fetch the name of the player based on the index
      $playerNames = array_keys($buttons);
      if (!isset($playerNames[$data])) return;
      $playerName = $playerNames[$data];
      if (!isset($buttons[$playerName])) return;
      $requested = $buttons[$playerName];

      // if invited player still online and in the hub and party its self is valid to invite then send the invite
      if ($requested instanceof SwimPlayer && $requested->isConnected() && !$requested->getSceneHelper()->isInParty()
        && $requested->getSceneHelper()->getScene()->getSceneName() == "Hub") {
        $requested->sendMessage(TextFormat::GREEN . "You joined the party: " . TextFormat::YELLOW . $party->getPartyName());
        $party->addPlayerToParty($requested);
      }
    });

    // add the players to the form
    foreach ($availablePlayers as $p) {
      $buttons[$p->getName()] = $p;
      $form->addButton($p->getNicks()->getNick());
    }

    $form->setTitle(TextFormat::GREEN . "Join Requests " . $party->formatSize());
    $player->sendForm($form);
  }

}