<?php

namespace core\commands;

use core\SwimCore;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class StopCommand extends BaseCommand
{

  private SwimCore $SwimCore;

  public function __construct(SwimCore $swimCore)
  {
    $this->SwimCore = $swimCore;
    $this->setPermission("use.op");
    parent::__construct($swimCore, "stop", "stop server");
  }

  /**
   * @throws ArgumentOrderException
   */
  public function prepare(): void
  {
    $this->registerArgument(0, new TextArgument("reason", true));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    $this->SwimCore->getLogger()->info("got stop request, shutting down...");
    $this->SwimCore->getLogger()->info("disconnecting players...");
    $this->SwimCore->shuttingDown = true;

    $reason = TextFormat::RED . "Server was shutdown by an admin." . (isset($args["reason"]) ? TextFormat::WHITE . "\nReason: " . TextFormat::GREEN . $args["reason"] : "");
    foreach ($this->SwimCore->getServer()->getOnlinePlayers() as $player) {
      $player->kick($reason);
    }

    $this->SwimCore->getScheduler()->scheduleDelayedTask(new class($this->SwimCore) extends Task {
      private SwimCore $SwimCore;

      public function __construct(SwimCore $SwimCore)
      {
        $this->SwimCore = $SwimCore;
      }

      public function onRun(): void
      {
        $this->SwimCore->getLogger()->info("stopping server...");
        $this->SwimCore->getServer()->shutdown();
      }
    }, 5); // give clients time to disconnect
  }

}