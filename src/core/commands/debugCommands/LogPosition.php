<?php

namespace core\commands\debugCommands;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use CortexPE\Commando\args\BooleanArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use DateTime;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;

class LogPosition extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct($core, "logpos", "log a position");
    $this->setPermission("use.staff");
    $this->core = $core;
  }

  /**
   * @inheritDoc
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new BooleanArgument("save", true));
  }

  /**
   * @inheritDoc
   */
  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      if (isset($args["save"])) {
        if ($args["save"]) { // if save argument is passed and is true
          // write to json file
          $this->write($sender);
          $sender->sendMessage("Wrote to JSON in Plugin Data");
        } else { // if save argument is passed and is false
          // clear
          $sender->getAttributes()->setAttribute("positions", []);
          $sender->sendMessage("Cleared positions data");
        }
      } else { // if no save argument is passed
        // add to attribute as an array
        $this->updatePositions($sender);
      }
    }
  }

  private function updatePositions(SwimPlayer $sender): void
  {
    $attributes = $sender->getAttributes();
    $vec3 = $sender->getPosition()->asVector3();
    $vec3 = new Vector3($vec3->getFloorX(), $vec3->getFloorY(), $vec3->getFloorZ()); // no decimals
    $positions = $attributes->getAttribute("positions") ?? [];
    $positions[] = $vec3;
    $attributes->setAttribute("positions", $positions);
    $sender->sendMessage("Added position #" . count($positions) . ": " . $vec3->x . ", " . $vec3->y . ", " . $vec3->z);
  }

  // writes to a json file in the plugin data folder called positions_datetime
  private function write(SwimPlayer $sender): void
  {
    $attributes = $sender->getAttributes();
    $positions = $attributes->getAttribute("positions") ?? [];

    $dateTime = new DateTime();
    $formattedFileName = $dateTime->format('m_d_Y_H_i');

    $pluginDataFolder = $this->core->getDataFolder();
    $pluginDataFolder = rtrim($pluginDataFolder, '/') . '/';

    $filename = "positions_" . $formattedFileName . ".json";
    $filePath = $pluginDataFolder . $filename;

    $json = json_encode($positions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($filePath, $json);

    $sender->sendMessage("Positions saved to JSON file: $filePath");
  }

}