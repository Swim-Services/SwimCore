<?php

namespace core\systems\scene\misc;

use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\item\Compass;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class SpectatorCompass extends Compass
{

  public function __construct(ItemIdentifier $identifier = new ItemIdentifier(ItemTypeIds::COMPASS), string $name = "Compass", array $enchantmentTags = [])
  {
    parent::__construct($identifier, $name, $enchantmentTags);
    $this->setCustomName(TextFormat::GREEN . "Spectator Compass");
  }

  public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult
  {
    if ($player instanceof SwimPlayer)
      self::spectatorForm($player);

    return ItemUseResult::NONE;
  }

  public static function spectatorForm(SwimPlayer $player): void
  {
    $scene = $player->getSceneHelper()->getScene();
    if (!isset($scene)) return;

    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use (&$buttons) {
      if ($data === null) {
        return;
      }

      // Fetch Swim Player from button and warp there
      if (isset($buttons[$data])) {
        $playerSelected = $buttons[$data];
        if ($playerSelected instanceof SwimPlayer) {
          $player->teleport($playerSelected->getPosition());
          $player->sendMessage(TextFormat::GREEN . "Warping to " . $playerSelected->getNicks()->getNick());
          return;
        }
      }

      $player->sendMessage(TextFormat::RED . "Error");
    });

    $form->setTitle(TextFormat::GREEN . "Teleport to Player");

    // get the array of swim players in the scene that aren't spectators
    $players = $scene->getPlayers();

    foreach ($players as $plr) {
      if ($plr instanceof SwimPlayer) {
        // skip self and spectators
        if ($plr !== $player && $plr->getGamemode() !== GameMode::SPECTATOR && !$scene->getPlayerTeam($plr)?->isSpecTeam()) {
          $buttons[] = $plr;
          $form->addButton($plr->getRank()->rankString());
        }
      }
    }

    $player->sendForm($form);
  }

}