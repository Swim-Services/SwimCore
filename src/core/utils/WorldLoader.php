<?php

namespace core\utils;

use DirectoryIterator;
use pocketmine\Server;

class WorldLoader
{

  private static array $worlds;

  public static function loadWorlds(string $folder): void
  {
    // get our worlds path
    $worldsFolder = $folder . DIRECTORY_SEPARATOR . 'worlds';
    $savedWorldsFolder = $folder . DIRECTORY_SEPARATOR . 'savedWorlds';

    // iterate the saved worlds folder and save each directory name string to the worlds array
    self::$worlds = self::getDirectories($savedWorldsFolder);

    // load each world in
    $worldManager = Server::getInstance()->getWorldManager();
    foreach (self::$worlds as $worldName) {
      // delete the world
      self::recurseDelete($worldsFolder . DIRECTORY_SEPARATOR . $worldName);
      // copy in the saved world
      self::recurseCopy($savedWorldsFolder . DIRECTORY_SEPARATOR . $worldName, $worldsFolder . DIRECTORY_SEPARATOR . $worldName);
      // load in the freshly copied world
      $worldManager->loadWorld($worldName, true);
      $world = $worldManager->getWorldByName($worldName); // get the actual world object to call methods on it after loading
      if ($world === null) {
        var_dump("null " . $worldName);
      }
      $world->setTime(1600);
      $world->stopTime();
    }
    $worldManager->setAutoSave(false); // disable worlds saving chunk changes on shutdown (be careful)
  }

  private static function getDirectories(string $path): array {
    $dirs = [];
    foreach (new DirectoryIterator($path) as $file) {
      if ($file->isDir() && !$file->isDot()) {
        $dirs[] = $file->getFilename();
      }
    }
    return $dirs;
  }

  private static function recurseDelete($src): void
  {
    if (file_exists($src) && is_dir($src)) {
      $dir = opendir($src);
      while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
          $full = $src . '/' . $file;
          if (is_dir($full)) {
            self::recurseDelete($full);
          } else {
            unlink($full);
          }
        }
      }
      closedir($dir);
      rmdir($src);
    }
  }

  private static function recurseCopy($src, $dst): void
  {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
      if (($file != '.') && ($file != '..')) {
        if (is_dir($src . '/' . $file)) {
          self::recurseCopy($src . '/' . $file, $dst . '/' . $file);
        } else {
          copy($src . '/' . $file, $dst . '/' . $file);
        }
      }
    }
    closedir($dir);
  }

  public static function getWorldPlayerCount(string $worldName): int
  {
    $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
    if ($world) {
      return count($world->getPlayers());
    }
    return 0;
  }

}
