<?php

namespace core;

use core\database\SwimDB;
use core\listeners\PlayerListener;
use core\listeners\WorldListener;
use core\systems\SystemManager;
use core\tasks\RandomMessageTask;
use core\tasks\SystemUpdateTask;
use core\utils\config\ConfigMapper;
use core\utils\config\SwimConfig;
use core\utils\cordhook\CordHook;
use core\utils\loaders\CommandLoader;
use core\utils\loaders\WorldLoader;
use core\utils\security\IPParse;
use core\utils\SteveSkin;
use CortexPE\Commando\exception\HookAlreadyRegistered;
use JsonException;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\SignalHandler;
use pocketmine\utils\TextFormat;
use ReflectionException;
use Symfony\Component\Filesystem\Path;

class SwimCore extends PluginBase
{

  public static string $assetFolder; // holds our assets for our custom loaded entities geometry and skin
  public static string $dataFolder; // the plug-in data folder that gets generated
  public static string $rootFolder;
  public static string $customDataFolder;
  public bool $shuttingDown = false;

  private SystemManager $systemManager;
  private CommandLoader $commandLoader;
  private SwimConfig $swimConfig;

  private WorldListener $worldListener;
  private PlayerListener $playerListener;

  /**
   * @throws JsonException
   * @throws HookAlreadyRegistered
   * @throws ReflectionException
   */
  public function onEnable(): void
  {
    // set instance
    SwimCoreInstance::setInstance($this);

    // set up the server appearance on the main menu based on whitelisted or not
    $this->MenuAppearance();

    // load the worlds
    WorldLoader::loadWorlds(self::$rootFolder);

    // set up the system manager
    $this->systemManager = new SystemManager($this);
    $this->systemManager->init();

    // set up the command loader and load the commands we want and don't want
    $this->commandLoader = new CommandLoader($this);
    $this->commandLoader->setUpCommands();

    // set up the config
    $this->swimConfig = new SwimConfig;
    $confMapper = new ConfigMapper($this, $this->swimConfig);
    $confMapper->load();
    $confMapper->save(); // add missing fields to config

    // set the database connection
    SwimDB::initialize($this);

    // set the server's listeners
    $this->setListeners();

    // schedule server's tasks
    $this->registerTasks();

    // register inv menu (thanks muqsit)
    if (!InvMenuHandler::isRegistered()) {
      InvMenuHandler::register($this);
    }

    // load our discord webhook from config
    CordHook::load();

    // set up signal handler
    $this->setUpSignalHandler();
  }

  private function registerTasks(): void
  {
    $this->getScheduler()->scheduleRepeatingTask(new SystemUpdateTask($this), 1); // update system every tick
    $this->getScheduler()->scheduleRepeatingTask(new RandomMessageTask, 2400); // random message in server every 2 minutes
  }

  private function setUpSignalHandler(): void
  {
    new SignalHandler(function () {

      $this->getLogger()->info("got signal, shutting down...");
      $this->getLogger()->info("disconnecting players...");
      $this->shuttingDown = true;

      foreach ($this->getServer()->getOnlinePlayers() as $player) {
        $player->kick(TextFormat::RED . "Server was shutdown by an admin.");
      }

      $this->getScheduler()->scheduleDelayedTask(new class($this) extends Task {

        private SwimCore $swimCore;

        public function __construct(SwimCore $xenonCore)
        {
          $this->swimCore = $xenonCore;
        }

        public function onRun(): void
        {
          $this->swimCore->getLogger()->info("stopping server...");
          $this->swimCore->getServer()->shutdown();
        }

      }, 5); // give clients time to disconnect
    });
  }

  /**
   * @throws JsonException
   */
  public function onLoad(): void
  {
    // set up asset and data folder
    $this->setDataAssetFolderPaths();

    // deserialize needed data
    SteveSkin::loadInSkin();
  }

  // close the connection to the database
  protected function onDisable(): void
  {
    SwimDB::close();

    if (!$this->shuttingDown) {
      $this->shuttingDown = true;
      foreach ($this->getServer()->getOnlinePlayers() as $p) {
        $serverAddr = $p->getPlayerInfo()->getExtraData()["ServerAddress"] ?? "0.0.0.0:1";
        $parsedIp = IPParse::sepIpFromPort($serverAddr);
        $p->getNetworkSession()->transfer($parsedIp[0], $parsedIp[1]);
      }
    }

    $this->getLogger()->info("-disabled");
  }

  private function setListeners(): void
  {
    $this->playerListener = new PlayerListener($this);
    $this->worldListener = new WorldListener($this);
    Server::getInstance()->getPluginManager()->registerEvents($this->playerListener, $this);
    Server::getInstance()->getPluginManager()->registerEvents($this->worldListener, $this);
  }

  private function setDataAssetFolderPaths(): void
  {
    self::$assetFolder = str_replace("\\", DIRECTORY_SEPARATOR,
      str_replace("/", DIRECTORY_SEPARATOR,
        Path::join($this->getFile(), "assets")));

    self::$dataFolder = str_replace("\\", DIRECTORY_SEPARATOR,
      str_replace("/", DIRECTORY_SEPARATOR,
        Path::join($this->getDataFolder())));

    self::$customDataFolder = str_replace("\\", DIRECTORY_SEPARATOR,
      str_replace("/", DIRECTORY_SEPARATOR,
        Path::join($this->getFile(), "data")));

    self::$rootFolder = dirname(self::$assetFolder, 3);
    echo("SwimCore asset folder: " . self::$assetFolder . "\n");
    echo("SwimCore data folder: " . self::$dataFolder . "\n");
    echo("SwimCore root folder: " . self::$rootFolder . "\n");
  }

  // toggles menu appearance based on white list
  private function MenuAppearance(): void
  {
    if (Server::getInstance()->hasWhitelist()) {
      Server::getInstance()->getNetwork()->setName("§r§cMaintenance");
    } else {
      Server::getInstance()->getNetwork()->setName(TextFormat::DARK_AQUA . TextFormat::BOLD . "SCRIMS");
    }
  }

  public function getSystemManager(): SystemManager
  {
    return $this->systemManager;
  }

  public function getCommandLoader(): CommandLoader
  {
    return $this->commandLoader;
  }

  public function getSwimConfig(): SwimConfig
  {
    return $this->swimConfig;
  }

  public function getPlayerListener(): PlayerListener
  {
    return $this->playerListener;
  }

  public function getWorldListener(): WorldListener
  {
    return $this->worldListener;
  }

}
