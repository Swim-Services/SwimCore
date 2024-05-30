<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\PlayerSystem;
use core\systems\player\SwimPlayer;
use core\utils\TargetArgument;
use CortexPE\Commando\args\BooleanArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class PingCmd extends BaseCommand
{

  private SwimCore $core;
  private PlayerSystem $playerSystem;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->playerSystem = $this->core->getSystemManager()->getPlayerSystem();
    $this->setPermission("use.all");
    parent::__construct($core, "ping", "get ping");
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player", true));
    $this->registerArgument(1, new BooleanArgument("raknet", true));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof Player) {
      $swimPlayer = $this->playerSystem->getSwimPlayer($sender);
      if ($swimPlayer) {
        $target = $sender;

        $raknet = $args["raknet"] ?? false;

        if (isset($args["player"])) {
          if ($this->core->getServer()->getPlayerExact($args["player"])) {
            $target = $this->core->getServer()->getPlayerExact($args["player"]);
          } else {
            $sender->sendMessage(TextFormat::RED . "Could not find player " . $args["player"]);
            return;
          }
        }
        if ($target instanceof SwimPlayer) {
          $ping = !$raknet ? $target->getNslHandler()->getPing() : $target->getNetworkSession()->getPing();
          $color = TextFormat::GREEN;
          if ($ping > 170) {
            $color = TextFormat::RED;
          } else if ($ping > 85) {
            $color = TextFormat::YELLOW;
          }
          $sender->sendMessage($target->getName() . "'s ping" . ($raknet ? " (Raknet)" : "") . ": " . $color . $ping . TextFormat::WHITE . "ms"
            . ($raknet ? "" : ", jitter: " . TextFormat::GREEN . $target->getNslHandler()->getJitter() . TextFormat::WHITE . "ms"));
        } else {
          $sender->sendMessage(TextFormat::RED . "Not a player");
        }
      }
    }
  }

}
