<?php

namespace core\systems\entity\entities;

use core\systems\entity\EntityBehaviorManager;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use core\SwimCoreInstance;
use Exception;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionException;

// a Hybrid implementation of the Human class intended for scene parenting and behavior scripting via overriding and extending
class Actor extends Living
{

  protected Scene $parentScene;
  protected EntityBehaviorManager $entityBehaviorManager;
  private int $lifeTime = -1; // in ticks

  private Skin $skin;
  private UuidInterface $uuid;
  private bool $hasServerSidedSkin = false;

  protected bool $anchored = false;

  /**
   * @throws ReflectionException
   */
  public function __construct(Location $location, ?Scene $parentScene = null, ?CompoundTag $nbt = null, ?Skin $skin = null)
  {
    parent::__construct($location, $nbt);
    $this->entityBehaviorManager = new EntityBehaviorManager($this);

    // apply the skin we were constructed with if possible
    if (isset($skin)) $this->setSkin($skin);

    // so they can't get de-spawned to clients automatically
    $this->setCanSaveWithChunk(false);
    $this->hackFixClose();

    // spawn to all in the parent scene
    if (isset($parentScene)) {
      $this->parentScene = $parentScene;
      $this->spawnToAllInScene();
    }

    // register
    SwimCoreInstance::getInstance()->getSystemManager()->getEntitySystem()->registerEntity($this);
  }

  public function getEntityBehaviorManager(): EntityBehaviorManager
  {
    return $this->entityBehaviorManager;
  }

  public function spawnToAllInScene()
  {
    foreach ($this->parentScene->getPlayers() as $player) {
      $this->spawnTo($player);
    }
  }

  public function deSpawnActorFrom(Player $player): void
  {
    if (!$player->isOnline()) return; // avoid login exception
    $player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($this->getId()));
    // remember to remove from spawned list
    $id = spl_object_id($player);
    unset($this->hasSpawned[$id]);
  }

  /**
   * @throws ReflectionException
   */
  public function deSpawnActorFromAll(): void
  {
    $this->hackFixClose(false);
    foreach ($this->parentScene->getPlayers() as $player) {
      $this->deSpawnActorFrom($player);
    }
    $this->close();
  }

  public function addMotion(float $x, float $y, float $z): void
  {
    if (!$this->anchored) {
      parent::addMotion($x, $y, $z);
    }
  }

  public function setMotion(Vector3 $motion): bool
  {
    if (!$this->anchored) {
      return parent::setMotion($motion);
    }
    return false;
  }

  public static function getNetworkTypeId(): string
  {
    return EntityIds::NPC;
  }

  /**
   * @return Scene
   */
  public function getParentScene(): Scene
  {
    return $this->parentScene;
  }

  /**
   * @param Scene $parentScene
   */
  public function setParentScene(Scene $parentScene): void
  {
    $this->parentScene = $parentScene;
  }

  protected function getInitialSizeInfo(): EntitySizeInfo
  {
    return new EntitySizeInfo(1.8, 0.6, 1.62);
  }

  public function init(): void
  {
    $this->entityBehaviorManager->init();
    if ($this->anchored) {
      $this->getAttributeMap()->add(new Attribute(Attribute::KNOCKBACK_RESISTANCE, 1, 1, 1, true));
    }
  }

  public function updateSecond(): void
  {
    $this->entityBehaviorManager->updateSecond();
  }

  // makes sure the player is in the same scene as this entity
  public function spawnTo(Player $player): void
  {
    if (isset($this->parentScene) && $player instanceof SwimPlayer) {
      if ($player->getSceneHelper()?->getScene() === $this->parentScene) {
        parent::spawnTo($player);
        // send skin if we have that saved on the server and not in a resource pack
        if ($this->hasServerSidedSkin) $this->sendSkinToPlayer($player);
      }
    }
  }

  /**
   * @throws ReflectionException
   * @breif override while still calling this base method if you need by tick behavior updates
   */
  public function updateTick(): void
  {
    $this->entityBehaviorManager->updateTick();
    $this->lifeTime--;
    if ($this->lifeTime == 0) { // by default lifetime is -1 so this won't happen until it is set to a positive number
      $this->destroy();
    }
  }

  public function exit(): void
  {
    $this->entityBehaviorManager->exit();
  }

  /**
   * @throws ReflectionException
   */
  public function destroy(bool $deSpawn = true, bool $kill = false, bool $callExit = true): void
  {
    SwimCoreInstance::getInstance()->getSystemManager()->getEntitySystem()->deregisterEntity($this, $deSpawn, $kill, $callExit);
  }

  /**
   * @return int
   */
  public function getLifeTime(): int
  {
    return $this->lifeTime;
  }

  /**
   * @param int $lifeTime
   */
  public function setLifeTime(int $lifeTime): void
  {
    $this->lifeTime = $lifeTime;
  }

  // this is fucking insane why does Human class have to do this
  public function getOffsetPosition(Vector3 $vector3): Vector3
  {
    if ($this->hasServerSidedSkin) {
      return $vector3->add(0, 1.621, 0);
    }
    return parent::getOffsetPosition($vector3);
  }

  public function event(string $message, mixed $args = null): void
  {
  }

  public function attack(EntityDamageEvent $source): void
  {
    $cause = $source->getCause();
    if ($cause == EntityDamageEvent::CAUSE_SUFFOCATION || $cause == EntityDamageEvent::CAUSE_STARVATION) {
      $source->cancel();
      return;
    }

    // disable critical hit to avoid particles
    $source->setModifier(0.0, EntityDamageEvent::MODIFIER_CRITICAL);

    if ($source instanceof EntityDamageByChildEntityEvent) {
      $child = $source->getChild();
      $player = $child->getOwningEntity();
      if ($player instanceof SwimPlayer) {
        $this->attackedByChild($source, $player, $child);
      }
    } else if ($source instanceof EntityDamageByEntityEvent) {
      $player = $source->getDamager();
      if ($player instanceof SwimPlayer) {
        $this->attackedByPlayer($source, $player);
      }
    } else {
      $this->damaged($source);
    }
  }

  public function doAnimation
  (
    string $animation,
    string $nextState = "",
    string $stopExpression = "",
    int    $stopExpressionVersion = 0,
    string $controller = "",
    float  $blendOutTime = 0
  ): void
  {
    foreach ($this->getWorld()->getViewersForPosition($this->getPosition()) as $player) {
      if (isset($player)) {
        if ($player->isConnected()) {
          $player->getNetworkSession()->sendDataPacket(AnimateEntityPacket::create(
            $animation, $nextState, $stopExpression, $stopExpressionVersion, $controller, $blendOutTime, [$this->getId()]
          ));
        }
      }
    }
  }

  protected function initEntity(CompoundTag $nbt): void
  {
    parent::initEntity($nbt);

    $this->setNameTagAlwaysVisible(); // what would not always show name tag mean? culled behind walls?
    $this->setScale(2);

    if ($this->hasServerSidedSkin) {
      $this->setSkinUUID();
    }
  }

  public function saveNBT(): CompoundTag
  {
    $nbt = parent::saveNBT();

    if ($this->hasServerSidedSkin) {
      $nbt->setTag("Skin", CompoundTag::create()
        ->setString("Name", $this->skin->getSkinId())
        ->setByteArray("Data", $this->skin->getSkinData())
        ->setByteArray("CapeData", $this->skin->getCapeData())
        ->setString("GeometryName", $this->skin->getGeometryName())
        ->setByteArray("GeometryData", $this->skin->getGeometryData())
      );
    }

    return $nbt;
  }

  protected function sendSpawnPacket(Player $player): void
  {
    if (!$this->hasServerSidedSkin) {
      parent::sendSpawnPacket($player);
      return;
    }

    $networkSession = $player->getNetworkSession();
    $typeConverter = $networkSession->getTypeConverter();
    if (!($this instanceof Player)) {
      $networkSession->sendDataPacket(PlayerListPacket::add([
        PlayerListEntry::createAdditionEntry($this->uuid, $this->id, $this->getName(), $typeConverter->getSkinAdapter()->toSkinData($this->skin))
      ]));
    }

    // Implementing head yaw would mean non server sided skinned entities need to use this part of function.
    // This is scuffed though because this uses add player packet and not add actor packet.
    // I am not going to refactor that until I decide I need head yaw for all custom entities.
    $networkSession->sendDataPacket(AddPlayerPacket::create(
      $this->getUniqueId(),
      $this->getName(),
      $this->getId(),
      "",
      $this->location->asVector3(),
      $this->getMotion(),
      $this->location->pitch,
      $this->location->yaw,
      $this->location->yaw, // TODO: head yaw
      ItemStackWrapper::legacy($typeConverter->coreItemStackToNet(VanillaItems::AIR())), // never holds anything
      GameMode::SURVIVAL,
      $this->getAllNetworkData(),
      new PropertySyncData([], []),
      UpdateAbilitiesPacket::create(new AbilitiesData(CommandPermissions::NORMAL, PlayerPermissions::VISITOR, $this->getId(), [
        new AbilitiesLayer(
          AbilitiesLayer::LAYER_BASE,
          array_fill(0, AbilitiesLayer::NUMBER_OF_ABILITIES, false),
          0.0,
          0.0
        )
      ])),
      [],
      "",
      DeviceOS::UNKNOWN
    ));

    $this->sendData([$player], [EntityMetadataProperties::NAMETAG => new StringMetadataProperty($this->getNameTag())]);

    $entityEventBroadcaster = $networkSession->getEntityEventBroadcaster();
    $entityEventBroadcaster->onMobArmorChange([$networkSession], $this);
    // $entityEventBroadcaster->onMobOffHandItemChange([$networkSession], $this); // for Human class only

    if (!($this instanceof Player)) {
      $networkSession->sendDataPacket(PlayerListPacket::remove([PlayerListEntry::createRemovalEntry($this->uuid)]));
    }
  }

  /**
   * @throws Exception
   * @breif You need to pass in json data decoded: $jsonData = json_decode($jsonString, true);
   */
  public static function getProperty(mixed $jsonData, string $animationName, string $property): mixed
  {
    // Navigate to the specific animation using the animation name
    if (isset($jsonData['animations'][$animationName])) {
      $animation = $jsonData['animations'][$animationName];

      // Check if the specified property exists for this animation
      if (isset($animation[$property])) {
        return $animation[$property];
      } else {
        throw new Exception("Property '$property' not found in animation '$animationName'.");
      }
    } else {
      throw new Exception("Animation '$animationName' not found.");
    }
  }

  /**
   * @throws ReflectionException
   */
  public function hackFixClose(bool $close = true): void
  {
    (new ReflectionClass(Entity::class))->getProperty("closeInFlight")->setValue($this, $close);
  }

  protected function getInitialDragMultiplier(): float
  {
    return 0.02;
  }

  protected function getInitialGravity(): float
  {
    return 0.08;
  }

  protected function damaged(EntityDamageEvent $source)
  {
  }

  protected function attackedByPlayer(EntityDamageByEntityEvent $source, SwimPlayer $player)
  {
  }

  protected function attackedByChild(EntityDamageByChildEntityEvent $source, SwimPlayer $player, ?Entity $child)
  {
  }

  public function onInteract(Player $player, Vector3 $clickPos): bool
  {
    if ($player instanceof SwimPlayer) {
      $this->playerInteract($player, $clickPos);
    }
    return true;
  }

  // optional override
  protected function playerInteract(SwimPlayer $player, Vector3 $clickPos): void
  {
  }

  public function getName(): string
  {
    return $this->getNameTag();
  }

  // this is an expensive network operation, only done for server sided created skins that can't be preloaded in a resource pack
  public function sendSkinToPlayer(Player $player): void
  {
    $this->sendSkin([$player]);
  }

  /**
   * Sends the human's skin to the specified list of players. If null is given for targets, the skin will be sent to
   * all viewers.
   *
   * @param Player[]|null $targets
   */
  public function sendSkin(?array $targets = null): void
  {
    if ($this instanceof Player && $this->getNetworkSession()->getProtocolId() === ProtocolInfo::PROTOCOL_1_19_60) {
      $targets = array_diff($targets ?? $this->hasSpawned, [$this]);
    }

    TypeConverter::broadcastByTypeConverter($targets ?? $this->hasSpawned, function (TypeConverter $typeConverter): array {
      return [
        PlayerSkinPacket::create($this->uuid, "", "", $typeConverter->getSkinAdapter()->toSkinData($this->skin))
      ];
    });
  }

  public function getSkin(): Skin
  {
    return $this->skin;
  }

  public function setSkin(Skin $skin): void
  {
    $this->skin = $skin;
    $this->hasServerSidedSkin = true;
    $this->setSkinUUID();
  }

  private function setSkinUUID(): void
  {
    $this->uuid = Uuid::uuid3(Uuid::NIL, ((string)$this->getId()) . $this->skin->getSkinData() . $this->getNameTag());
  }

  public function hasServerSidedSkin(): bool
  {
    return $this->hasServerSidedSkin;
  }

  public function getUniqueId(): UuidInterface
  {
    return $this->uuid;
  }

}