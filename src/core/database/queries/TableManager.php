<?php

namespace core\database\queries;

use core\database\SwimDB;
use poggit\libasynql\SqlThread;

class TableManager
{

  // creates the needed tables (this only does the essential ones for the lightweight engine)
  public static function createTables(): void
  {

    // make the Connections table, which holds the player's xuid as the key, and the player's name as a value
    SwimDB::getDatabase()->executeImplRaw(
      [
        0 => "CREATE TABLE IF NOT EXISTS Connections
              (
                  xuid VARCHAR(16) NOT NULL UNIQUE, 
                  name TEXT
              )"
      ],
      [0 => []],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );

    // make the Settings table, which holds the player's xuid as the key, and booleans for cps, scoreboard, duel invites, and cords
    SwimDB::getDatabase()->executeImplRaw(
      [
        0 => "CREATE TABLE IF NOT EXISTS Settings 
             (
                xuid VARCHAR(16) NOT NULL UNIQUE, 
                showCPS int, 
                showScoreboard int, 
                duelInvites int, 
                partyInvites int,
                showCords int,
                showScoreTags int,
                msg int,
                pearl int,
                nhc int,
                personalTime int
             )"
      ],
      [0 => []],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );

    // make the Ranks table, which holds the player's xuid as the key, and the player's name and rank
    SwimDB::getDatabase()->executeImplRaw(
      [
        0 => "CREATE TABLE IF NOT EXISTS Ranks 
             (
                xuid VARCHAR(16) NOT NULL UNIQUE, 
                name TEXT, 
                playerRank int
             )"
      ],
      [0 => []],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );

    // create the Punishments table, which holds the player's xuid as the key, and if they are banned or muted and the times to be unpunished at
    SwimDB::getDatabase()->executeImplRaw(
      [0 => "CREATE TABLE IF NOT EXISTS Punishments 
            (
                xuid VARCHAR(16) NOT NULL UNIQUE, 
                name TEXT, 
                banned int, 
                muted int,
                unbanTime BIGINT, 
                unmuteTime BIGINT, 
                banReason TEXT, 
                muteReason TEXT
            )"],
      [0 => []],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );

  }

} // TableManager