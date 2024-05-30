<?php

namespace core\commands\debugCommands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\utils\ServerSounds;
use core\utils\TargetArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class PlaySoundCmd extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct($core, "sound", "play a sound to a player");
    $this->setPermission("use.staff");
    $this->core = $core;
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new IntegerArgument("volume", false));
    $this->registerArgument(1, new IntegerArgument("pitch", false));
    $this->registerArgument(2, new TargetArgument("player", false));
    $this->registerArgument(3, new TextArgument("sound path", false));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      $rank = $sender->getRank()->getRankLevel();
      if (isset($args['sound path'])) {
        if ($rank == Rank::OWNER_RANK) {
          $sound = $args['sound path'];
          $volume = 2;
          $pitch = 1;
          $player = $sender;

          if (isset($args['volume'])) {
            $volume = $args['volume'];
          }

          if (isset($args['pitch'])) {
            $pitch = $args['pitch'];
          }

          if (isset($args['player'])) {
            $player = $this->core->getServer()->getPlayerExact($args['player']);
            if (!isset($player)) {
              $sender->sendMessage(TextFormat::RED . "Player not found:" . $args['player']);
              return;
            }
          }

          ServerSounds::playSoundToPlayer($player, $sound, $volume, $pitch);
        } else {
          $sender->sendMessage(TextFormat::RED . "You can not use this");
        }
      } else {
        $sender->sendMessage("Missing sound path argument");
      }
    }
  }

}