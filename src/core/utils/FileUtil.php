<?php

namespace core\utils;

use DirectoryIterator;

class FileUtil
{

  public static function GetDirectories(string $path): array
  {
    $dirs = [];
    foreach (new DirectoryIterator($path) as $file) {
      if ($file->isDir() && !$file->isDot()) {
        $dirs[] = $file->getFilename();
      }
    }
    return $dirs;
  }

  public static function RecurseDelete($src): void
  {
    if (file_exists($src) && is_dir($src)) {
      $dir = opendir($src);
      while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
          $full = $src . '/' . $file;
          if (is_dir($full)) {
            self::RecurseDelete($full);
          } else {
            unlink($full);
          }
        }
      }
      closedir($dir);
      rmdir($src);
    }
  }

  public static function RecurseCopy($src, $dst): void
  {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
      if (($file != '.') && ($file != '..')) {
        if (is_dir($src . '/' . $file)) {
          self::RecurseCopy($src . '/' . $file, $dst . '/' . $file);
        } else {
          copy($src . '/' . $file, $dst . '/' . $file);
        }
      }
    }
    closedir($dir);
  }

}