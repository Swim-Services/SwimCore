<?php

namespace core\forms\parties;

use core\SwimCore;
use core\systems\party\Party;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

class FormPartyCreate
{

  public static function partyBaseForm(SwimCore $core, SwimPlayer $player): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core) {
      if ($data === null) return;

      if ($data == 0) {
        // create a party form
        self::partyCreateForm($core, $player);
      } elseif ($data == 1) {
        // view party invites
        self::viewPartyInvitesForm($core, $player);
      } elseif ($data == 2) {
        // join a party
        self::partyJoinForm($core, $player);
      }
    });

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Party Menu");
    $form->addButton(TextFormat::GREEN . "Create a Party", 0, "textures/items/cake");
    $form->addButton(TextFormat::MINECOIN_GOLD . "Party Invites", 0, "textures/items/cookie");
    $form->addButton(TextFormat::BLUE . "Join a Party", 0, "textures/items/diamond");
    $player->sendForm($form);
  }

  private static function viewPartyInvitesForm(SwimCore $core, SwimPlayer $player): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core, &$buttons) {
      if ($data === null) return;

      $partyNames = array_keys($buttons);
      if (!isset($partyNames[$data])) return;
      $partyName = $partyNames[$data];
      if (!isset($buttons[$partyName])) return;
      $party = $buttons[$partyName];

      if ($party instanceof Party) {
        if (!$party->isInDuel() && $party->canAddPlayerToParty()) {
          $party->addPlayerToParty($player);
        } else {
          $player->sendMessage(TextFormat::YELLOW . "Could not join party at this time");
        }
      }
    });

    // add parties to the buttons
    foreach ($player->getInvites()->getPartyInvites() as $partyName => $party) {
      if ($party instanceof Party) {
        if (!$party->isInDuel() && $party->canAddPlayerToParty()) {
          $buttons[$partyName] = $party;
          $form->addButton($partyName . TextFormat::GRAY . " | " . $party->formatSize());
        }
      }
    }

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Your Party Invites");

    $player->sendForm($form);
  }

  private static function partyCreateForm(SwimCore $core, SwimPlayer $player): void
  {
    $form = new CustomForm(function (SwimPlayer $player, $data) use ($core) {
      if ($data === null) return;

      $partySystem = $core->getSystemManager()->getPartySystem();
      $partyName = $data[0];
      if ($partyName == "") {
        $partyName = $player->getNicks()->getNick() . "'s Party";
      }

      if ($partySystem->partyNameTaken($partyName)) {
        $player->sendMessage(TextFormat::RED . "That party name is taken!");
        return;
      }

      $partySystem->addParty(new Party($core, $partyName, $player));
      // $player->sendMessage(TextFormat::GREEN . "Created your Party " . TextFormat::YELLOW . $partyName);
      // $swimPlayer->scenesManager->setCurrentScene(new HubParty($core, $player, $swimPlayer, $party)); // no party hub scene
    });

    $form->setTitle(TextFormat::GREEN . "Create Party");
    $form->addInput(TextFormat::GREEN . "Set Party Name", $player->getNicks()->getNick() . "'s Party");
    $player->sendForm($form);
  }

  private static function partyJoinForm(SwimCore $core, SwimPlayer $player): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core, &$buttons) {
      if ($data === null) return;

      $partyNames = array_keys($buttons);
      if (!isset($partyNames[$data])) return;
      $partyName = $partyNames[$data];
      if (!isset($buttons[$partyName])) return;
      $partyData = $buttons[$partyName];

      $party = $partyData['party'];
      $openJoin = $partyData['open'];

      if ($party instanceof Party) {
        if (!$party->isInDuel() && $party->canAddPlayerToParty()) {
          if ($openJoin) {
            $party->addPlayerToParty($player);
          } else {
            $party->sendJoinRequest($player);
          }
        } else {
          $player->sendMessage(TextFormat::RED . "Party no longer available to join");
        }
      }
    });

    // add parties to the buttons
    foreach ($core->getSystemManager()->getPartySystem()->getParties() as $partyName => $party) {
      if ($party instanceof Party) {
        $allowRequests = $party->getSetting('allowJoinRequests');
        $openJoin = $party->getSetting('openJoin');
        if (!$party->isInDuel() && $party->canAddPlayerToParty() && ($allowRequests || $openJoin)) {
          $buttons[$partyName] = ['party' => $party, 'open' => $openJoin];
          // $label = $openJoin ? "Open to Join" : "Request to Join"; // not sure what label even is (sub text?)
          $form->addButton($partyName . TextFormat::GRAY . " | " . $party->formatSize());
        }
      }
    }

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Parties Available to Join");

    $player->sendForm($form);
  }

}