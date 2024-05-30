<?php

namespace core\forms\parties;

use core\scenes\duel\Boxing;
use core\scenes\duel\Duel;
use core\scenes\duel\Midfight;
use core\scenes\duel\Nodebuff;
use core\SwimCore;
use core\systems\party\Party;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

class FormPartyDuels
{

  public static function baseForm(SwimCore $core, SwimPlayer $swimPlayer, Party $party): void
  {
    $form = new SimpleForm(function (SwimPlayer $swimPlayer, $data) use ($core, &$buttons, $party) {
      if ($data === null) return;

      if (!$party->isInDuel()) {
        switch ($data) {
          case 0:
            self::selfPartyDuel($core, $swimPlayer, $party);
            break;
          case 1:

            break;
        }
      }
    });

    $form->setTitle(TextFormat::DARK_PURPLE . "Select Mode");
    $form->addButton(TextFormat::DARK_AQUA . "Duel own Party");
    $form->addButton(TextFormat::YELLOW . "COMING SOON | Party Mini Games");
    $swimPlayer->sendForm($form);
  }

  private static function selfPartyDuel(SwimCore $core, SwimPlayer $swimPlayer, Party $party): void
  {
    if ($party->getCurrentPartySize() <= 1) {
      $swimPlayer->sendMessage(TextFormat::RED . "You need at least 2 people in the party to start a duel!");
      return;
    }

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core, $party) {
      if ($data === null) {
        return;
      }

      // check
      if ($party->getCurrentPartySize() <= 1) {
        $player->sendMessage(TextFormat::RED . "You need at least 2 people in the party to start a duel!");
        return;
      }

      $mode = Duel::$MODES[$data] ?? null;

      if (isset($mode) && !$party->isInDuel()) {
        $party->startSelfDuel($mode);
      }
    });

    $form->setTitle(TextFormat::GREEN . "Select Game");
    $form->addButton("§4Nodebuff", 0, Nodebuff::getIcon());
    $form->addButton("§4Boxing", 0, Boxing::getIcon());
    $form->addButton("§4Midfight", 0, Midfight::getIcon());
    $swimPlayer->sendForm($form);
  }

  public static function pickOtherPartyToDuel(SwimCore $core, SwimPlayer $player, Party $party): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core, &$buttons, $party) {
      if ($data === null) return;

      $partyNames = array_keys($buttons);
      if (!isset($partyNames[$data])) return;
      $partyName = $partyNames[$data];
      if (!isset($buttons[$partyName])) return;
      $otherParty = $buttons[$partyName];

      if ($otherParty instanceof Party) {
        if (!$otherParty->isInDuel() && $otherParty->getSetting('allowDuelInvites')) {
          self::sendPartyDuelRequest($player, $party, $otherParty);
        } else {
          $player->sendMessage(TextFormat::RED . "Party no longer available to duel");
        }
      }
    });

    // add parties to the buttons
    foreach ($core->getSystemManager()->getPartySystem()->getParties() as $partyName => $p) {
      if ($p instanceof Party) {
        if (!$p->isInDuel() && $p->canAddPlayerToParty() && $p->getSetting('allowDuelInvites') && $party !== $p) {
          $buttons[$partyName] = $p;
          // $label = $openJoin ? "Open to Join" : "Request to Join"; // not sure what label even is (sub text?)
          $form->addButton($partyName . TextFormat::GRAY . " | " . $p->formatSize());
        }
      }
    }

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Parties Available to Duel");

    $player->sendForm($form);
  }

  private static function sendPartyDuelRequest(SwimPlayer $player, Party $senderParty, Party $otherParty): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($senderParty, $otherParty) {
      if ($data === null) {
        return;
      }

      $mode = Duel::$MODES[$data] ?? null;
      
      if (isset($mode) && !$otherParty->isInDuel() && !$senderParty->isInDuel()) {
        $otherParty->duelInvite($player, $senderParty, $mode);
      }
    });

    $form->setTitle(TextFormat::GREEN . "Select Game");
    $form->addButton("§4Nodebuff", 0, Nodebuff::getIcon());
    $form->addButton("§4Boxing", 0, Boxing::getIcon());
    $form->addButton("§4Midfight", 0, Midfight::getIcon());
    $player->sendForm($form);
  }

  public static function acceptPartyDuelRequests(SwimPlayer $player, Party $party): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use (&$buttons, $party) {
      if ($data === null) return;

      $partyNames = array_keys($buttons);
      if (!isset($partyNames[$data])) return;
      $partyName = $partyNames[$data];
      if (!isset($buttons[$partyName])) return;
      $partyData = $buttons[$partyName];

      $otherParty = $partyData['party'];
      $mode = $partyData['mode'];

      if ($otherParty instanceof Party) {
        if (!$otherParty->isInDuel()) {
          $party->startPartyVsPartyDuel($otherParty, $mode);
        } else {
          $player->sendMessage(TextFormat::RED . "Party no longer available to duel");
        }
      }
    });

    // add parties to the buttons
    foreach ($party->getDuelRequests() as $text => $partyData) {
      if (!$party->isInDuel()) {
        $buttons[$text] = $partyData;
        // $label = $openJoin ? "Open to Join" : "Request to Join"; // not sure what label even is (sub text?)
        $form->addButton($text . TextFormat::DARK_GRAY . " | " . $partyData['party']->formatSize());
      }
    }

    $form->setTitle(TextFormat::LIGHT_PURPLE . "Party Duel Requests");

    $player->sendForm($form);
  }

}