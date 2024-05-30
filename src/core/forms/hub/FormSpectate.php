<?php

namespace core\forms\hub;

use core\scenes\duel\Duel;
use core\SwimCore;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat;

class FormSpectate
{

  public static function spectateSelectionForm(SwimCore $core, SwimPlayer $swimPlayer): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $swimPlayer, $data) use ($core, &$buttons) {
      if ($data === null) {
        return;
      }

      // data in this case will be an int for the index in the buttons array that was clicked
      // Fetch the name of the scene based on the index
      $sceneNames = array_keys($buttons);
      if (isset($sceneNames[$data])) {
        $sceneName = $sceneNames[$data];

        // Now fetch the corresponding duel object using the scene name
        if (isset($buttons[$sceneName])) {
          $duel = $buttons[$sceneName];
          if ($duel instanceof Duel) {
            $duel->sceneAnnouncement(TextFormat::AQUA . $swimPlayer->getNicks()->getNick() . " Started Spectating");
            $swimPlayer->getSceneHelper()->setNewScene($sceneName);
            $duel->getTeamManager()->getSpecTeam()->addPlayer($swimPlayer);
            $swimPlayer->setGamemode(GameMode::SPECTATOR);
            $swimPlayer->setInvisible();
            $swimPlayer->sendMessage(TextFormat::GREEN . "Sending you to " . TextFormat::YELLOW . $sceneName);
            // teleport to the first non-spec player in the scene
            foreach ($duel->getPlayers() as $plr) {
              if($plr->getGamemode() !== GameMode::SPECTATOR) {
                $swimPlayer->teleport($plr->getPosition());
                break;
              }
            }
            return;
          }
        }
      }

      // if something went wrong
      $swimPlayer->sendMessage(TextFormat::YELLOW. "Duel ended before you could join");
    });

    $form->setTitle(TextFormat::GREEN . "Select Duel to Spectate");

    // we need to iterate all the scenes marked as duels and push them back into an array that generates mapped buttons
    $ss = $core->getSystemManager()->getSceneSystem();
    $scenes = $ss->getScenes();
    foreach ($scenes as $name => $scene) {
      if ($scene->isDuel()) {
        $buttons[$name] = $scene;
        $form->addButton($name, 0, $scene->getIcon());
      }
    }

    $swimPlayer->sendForm($form);
  }

}