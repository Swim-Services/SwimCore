<img src="https://github.com/Swedeachu/SwimCore/assets/63020914/03216eac-2573-4d6c-850a-4a6c418262f4" width="50%" alt="swimcore"/>

# SwimCore: The engine of swim.gg
So basically imagine a game engine placed ontop of PocketMine.

## Getting Started

- **PHP Version**: We use PHP 8.2.
- **PocketMine Fork**: Swim.gg and Divinity use a fork of the NetherGames PocketMine for multi-version support.
  - Using the normal NGPM and distributed PocketMine PHP binaries should also work fine. If anything requires minor tweaks.
- **Virions**: The virions we use for Swim.gg are listed in `.poggit.yml`. You need to download them off poggit and place them in the `virions` directory.

### Example `.poggit.yml` Configuration:
```yaml
projects:
  swim:
    path: ""
    compressBuilds: false
    libs:
      - src: poggit/libasynql/libasynql 
        version: ^4.1.0
        epitope: .random
      - src: Ifera/ScoreFactory/ScoreFactory
        version: ^3.1.0
      - src: jojoe77777/FormAPI/libFormAPI
        version: ^2.1.1
      - src: muqsit/InvMenu/InvMenu
        version: ^4.4.1
      - src: jasonw4331/libpmquery/libpmquery
        version: ^1.0.0
```

## World Setup

- SwimCore keeps a copy of worlds to autoload in a directory called `savedWorlds`.
- Both the `savedWorlds` and `worlds` directories must have a world named `hub` to use as the default hub world.

![worlds](https://github.com/Swedeachu/SwimCore/assets/63020914/1aa5b2ad-af42-4629-b6b2-cdd6f1e1b462)

---

## Database Setup

- **Database Type**: SwimCore uses MariaDB with MySQL syntax.
- **Required**: A database connection must be established for the server to run properly.
- If you have no database, you can modify the code to not call the load and save component functions in the `SwimPlayer` class

### Configuration:

1. **config.yml:**
```yaml
---
database:
  host: # IP address of the database
  username: 
  password: 
  schema: 
  port: 3306
  workerLimit: 2
motds: # List of MOTDs to cycle through in the client's UI
- §bSWIM.GG
- §9SCRIMS
...
```

2. **localDatabase.yml (For development environments):**
```yaml
database:
  main:
    host: "127.0.0.1"
    username: "root"
    password: 
    schema: 
    port: 3306
  worker-limit: 2
```

![image](https://github.com/Swedeachu/SwimCore/assets/63020914/82a8a1fd-ba4a-4cd2-bd1a-0ff66beb3928)

---

## Designed to be a library based "AAA" Game Engine

- This represents about 2 years of rewrites and learning, totaling around 7 months of real-time work between breaks.
- **Core Design**: 
  - Powered by a **System Manager** class that updates core systems every tick and second for:
    - Scenes
    - Players
    - Actors
    - Events
    - Behaviors
  - Everything is sorted into **scenes** to encapsulate and simplify server logic.
    - Example: Hub, Kit FFA, and duels are all scenes.
    - Everything updates each tick within its respective scene.
  - Players and actors follow a basic **ECS (Entity Component System)** for data storage and per player specific behavior scripting.
- **Custom Actor Class**:
  - Custom entities can have any skin, geo, animations, and behavior scripts.
  - This is set up using a resource pack identical to how the Customies library works.

### Key Features:
- **Scenes**: Hub, Kit FFA, duels, and other parts of the server are handled as separate scenes.
- **Player ECS**: Entity Component System used to handle player behaviors and callbacks.
- **Custom Actors**: Create custom entities with skins, geometries, and behaviors to control them.
  
---

## Auto-Loading of Actors, Commands, and Scenes

One of the most powerful features of SwimCore is the ability to **auto-load PHP scripts** on server start-up without manually registering them.

### Auto-Loading Mechanism:
- **Scenes**: Any script in the `core\scenes` namespace that implements `public static function AutoLoad(): bool` will be auto-loaded into the `SceneSystem`.
- **Actors**: Scripts in the `core\custom\prefabs` namespace will automatically register entities in the entity factory.
- **Commands**: Scripts in the `core\commands` namespace will auto-register commands.
- This works for sub folders in these directories too! Fully recursive and smart class script type detection.

---

## Fun Extras

- **Examples**: 
  - Includes examples of simple duels and FFA scenes (nodebuff, boxing, midfight).
  - Abstract class `SkyGoalGame` to work as a base implementation for games like Bridge or Battle Rush.
- **Code Highlights**:
  - Lots of UI form code.
  - Custom items and actors in the `prefabs` namespace (Basic Actor example script and some Potions and Pearls and other PvP things).
  - Player behavior event scripts (`NoFall`, `DoubleJump`, `MaxDistance`).
  - JSON map data deserialization for arenas (maps namespace).
- **Systems Included**: Full implementation of swim.gg's **server event** and **party systems**.

---

## IMPORTANT

This repo contains only the **game engine**.

- **Anticheat and Security**: Any swim.gg anticheat detections and security implementations are excluded.
- **Raklib left out**: Unfortunately for some security reasons, we have to leave out our RakLib interface code. This means the code for rotating MOTDS is not present.
- **Database Tables**: Contains basic tables for storing player history, settings, punishments, and ranks.
- **Punishment Commands**: Muting and banning commands are included, but without advanced features like alternate account tracking via client data collection.

## TODO

- Hot reloading + anytime loading of Scenes, Actors, Behaviors, and Command scripts. No more restarting the server to test a new feature.
- Cosmetic 3D geometry system that works via components per player for doing things like wings and hats and back bling.
- Automated tournament system.
- Clans/guilds.
- Elo leaderboards.
- Truly custom item system (right now just overrides callbacks on existing items such as OnUseEvent)
- Custom blocks (needed for retexturing of things like knock back TNT)
- Automatic arena copy and pasting + generating spawn positions from a single plotted out arena.

---
