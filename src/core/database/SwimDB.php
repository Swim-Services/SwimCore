<?php

namespace core\database;

use core\database\queries\TableManager;
use core\SwimCore;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

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