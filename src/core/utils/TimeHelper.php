<?php

namespace core\utils;

use DateTime;

class TimeHelper
{

  // 20 ticks a second
  public static function secondsToTicks(int|float $seconds): int|float
  {
    return $seconds * 20;
  }

  // 20 ticks a second, 60 seconds in a minute
  public static function minutesToTicks(int|float $minutes): int|float
  {
    return $minutes * 60 * 20;
  }

  public static function ticksToSeconds(int|float $ticks): int|float
  {
    if ($ticks <= 0) {
      return 0;
    }
    return $ticks / 20;
  }

  // Utility function to format time
  public static function formatTime(int $seconds): string
  {
    $dtF = new DateTime('@0');
    $dtT = new DateTime("@$seconds");
    $interval = $dtF->diff($dtT);
    $formatArray = [
      'y' => 'year',
      'm' => 'month',
      'd' => 'day',
      'h' => 'hour',
      'i' => 'minute',
    ];
    $formatted = [];
    foreach ($formatArray as $timeElement => $timeString) {
      if ($interval->$timeElement > 0) {
        $formatted[] = $interval->$timeElement . ' ' . $timeString . ($interval->$timeElement > 1 ? 's' : '');
      }
    }
    return implode(', ', $formatted);
  }

  public static function digitalClockFormatter(int $seconds, bool $includeHours = false, bool $inFrontZeroForMinutes = false): string
  {
    // safety check
    if ($seconds <= 0) {
      return $includeHours ? "00:00:00" : "00:00";
    }

    // Divide the total seconds into hours, minutes, and remaining seconds
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    // Format the values to ensure they are two digits, adding leading zeros if necessary
    $formattedHours = str_pad($hours, 2, "0", STR_PAD_LEFT);

    // leading zeroes for minutes by default is false
    if ($inFrontZeroForMinutes) {
      $formattedMinutes = str_pad($minutes, 2, "0", STR_PAD_LEFT);
    } else {
      $formattedMinutes = $minutes;
    }

    $formattedSeconds = str_pad($remainingSeconds, 2, "0", STR_PAD_LEFT);

    // Concatenate the formatted values into the digital clock format
    if ($includeHours) {
      return $formattedHours . ":" . $formattedMinutes . ":" . $formattedSeconds;
    } else {
      return $formattedMinutes . ":" . $formattedSeconds;
    }
  }

  public static function parseTime(string|null $timeOption): int|null
  {
    switch (strtolower($timeOption ?? "day")) {
      case "sunrise":
        $time = 23500;
        break;
      case "day":
        $time = 1000;
        break;
      case "noon":
        $time = 6000;
        break;
      case "sunset":
        $time = 12500;
        break;
      case "midnight":
        $time = 18000;
        break;
      default:
        if (is_numeric($timeOption)) {
          $time = intval($timeOption);
          if ($time < 0 || $time > 24000) {
            return null;
          }
        } else {
          return null;
        }
    }
    return $time;
  }

  public static function getTimeIndex(int $raw): int
  {
    return match ($raw) {
      23500 => 0, // sunset
      1000 => 1, // day
      6000 => 2, // noon
      12500 => 3, // sunset
      18000 => 4 // midnight
    };
  }

  public static function timeIndexToRaw(int $index): int
  {
    return match ($index) {
      0 => 23500, // sunset
      1 => 1000, // day
      2 => 6000, // noon
      3 => 12500, // sunset
      4 => 18000 // midnight
    };
  }

  public static function timeIntToString(int $rawTime): string
  {
    return match ($rawTime) {
      23500 => "sunrise",
      1000 => "day",
      6000 => "noon",
      12500 => "sunset",
      18000 => "midnight",
      default => strval($rawTime),
    };
  }

  public static function minutesToSeconds(int $minutes): int
  {
    return $minutes * 60;
  }

  public static function secondsToMinutes(int $seconds): float
  {
    return $seconds / 60.0;
  }

}