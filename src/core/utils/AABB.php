<?php

namespace core\Utils;

use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

// Taken directly from Esoteric, credit to Ethan (we did add some stuff though and refactor it a lot)
class AABB
{

  private float $minY;
  private float $minZ;
  private float $minX;
  private float $maxZ;
  private float $maxY;
  private float $maxX;
  private Vector3 $maxVector;
  private Vector3 $minVector;

  public function __construct(float $minX, $minY, float $minZ, float $maxX, float $maxY, float $maxZ)
  {
    $this->minX = $minX;
    $this->minY = $minY;
    $this->minZ = $minZ;
    $this->maxX = $maxX;
    $this->maxY = $maxY;
    $this->maxZ = $maxZ;
    $this->minVector = new Vector3($this->minX, $this->minY, $this->minZ);
    $this->maxVector = new Vector3($this->maxX, $this->maxY, $this->maxZ);
  }

  /*
  public static function from(PlayerData $data) : self{
    $pos = $data->currentLocation;
    return new AABB($pos->x - $data->hitboxWidth, $pos->y, $pos->z - $data->hitboxWidth, $pos->x + $data->hitboxWidth, $pos->y + $data->hitboxHeight, $pos->z + $data->hitboxWidth);
  }
  */

  public static function fromAxisAlignedBB(AxisAlignedBB $alignedBB): AABB
  {
    return new AABB($alignedBB->minX - 0.1, $alignedBB->minY, $alignedBB->minZ - 0.1, $alignedBB->maxX + 0.1, $alignedBB->maxY, $alignedBB->maxZ + 0.1);
  }

  public static function fromPosition(Vector3 $pos, float $width = 0.3, float $height = 1.8): AABB
  {
    return new AABB($pos->x - $width, $pos->y, $pos->z - $width, $pos->x + $width, $pos->y + $height, $pos->z + $width);
  }

  public static function fromBlock(Block $block): AABB
  {
    $b = $block->getCollisionBoxes()[0] ?? null;
    if ($b !== null || count($block->getCollisionBoxes()) > 0) {
      return new AABB($b->minX, $b->minY, $b->minZ, $b->maxX, $b->maxY, $b->maxZ);
    }

    $pos = $block->getPosition();
    return new AABB($pos->getX(), $pos->getY(), $pos->getZ(), $pos->getX() + 1, $pos->getY() + 1, $pos->getZ() + 1);
  }

  public function clone(): AABB
  {
    return clone $this;
  }

  public function translate(float $x, float $y, float $z): AABB
  {
    return new AABB($this->minX + $x, $this->minY + $y, $this->minZ + $z, $this->maxX + $x, $this->maxY, $this->maxZ);
  }

  public function grow(float $x, float $y, float $z): AABB
  {
    return new AABB($this->minX - $x, $this->minY - $y, $this->minZ - $z, $this->maxX + $x, $this->maxY, $this->maxZ);
  }

  public function contains(Vector3 $pos): bool
  {
    return $pos->getX() <= $this->maxX && $pos->getY() <= $this->maxY && $pos->getZ() <= $this->maxZ && $pos->getX() >= $this->minX && $pos->getY() >= $this->minY && $pos->getZ() >= $this->minZ;
  }

  public function min(int $i): float
  {
    return [$this->minX, $this->minY, $this->minZ][$i] ?? 0;
  }

  public function max(int $i): float
  {
    return [$this->maxX, $this->maxY, $this->maxZ][$i] ?? 0;
  }

  public function getCornerVectors(): array
  {
    return [
      new Vector3($this->maxX, $this->maxY, $this->maxZ), // top vectors
      new Vector3($this->minX, $this->maxY, $this->maxZ),
      new Vector3($this->minX, $this->maxY, $this->minZ),
      new Vector3($this->maxX, $this->maxY, $this->minZ),
      new Vector3($this->maxX, $this->minY, $this->maxZ),  // bottom vectors
      new Vector3($this->minX, $this->minY, $this->maxZ),
      new Vector3($this->minX, $this->minY, $this->minZ),
      new Vector3($this->maxX, $this->minY, $this->minZ)
    ];
  }

  public function distanceFromVector(Vector3 $vector) : float{
    $distX = max($this->minX - $vector->x, max(0, $vector->x - $this->maxX));
    $distY = max($this->minY - $vector->y, max(0, $vector->y - $this->maxY));
    $distZ = max($this->minZ - $vector->z, max(0, $vector->z - $this->maxZ));
    return sqrt(($distX ** 2) + ($distY ** 2) + ($distZ ** 2));
  }

  public function closestPoint(Vector3 $origin) : Vector3{
    $shortest = Vector3::zero();

    if ($origin->x < $this->minX) {
      $shortest->x = $this->minX;
    } else if ($origin->x > $this->maxX) {
      $shortest->x = $this->maxX;
    } else {
      $shortest->x = $origin->x;
    }

    if ($origin->y < $this->minY) {
      $shortest->y = $this->minY;
    } else if ($origin->y > $this->maxY) {
      $shortest->y = $this->maxY;
    } else {
      $shortest->y = $origin->y;
    }

    if ($origin->z < $this->minZ) {
      $shortest->z = $this->minZ;
    } else if ($origin->z > $this->maxZ) {
      $shortest->z = $this->maxZ;
    } else {
      $shortest->z = $origin->z;
    }

    return $shortest;
  }

  /*
  public function calculateIntercept(Vector3 $pos1, Vector3 $pos2) : ?RayTraceResult{
    return $this->toAABB()->isVectorInside($pos1) ? new RayTraceResult($this->toAABB(), 0, clone PlayerData::$ZERO_VECTOR) : $this->toAABB()->calculateIntercept($pos1, $pos2);
  }
  */

  public function toAABB(): AxisAlignedBB
  {
    return new AxisAlignedBB($this->minX, $this->minY, $this->minZ, $this->maxX, $this->maxY, $this->maxZ);
  }

}