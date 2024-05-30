<?php

namespace core\commands\debugCommands;

use core\SwimCore;
use core\systems\entity\entities\Actor;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\utils\SkinHelper;
use CortexPE\Commando\args\FloatArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use JsonException;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use ReflectionException;
use Symfony\Component\Filesystem\Path;

class SpawnEntityCmd extends BaseCommand
{

  public function __construct(SwimCore $core)
  {
    parent::__construct($core, "entity", "DO NOT FUCKING RUN THIS IN PROD, spawn an entity at your position with a rotation, scale, skin, and geo");
    $this->setPermission("use.staff");
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new FloatArgument("rotation", false));
    $this->registerArgument(1, new FloatArgument("head rotation", false));
    $this->registerArgument(2, new FloatArgument("scale", false));
    $this->registerArgument(3, new TextArgument("skin and geo", false));
  }

  /**
   * @throws ReflectionException
   * @throws JsonException
   */
  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      $rank = $sender->getRank()->getRankLevel();
      if ($rank == Rank::OWNER_RANK) {
        $rot = $args['rotation'];
        $head = $args['head rotation'];
        $scale = $args['scale'];
        $skinAndGeo = $args['skin and geo'];
        $parts = explode(",", $skinAndGeo);
        $skinPath =  str_replace(" ", "", $parts[0]);
        $geoPath =  str_replace(" ", "", $parts[1]);

        $png = Path::join(SwimCore::$assetFolder, 'img', $skinPath);
        $geometryData = file_get_contents(Path::join(SwimCore::$assetFolder, 'geo', $geoPath));
        $skin = new Skin("test", SkinHelper::getSkinDataFromPNG($png), "", "geometry.humanoid.custom", $geometryData);

        $world = $sender->getWorld();
        $pos = $sender->getPosition();
        $location = Location::fromObject($pos->round()->add(0.5, 0, 0.5), $world);
        $location->yaw = $rot; // TO DO : body yaw, head yaw

        $actor = new Actor($location, $sender->getSceneHelper()->getScene(), null, $skin);
        $actor->setScale($scale);
      }
    }
  }

}