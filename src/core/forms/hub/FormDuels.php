<?php

namespace core\forms\hub;

use core\scenes\duel\Boxing;
use core\scenes\duel\Duel;
use core\scenes\duel\Midfight;
use core\scenes\duel\Nodebuff;
use core\scenes\hub\Queue;
use core\SwimCoreInstance;
use core\systems\player\SwimPlayer;
use core\systems\scene\SceneSystem;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat as TF;

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

    $sceneSystem = SwimCoreInstance::getInstance()->getSystemManager()->getSceneSystem();
    /** @var Queue $queue */
    $queue = $sceneSystem->getScene("Queue");

    $queueCount = TF::GREEN . "Queued: " . TF::YELLOW . $sceneSystem->getQueuedCount();
    $duelCount = TF::GREEN . "In Duels: " . TF::BLUE . $sceneSystem->getInDuelsCount();

    $form->setTitle(TF::GREEN . "Select Game");
    $form->setContent($queueCount . TF::DARK_GRAY . " | " . $duelCount);
    $form->addButton("§4Nodebuff " . self::formatModePlayerCounts('nodebuff', Nodebuff::class, $queue, $sceneSystem), 0, Nodebuff::getIcon());
    $form->addButton("§4Boxing " . self::formatModePlayerCounts('boxing', Boxing::class, $queue, $sceneSystem), 0, Boxing::getIcon());
    $form->addButton("§4Midfight " . self::formatModePlayerCounts('midfight', Midfight::class, $queue, $sceneSystem), 0, Midfight::getIcon());
    $form->addButton("§4BUHC " . self::formatModePlayerCounts('buhc', BUHC::class, $queue, $sceneSystem), 0, BUHC::getIcon());
    $player->sendForm($form);
  }

  private static function formatModePlayerCounts(string $mode, string $sceneClassPath, Queue $queue, SceneSystem $sceneSystem): string
  {
    $queued = /*TF::GREEN . "Queued: " .*/
      TF::YELLOW . self::getQueuedCountOfMode($mode, $queue);
    $playing = /*TF::GREEN . "In Duel: " .*/
      TF::BLUE . $sceneSystem->getSceneInstanceOfCount($sceneClassPath);
    return TF::DARK_GRAY . "[" . $queued . TF::DARK_GRAY . " | " . $playing . TF::DARK_GRAY . "]";
  }

  private static function getQueuedCountOfMode(string $mode, Queue $queue): int
  {
    $teamPlayers = $queue->getTeamManager()->getTeam($mode)?->getPlayers() ?? null;

    if ($teamPlayers) {
      return count($teamPlayers);
    }

    return 0;
  }

}