<?php

namespace core\forms\hub;

use core\systems\player\SwimPlayer;
use core\utils\loaders\WorldLoader;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

class FormFFA
{

  // Constants for arena types
  private const SG_KITS_FFA = 0;
  private const NODEBUFF_FFA = 1;
  private const MIDFIGHT_FFA = 2;
  private const SKYWARS_FFA = 3;

  /**
   * Displays a selection form for FFA arenas to a player.
   * @param SwimPlayer $player The player to display the form to.
   */
  public static function ffaSelectionForm(SwimPlayer $player): void
  {

    $form = new SimpleForm(function (SwimPlayer $player, $data) {
      if ($data === null) {
        return; // Player closed the form
      }

      switch ($data) {
        case self::NODEBUFF_FFA:
          $player->getSceneHelper()->setNewScene('NodebuffFFA');
          break;
        case self::MIDFIGHT_FFA:
          $player->getSceneHelper()->setNewScene('MidFightFFA');
          break;
      }
    });

    $form->setTitle(TextFormat::GREEN . "Select FFA Arena");

    $form->addButton("§4Nodebuff §8[§a" . WorldLoader::getWorldPlayerCount("PotFFA") . "§8]", 0, "textures/items/potion_bottle_heal");
    $form->addButton("§4Midfight §8[§a" . WorldLoader::getWorldPlayerCount("midFFA") . "§8]", 0, "textures/items/diamond_chestplate");

    $player->sendForm($form);
  }


}