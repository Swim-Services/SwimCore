<?php

namespace core\utils;

use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

class PositionHelper
{

  // returns the pitch towards a vector from a given source position
  public static function getPitchTowardsPosition(Vector3 $source, Vector3 $target): float
  {
    // Differences in coordinates
    $dx = $target->x - $source->x;
    $dy = $target->y - $source->y;
    $dz = $target->z - $source->z;

    // Calculate the horizontal distance
    $horizontalDistance = sqrt($dx * $dx + $dz * $dz);

    // Calculate the pitch in radians
    $pitchRadians = atan2($dy, $horizontalDistance);

    // Convert pitch to degrees
    return rad2deg($pitchRadians);
  }

  // Returns the yaw towards a vector from a given source position
  public static function getYawTowardsPosition(Vector3 $source, Vector3 $target): float
  {
    // Differences in coordinates
    $dx = $target->x - $source->x;
    $dz = $target->z - $source->z;

    // Calculate the yaw in radians
    $yawRadians = atan2($dz, $dx);

    // Convert yaw to degrees and adjust the angle
    $yawDegrees = rad2deg($yawRadians) - 90;

    // Normalize the yaw to the range [-180, 180]
    if ($yawDegrees < -180) {
      $yawDegrees += 360;
    } elseif ($yawDegrees > 180) {
      $yawDegrees -= 360;
    }

    return $yawDegrees;
  }

  // center of a 3D block position in terms of barycentric coordinates
  public static function centerPosition(Position $position): Position
  {
    return new Position($position->getX() + 0.5, $position->getY(), $position->getZ() + 0.5, $position->getWorld());
  }

  // center of a 3D vector position in terms of barycentric coordinates
  public static function centerVector(Vector3 $vector3): Vector3
  {
    return new Vector3($vector3->x + 0.5, $vector3->y, $vector3->z + 0.5);
  }

  public static function toString(Vector3|Position $vector3): string
  {
    return (int)$vector3->x . ", " . (int)$vector3->y . ", " . (int)$vector3->z;
  }

  public static function isZeroVector(Vector3 $vector3): bool
  {
    return $vector3->x == 0 && $vector3->y == 0 && $vector3->z == 0;
  }

  public static function positionToLocation(Position $position, float $yaw = 0, float $pitch = 0): Location
  {
    return new Location($position->x, $position->y, $position->z, $position->world, $yaw, $pitch);
  }

  /**
   * Generates a hashed integer key from 3D coordinates for fast lookup.
   * This treats the coordinates as whole number integers for block position.
   * Uses XOR'd prime numbers on the coordinates.
   * @param Vector3|Position $vector3
   * @return int The hashed key.
   */
  public static function getVectorHashKey(Vector3|Position $vector3): int
  {
    return ((int)$vector3->x * 73856093) ^ ((int)$vector3->y * 19349663) ^ ((int)$vector3->z * 83492791);
  }

  // returns the position closer to the other position by given amount
  // calculates the angle to move by as well automatically
  // for example if positions share the same X or Z coordinate than it should move perfectly straight by amount in the appropriate direction
  public static function moveCloserTo(Position $positionToMove, Position $positionToMoveTowards, float $amount): Position
  {
    // Calculate the distance between the two positions
    $distance = $positionToMove->asVector3()->distance($positionToMoveTowards->asVector3());

    // If the distance is 0 or the amount to move is 0, return the original position
    if ($distance == 0 || $amount == 0) {
      return $positionToMove;
    }

    // Calculate the ratio to scale the differences by
    $ratio = $amount / $distance;

    // If the ratio is greater than 1, it means the amount is larger than the distance,
    // so just return the position to move towards
    if ($ratio >= 1) {
      return $positionToMoveTowards;
    }

    // Cache coords
    $posX = $positionToMove->getX();
    $posY = $positionToMove->getY();
    $posZ = $positionToMove->getZ();

    // Calculate the differences in x, y, and z coordinates
    $diffX = $positionToMoveTowards->getX() - $posX;
    $diffY = $positionToMoveTowards->getY() - $posY;
    $diffZ = $positionToMoveTowards->getZ() - $posZ;

    // Calculate the new coordinates by moving the position closer by the specified amount
    $newX = $posX + $ratio * $diffX;
    $newY = $posY + $ratio * $diffY;
    $newZ = $posZ + $ratio * $diffZ;

    // Return the new position
    return new Position($newX, $newY, $newZ, $positionToMove->getWorld());
  }

  public static function vecToPos(Vector3 $vector3, World $world): Position
  {
    return new Position($vector3->getX(), $vector3->getY(), $vector3->getZ(), $world);
  }

  public static function sameXZ(Vector3 $vector3, Vector3 $otherVector3): bool
  {
    return ((int)$vector3->x == (int)$otherVector3->x) && ((int)$vector3->z == (int)$otherVector3->z);
  }

  public static function getChunkX(Position $position): int|float
  {
    return $position->x >> 4;
  }

  public static function getChunkZ(Position $position): int|float
  {
    return $position->z >> 4;
  }

  public static function distanceSquared2D(float $x1, float $y1, float $x2, float $y2): float
  {
    $dx = $x1 - $x2;
    $dy = $y1 - $y2;
    return ($dx * $dx) + ($dy * $dy);
  }

  public static function midPoint(Position $position1, Position $position2): Position
  {
    // Calculate the midpoint of the x coordinates
    $midX = ($position1->getX() + $position2->getX()) / 2;

    // Calculate the midpoint of the y coordinates
    $midY = ($position1->getY() + $position2->getY()) / 2;

    // Calculate the midpoint of the z coordinates
    $midZ = ($position1->getZ() + $position2->getZ()) / 2;

    // Return a new Position object representing the midpoint
    return new Position($midX, $midY, $midZ, $position1->getWorld());
  }

}