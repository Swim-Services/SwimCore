<?php

namespace core\commands\debugCommands;

use core\SwimCore;
use core\utils\security\IPParse;
use core\utils\TaskUtils;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class RestartCommand extends BaseCommand
{

  private swimCore $swimCore;

  public function __construct(swimCore $swimCore)
  {
    $this->swimCore = $swimCore;
    $this->setPermission("use.op");
    parent::__construct($swimCore, "restart", "restart server");
  }

  /**
   * @throws ArgumentOrderException
   */
  public function prepare(): void
  {
    $this->registerArgument(0, new IntegerArgument("time", true));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if (!isset($args["time"])) {
      $this->restartServer();
      return;
    }

    $time = $args["time"] + time();
    TaskUtils::repeating($this->swimCore, 20, function () use ($time) {
      $timeLeft = $time - time();
      if ($timeLeft <= 0) {
        $this->restartServer();
        return;
      }
      if ($timeLeft % 60 == 0) {
        $this->swimCore->getServer()->broadcastMessage(TextFormat::RED . "The server will be restarting in " . ($timeLeft / 60) . " minute" . (($timeLeft / 60) == 1 ? "" : "s"));
      }
      if ($timeLeft <= 10) {
        $this->swimCore->getServer()->broadcastMessage(TextFormat::RED . "The server will be restarting in $timeLeft second" . ($timeLeft == 1 ? "" : "s"));
      }
    });
  }

  public function restartServer()
  {
    $this->swimCore->shuttingDown = true;

    foreach ($this->swimCore->getServer()->getOnlinePlayers() as $p) {
      $serverAddr = $p->getPlayerInfo()->getExtraData()["ServerAddress"] ?? "0.0.0.0:1";
      $parsedIp = IPParse::sepIpFromPort($serverAddr);
      $p->getNetworkSession()->transfer($parsedIp[0], $parsedIp[1]);
    }

    $this->swimCore->getScheduler()->scheduleDelayedTask(new class($this->swimCore) extends Task {
      private SwimCore $swimCore;

      public function __construct(SwimCore $swimCore)
      {
        $this->swimCore = $swimCore;
      }

      public function onRun(): void
      {
        if (stripos(PHP_OS, 'WIN') === 0) {
          // windows specific restart (makes a new window, not desired but works)
          register_shutdown_function(function () {
            shell_exec('start "" "start.cmd"');
          });
          /* does it in the same window but not with proper output
          register_shutdown_function(function () {
            $batchScript = <<<BATCH
              @echo off
              timeout /t 2 /nobreak >nul
              call start.cmd
              exit
              BATCH;
            file_put_contents('restart.bat', $batchScript);
            exec('restart.bat');
          });
          */
        } else {
          // linux specific restart
          register_shutdown_function(function () {
            pcntl_exec(PHP_BINARY, $_SERVER['argv']);
          });
        }
        $this->swimCore->getServer()->shutdown();
      }
    }, 1); // not sure what time unit delay is in

  }

}