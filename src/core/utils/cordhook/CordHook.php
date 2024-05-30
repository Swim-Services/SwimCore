<?php

namespace core\utils\cordhook;

use core\SwimCore;
use RuntimeException;
use Symfony\Component\Filesystem\Path;

class CordHook
{

  private static string $url;
  private static string $name;
  private static string $avatarURL;
  private static Webhook $webhook;

  public static function load(): void
  {
    $hookConfig = Path::join(SwimCore::$customDataFolder, "webhook.json");

    if (!file_exists($hookConfig)) {
      throw new RuntimeException('Webhook configuration file not found');
    }

    $data = file_get_contents($hookConfig);

    if ($data === false) {
      throw new RuntimeException('Failed to read the webhook configuration file');
    }

    $config = json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new RuntimeException('Invalid JSON in the webhook configuration file: ' . json_last_error_msg());
    }

    if (!isset($config['url'], $config['name'], $config['avatar'])) {
      throw new RuntimeException('Missing required webhook configuration fields');
    }

    self::$url = $config['url'];
    self::$name = $config['name'];
    self::$avatarURL = $config['avatar'];

    self::$webhook = new Webhook(self::$url);
  }

  public static function getUrl(): string
  {
    return self::$url;
  }

  public static function getName(): string
  {
    return self::$name;
  }

  public static function getAvatarURL(): string
  {
    return self::$avatarURL;
  }

  public static function sendEmbed(string $description, string $title, string $footer = "Made by Swim Services", int $color = 0x0000ff): void
  {
    $msg = new Message();
    $msg->setAvatarURL(self::$avatarURL);
    $msg->setUsername(self::$name);
    $msg->setContent("");

    $embed = new Embed();
    $embed->setTitle($title);
    $embed->setDescription($description);
    $embed->setFooter($footer);
    $embed->setColor($color);
    $msg->addEmbed($embed);

    self::$webhook->send($msg);
  }

}