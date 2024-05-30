<?php

namespace core\forms\hub;

use core\scenes\duel\Boxing;
use core\scenes\duel\Duel;
use core\scenes\duel\Midfight;
use core\scenes\duel\Nodebuff;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

class FormDuels
{

  public static function duelSelectionForm(SwimPlayer $player): void
  {
    $form = new SimpleForm(function (SwimPlayer $player, $data) {
      if ($data === null) {
        return;
      }

      $mode = Duel::$MODES[$data] ?? null;

      if (isset($mode)) {
        $sceneHelper = $player->getSceneHelper();
        $sceneHelper->setNewScene('Queue');
        $sceneHelper->getScene()->getTeamManager()->getTeam($mode)?->addPlayer($player);
      }
    });

    $form->setTitle(TextFormat::GREEN . "Select Game");
    $form->addButton("§4Nodebuff", 0, Nodebuff::getIcon());
    $form->addButton("§4Boxing", 0, Boxing::getIcon());
    $form->addButton("§4Midfight", 0, Midfight::getIcon());
    $player->sendForm($form);
  }

}