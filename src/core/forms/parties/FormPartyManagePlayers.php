<?php

namespace core\forms\parties;

use core\SwimCore;
use core\systems\party\Party;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

class FormPartyManagePlayers
{

  public static function listPlayers(SwimCore $core, SwimPlayer $player, Party $party): void
  {
    $buttons = [];

    // first get all players in the party
    $players = $party->getPlayers();
    $availablePlayers = [];
    foreach ($players as $plr) {
      if ($plr === $player) continue; // skip self
      $availablePlayers[] = $plr;
    }

    $form = new SimpleForm(function (SwimPlayer $swimPlayer, $data) use ($core, &$buttons, $party) {
      if ($data === null) return;

      // data in this case will be an int for the index in the buttons array that was clicked
      // Fetch the name of the player based on the index
      $playerNames = array_keys($buttons);
      if (!isset($playerNames[$data])) return;
      $playerName = $playerNames[$data];
      if (!isset($buttons[$playerName])) return;
      $selected = $buttons[$playerName];

      // if invited player still online and in the hub and in party then manage them
      if ($selected instanceof SwimPlayer && $selected->isConnected() && $selected->getSceneHelper()->getParty() === $party) {
        self::manageForm($core, $selected, $swimPlayer, $party);
      }
    });

    // add the players to the form
    foreach ($availablePlayers as $p) {
      $buttons[$p->getName()] = $p;
      $form->addButton($p->getNicks()->getNick());
    }

    $form->setTitle(TextFormat::GREEN . "Manage Players " . $party->formatSize());
    $player->sendForm($form);
  }

  private static function manageForm(SwimCore $core, SwimPlayer $selected, SwimPlayer $mod, Party $party): void
  {
    $form = new SimpleForm(function (SwimPlayer $swimPlayer, $data) use ($core, &$buttons, $party, $selected) {
      if ($data === null) return;

      if ($selected->isConnected() && !$party->isInDuel() && $party->hasPlayer($selected)) {
        switch ($data) {
          case 0:
            $party->removePlayerFromParty($selected);
            $party->partyMessage(TextFormat::YELLOW . $swimPlayer->getNicks()->getNick() . TextFormat::GREEN . " kicked " .
              TextFormat::YELLOW . $selected->getNicks()->getNick() . TextFormat::GREEN . " from the party! " . $party->formatSize());
            $selected->sendMessage(TextFormat::YELLOW . "You were removed from the party");
            break;
          case 1:
            $party->setPartyLeader($selected);
            $party->partyMessage(TextFormat::YELLOW . $selected->getNicks()->getNick() . TextFormat::GREEN . " is now the party leader!");
            $party->setHubKits();
            break;
          case 2:
            self::listPlayers($core, $swimPlayer, $party);
            break;
        }
      }
    });

    $form->setTitle("Select Action");
    $form->setContent(TextFormat::YELLOW . "Managing: " . TextFormat::GREEN . $selected->getNicks()->getNick());
    $form->addButton(TextFormat::RED . "Remove From Party");
    $form->addButton(TextFormat::RED . "Promote to Owner");
    $form->addButton(TextFormat::YELLOW . "Back");
    $mod->sendForm($form);
  }

}