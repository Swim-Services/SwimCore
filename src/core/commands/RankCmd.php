<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\utils\TargetArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class RankCmd extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.op");
    parent::__construct($core, "rank", "Set Rank");
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player", false));
    $this->registerArgument(1, new IntegerArgument("rankLevel", false));
  }

  // takes in player name and integer as args
  private function rankCommandLogic(CommandSender $sender, array $args)
  {
    if (count($args) >= 2) {
      $playerName = $args["player"];
      $rankLevel = $args["rankLevel"];
      if ($rankLevel >= Rank::DEFAULT_RANK && $rankLevel <= Rank::OWNER_RANK) {
        // first attempt to get player online
        $player = $this->core->getServer()->getPlayerExact($playerName);
        if ($player instanceof SwimPlayer) {
          $player->getRank()->setOnlinePlayerRank($rankLevel);
        } else {
          // else wise just set rank in database
          Rank::setRankInDatabase($playerName, $rankLevel);
        }
        $sender->sendMessage(TextFormat::GREEN . "Updated " . $playerName . "'s Rank to " . Rank::getRankNameString($rankLevel));
      } else {
        $sender->sendMessage(TextFormat::RED . "Incorrect rank level: " . $rankLevel);
      }
    } else {
      $sender->sendMessage(TextFormat::RED . "Incorrect usage. Please provide the player name and rank level correctly");
    }
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      // from staff in game
      $rank = $sender->getRank()->getRankLevel();
      if ($rank >= Rank::MOD_RANK) {
        self::rankCommandLogic($sender, $args);
      } else {
        $sender->sendMessage(TextFormat::RED . "You do not have permissions to do this command.");
      }
    } else {
      // from console
      self::rankCommandLogic($sender, $args);
    }
  }

}