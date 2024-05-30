<?php

namespace core\custom\prefabs\pot;

use pocketmine\entity\Location;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\SplashPotion;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\ThrowSound;

class SwimPotItem extends SplashPotion
{

  public function __construct(string $name = "")
  {
    $pot = VanillaItems::SPLASH_POTION();
    $identifier = new ItemIdentifier($pot->getTypeId());
    parent::__construct($identifier, $pot->getName());

    // apply custom name
    if ($name !== "") {
      $this->setCustomName(TextFormat::RESET . $name);
    }
  }

  // pretty much the same as the vanilla version but makes a SwimPot entity instead
  public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult
  {
    $location = $player->getLocation();
    $pot = new SwimPot(Location::fromObject($player->getEyePos(), $player->getWorld(), $location->yaw, $location->pitch), $player, $this->getType());
    $pot->setMotion($player->getDirectionVector()->multiply(0.5));

    $projectileEv = new ProjectileLaunchEvent($pot);
    $projectileEv->call();
    if ($projectileEv->isCancelled()) {
      $pot->flagForDespawn();
      return ItemUseResult::FAIL;
    }

    $pot->spawnToAll();
    $player->getWorld()->addSound($location, new ThrowSound(), $player->getWorld()->getPlayers());
    $this->pop(); // -1 off the item count consuming it

    return ItemUseResult::SUCCESS;
  }

}