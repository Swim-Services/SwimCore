<?php

namespace core\utils\loaders;

use core\SwimCore;
use CortexPE\Commando\exception\HookAlreadyRegistered;
use CortexPE\Commando\PacketHooker;
use pocketmine\command\Command;
use pocketmine\Server;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Path;

class CommandLoader
{

  private SwimCore $core;
  private Server $server;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->server = $core->getServer();
  }

  /**
   * @throws HookAlreadyRegistered
   */
  public function setUpCommands(bool $disableVanilla = true): void
  {
    if ($disableVanilla) {
      $this->unloadVanillaCommands();
    }

    // for Commando
    if (!PacketHooker::isRegistered()) {
      PacketHooker::register($this->core);
    }

    $this->loadCommands();
  }

  public function loadCommands(): void
  {
    $commandsDir = Path::canonicalize(Path::join(__DIR__, '..', '..', 'commands'));
    $this->registerCommandScriptsRecursively($commandsDir);
  }

  private function registerCommandScriptsRecursively(string $directory): void
  {
    echo "Loading Command Scripts from: " . $directory . "\n";
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'php') {
        $relativePath = Path::makeRelative($file->getPathname(), $directory);
        $relativePath = str_replace('/', '\\', $relativePath); // Ensure correct namespace separators
        $relativePath = str_replace('.php', '', $relativePath); // Remove the .php extension

        // Construct the full class name with the appropriate namespace
        $fullClassName = '\\core\\commands\\' . $relativePath;
        echo "Registering Command: " . $fullClassName . "\n";

        if (class_exists($fullClassName)) {
          $script = new $fullClassName($this->core);
          if ($script instanceof Command) {
            $this->registerCommand($script);
          }
        } else {
          echo "Error: Command class failed to register: " . $fullClassName . "\n";
        }
      }
    }
  }

  // get rid of whisper, clear, me, vanilla banning, kill, etc
  private function unloadVanillaCommands(): void
  {
    $commandNames = array("kill", "me", "w", "whisper", "clear", "ban", "stop", "kick");
    foreach ($commandNames as $cmd) {
      $this->unregisterCommand($cmd);
    }
  }

  // register a single command
  private function registerCommand(Command $command): void
  {
    $commandMap = $this->server->getCommandMap();
    $commandMap->register($command->getName(), $command);
  }

  // unregister a single command
  private function unregisterCommand(string $commandName): void
  {
    $commandMap = $this->server->getCommandMap();
    $command = $commandMap->getCommand($commandName);
    if ($command !== null) {
      $command->setLabel($command->getLabel() . "__disabled");
      $commandMap->unregister($command);
    }
  }

}