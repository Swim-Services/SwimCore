<?php

namespace core\database;

use core\database\queries\TableManager;
use core\SwimCore;
use core\utils\TimeHelper;
use pocketmine\scheduler\Task;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlThread;

class SwimDB
{

  private static DataConnector $DBC;

  public static function initialize(SwimCore $core): void
  {
    $databaseConf = $core->getSwimConfig()->database;

    // establish the database connection with the database info
    self::$DBC = libasynql::create($core, ["type" => "mysql", "mysql" => ["host" => $databaseConf->host,
      "username" => $databaseConf->username, "password" => $databaseConf->password, "schema" => $databaseConf->schema,
      "port" => $databaseConf->port, "worker-limit" => $databaseConf->workerLimit]], ["mysql" => "mysql.sql"]);
    // once we have made it, create the tables
    TableManager::createTables();

    // start up the keep alive task to ping the database every minute
    $core->getScheduler()->scheduleRepeatingTask(new KeepAlive(self::$DBC), TimeHelper::minutesToTicks(1));
  }

  public static function getDatabase(): DataConnector
  {
    return self::$DBC;
  }

  public static function close(): void
  {
    self::$DBC->close();
  }

}

class KeepAlive extends Task
{

  private DataConnector $DBC;

  public function __construct(DataConnector $DBC)
  {
    $this->DBC = $DBC;
  }

  /*
  * @brief Called in a task every minute to ping the database to keep the connection alive
  */
  public function onRun(): void
  {
    // Perform a simple query like SELECT 1 to ping the database
    $this->DBC->executeImplRaw(
      [
        0 => "SELECT 1"
      ],
      [0 => []],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );
  }

}