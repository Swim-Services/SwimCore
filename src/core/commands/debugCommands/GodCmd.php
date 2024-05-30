<?php

namespace core\commands\debugCommands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\systems\scene\SceneSystem;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class GodCmd extends Command
{

  private SwimCore $core;
  private SceneSystem $sceneSystem;

  public function __construct(SwimCore $core)
  {
    parent::__construct("god", "enter god mode");
    $this->core = $core;
    $this->sceneSystem = $this->core->getSystemManager()->getSceneSystem();
    $this->setPermission("use.staff");
  }

  /**
   * @throws ScoreFactoryException|JsonException
   */
  public function execute(CommandSender $sender, string $commandLabel, array $args): bool
  {
    if ($sender instanceof SwimPlayer) {
      $rank = $sender->getRank()->getRankLevel();
      if ($rank == Rank::OWNER_RANK) {
        $this->sceneSystem->setScene($sender, $this->sceneSystem->getScene('GodMode'));
      } else {
        $sender->sendMessage(TextFormat::RED . "You can not use this");
      }
    }
    return true;
  }

}