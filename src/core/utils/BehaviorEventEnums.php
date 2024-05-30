<?php

namespace core\utils;

class BehaviorEventEnums
{

  public const BLOCK_BREAK_EVENT = 0;
  public const BLOCK_PLACE_EVENT = 1;
  public const ENTITY_DAMAGE_BY_CHILD_ENTITY_EVENT = 2;
  public const ENTITY_DAMAGE_BY_ENTITY_EVENT = 3;
  public const ENTITY_DAMAGE_EVENT = 4;
  public const ENTITY_ITEM_PICKUP_EVENT = 5;
  public const ENTITY_REGAIN_HEALTH_EVENT = 6;
  public const ENTITY_SPAWN_EVENT = 7;
  public const ENTITY_TELEPORT_EVENT = 8;
  public const PROJECTILE_HIT_ENTITY_EVENT = 9;
  public const PROJECTILE_HIT_EVENT = 10;
  public const PROJECTILE_LAUNCH_EVENT = 11;
  public const INVENTORY_TRANSACTION_EVENT = 12;
  public const PLAYER_DROP_ITEM_EVENT = 13;
  public const PLAYER_INTERACT_EVENT = 14;
  public const PLAYER_ITEM_CONSUME_EVENT = 15;
  public const PLAYER_ITEM_USE_EVENT = 16;
  public const PLAYER_TOGGLE_FLIGHT_EVENT = 17;
  public const DATA_PACKET_RECEIVE_EVENT = 18;
  public const PLAYER_JUMP_EVENT = 19;

}