<?php

namespace core\forms\parties;

use core\SwimCore;
use core\systems\party\Party;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\ModalForm;
use pocketmine\utils\TextFormat;

class FormPartyExit
{

  public static function formPartyDisband(SwimCore $core, SwimPlayer $player, Party $party): void
  {
    $form = new ModalForm(function (SwimPlayer $player, $data) use ($core, $party) {
      if ($data === null) return;

      if ($data == 1) {
        $core->getSystemManager()->getPartySystem()->disbandParty($party);
      }
    });

    $form->setTitle(TextFormat::RED . "Disband Party");
    $form->setContent(TextFormat::RED . "Are you sure you want to disband the party?");
    $form->setButton1("Yes");
    $form->setButton2("No");
    $player->sendForm($form);
  }

  public static function formPartyLeave(SwimCore $core, SwimPlayer $player, Party $party): void
  {
    $form = new ModalForm(function (SwimPlayer $player, $data) use ($core, $party) {
      if ($data === null) return;

      if ($data == 1) {
        $party->removePlayerFromParty($player);
        $party->partyMessage(TextFormat::YELLOW . $player->getNicks()->getNick() . " has left the party. " . $party->formatSize());
      }
    });

    $form->setTitle(TextFormat::RED . "Leave Party");
    $form->setContent(TextFormat::RED . "Are you sure you want to leave the party?");
    $form->setButton1("Yes");
    $form->setButton2("No");
    $player->sendForm($form);
  }

}