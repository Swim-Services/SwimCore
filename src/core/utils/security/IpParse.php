<?php

namespace core\utils\security;

// this class is only used for changing players from one server to another, such as on server restart for hot reloading
class IPParse
{

  public static function parseIp(string $ip): string
  {
    if (!str_contains($ip, ":")) {
      return $ip; //ipv4
    }
    $parts = explode(':', $ip);
    if (count($parts) < 4) return $ip;
    return implode(':', array_slice($parts, 0, 4));
  }

  public static function sepIpFromPort(string $ip): array
  {
    $parts = explode(":", $ip);
    if (count($parts) < 2) return ["0.0.0.0", 19132];
    $port = intval($parts[count($parts) - 1]) ?? 19132;
    if (count($parts) == 2) {
      return [$parts[0] ?? "0.0.0.0", $port]; // ipv4
    }
    array_splice($parts, count($parts) - 1);
    return [implode(":", $parts), $port];
  }

}