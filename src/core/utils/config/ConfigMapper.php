<?php

namespace core\utils\config;

use JsonException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\Utils;
use ReflectionClass;
use ReflectionProperty;

class ConfigMapper
{

  private Config $conf;

  public function __construct(PluginBase|Config $pluginOrConf, private readonly object $object, private readonly bool $deleteUnused = true)
  {
    if ($pluginOrConf instanceof Config) {
      $this->conf = $pluginOrConf;
    } else {
      $this->conf = $pluginOrConf->getConfig();
    }
  }

  public function load(): void
  {
    $confOpts = $this->conf->getAll();
    $this->loadClass($this->object, $confOpts);
  }

  /**
   * @throws JsonException
   */
  public function save(): void
  {
    $this->conf->setAll(self::saveClass($this->object, $this->deleteUnused ? [] : $this->conf->getAll()));
    $this->conf->save();
  }

  private static function loadClass(object $obj, array $in): void
  {
    $class = new ReflectionClass($obj);
    self::loopProperties($class, function (string $name, ReflectionProperty $prop) use ($in, $obj) {
      $typeName = $prop->getType()->getName();
      if (str_contains($typeName, "\\")) {
        if (!$prop->isInitialized($obj)) {
          $val = new $typeName;
        } else {
          $val = $prop->getValue($obj);
        }
        $prop->setValue($obj, $val);
        self::loadClass($val, $in[$name] ?? []);
        return;
      }
      if (!isset($in[$name])) return;
      $prop->setValue($obj, $in[$name]);
    });
  }

  private static function saveClass(object $obj, array $out = []): array
  {
    $class = new ReflectionClass($obj);
    self::loopProperties($class, function (string $name, ReflectionProperty $prop) use (&$out, $obj) {
      $typeName = $prop->getType()->getName();
      if (str_contains($typeName, "\\")) {
        if (!$prop->isInitialized($obj)) {
          $val = new $typeName;
        } else {
          $val = $prop->getValue($obj);
        }
        $out[$name] = self::saveClass($val, $out[$name] ?? []);
        return;
      }
      if (!$prop->isInitialized($obj)) {
        $out[$name] = null;
        return;
      }
      $out[$name] = $prop->getValue($obj);
    });
    return $out;
  }

  private static function loopProperties(ReflectionClass $reflectionClass, callable $cb): void
  {
    /** @var ReflectionProperty[] */
    $props = $reflectionClass->getProperties();
    foreach ($props as $prop) {
      $confName = $prop->getName();
      $comment = $prop->getDocComment();
      if ($comment !== false) {
        $parsed = Utils::parseDocComment($comment);
        if (isset($parsed["conf"])) {
          $confName = $parsed["conf"];
        }
      }
      $cb($confName, $prop);
    }
  }

}
