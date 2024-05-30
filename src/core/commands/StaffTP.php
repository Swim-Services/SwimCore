<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\systems\scene\SceneSystem;
use core\utils\TargetArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\command\CommandSender;
use pocketmine\player\GameMode;

class StaffTP extends BaseCommand
{

  private SwimCore $core;
  private SceneSystem $sceneSystem;

  public function __construct(SwimCore $core)
  {
    parent::__construct($core, "stafftp", "teleport to a player (puts you in the god mode scene)");
    $this->core = $core;
    $this->sceneSystem = $this->core->getSystemManager()->getSceneSystem();
    $this->setPermission("use.staff");
  }

  /**
   * @inheritDoc
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player"));
  }

  /**
   * @inheritDoc
   */
  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      if ($sender->getRank()->getRankLevel() >= Rank::MOD_RANK) {
        $player = $args["player"];
        $real = $this->core->getServer()->getPlayerExact($player);
        if (isset($real) && $real->isOnline()) {
          try {
            $this->sceneSystem->setScene($sender, $this->sceneSystem->getScene('GodMode'));
            $sender->setInvisible();
            $sender->setGamemode(GameMode::SPECTATOR);
            $sender->teleport($real->getPosition());
          } catch (JsonException|ScoreFactoryException $e) {
            echo "staff tp error: " . $e->getMessage() . "\n";
          }
        }
      }
    }
  }

}