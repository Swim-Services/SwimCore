<img src="https://github.com/Swedeachu/SwimCore/assets/63020914/03216eac-2573-4d6c-850a-4a6c418262f4" width="50%" alt="swimcore"/>

# SwimCore: The engine of swim.gg
So basically imagine a game engine placed ontop of PocketMine.
<br>
# Getting Started
We use PHP 8.2 and a fork of the NetherGames Pocketmine for multi version and some extra features. Using the normal NGPM and distrubuted Pocketmine PHP binaries should be fine.
<br>
The virions we use are found in .poggit.yml. You will need to download and place them in the virions directory yourself.
```
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
# World Setup:
SwimCore keeps a copy of worlds to autoload in a directory called savedWorlds. It is required that both these world directories have a world named 'hub'.

![worlds](https://github.com/Swedeachu/SwimCore/assets/63020914/1aa5b2ad-af42-4629-b6b2-cdd6f1e1b462)

# Database Setup:
SwimCore uses MariaDB MySql syntax. You must have a database connection established for the server to run properly. To configure this, see below:

![image](https://github.com/Swedeachu/SwimCore/assets/63020914/82a8a1fd-ba4a-4cd2-bd1a-0ff66beb3928)

config.yml:
```
---
database:
  host: # whatever IP you connect with to the database
  username: 
  password: 
  schema: 
  port: 3306
  workerLimit: 2
motds: # list of modes that can be cycled through on the client's UI
- §bSWIM.GG
- §9SCRIMS
...
```
localDatabase.yml (intended for self hosted database instances in a dev environment)
```
database:
  main:
    host: "127.0.0.1"
    username: "root"
    password: 
    schema: 
    port: 3306
  worker-limit: 2
```
# How this is a game engine
This is about 2 years worth of rewrites and learning on and off, so about 6 months worth of work in real time.
<br>
SwimCore is powered by a system manager class that updates the core systems for things like scenes, players, actors, events, and behaviors.
<br>
The main concept behind SwimCore is to have everything sorted into scenes to encapsulate and simplfy a server's logic to more cohesive single parts.
<br>
This means the hub is a scene, Kit FFA is a scene, any duel created is a scene. Everything is stored and updated each tick inside of a scene.
<br>
Players and actors have a simple ECS (entity component system) for storing data and behaviors within them for game scripting and event call backs.
<br>
We have a single event listener for routing every call back to the player's scene they are in, along with any behavior components attatched.
<br>
I've also implemented a custom Actor class for making custom actors (aka custom entities) that can have any skin and geo and behavior. They are setup in a resource pack identical to how customies does it.
<br>
Really the best way to understand this framework beyond making a game engine yourself is to clone this repo and start exploring the code and making things with it.
# Some extra fun stuff
This has a lot of example code for how to make custom things, including duels and FFA scenes for nodebuff and boxing and midfight. I also left in an abstract class SkyGoalGame that does 90% of the logic for a game like bridge or battle rush.
I would spend some time reading those scenes, the UI form code, and spend some time in the prefabs namespace looking around at how I made custom items and actors.
More specifically, I would read the player behavior event scripts I wrote such as NoFall and DoubleJump to see how player behavior components are made.
Another very important thing to understand is how I deserialize map data from json. You can read the maps namespace and data directory in the SwimCore plug in folder to understand how that works.
I have included the full implementation of swim.gg's server event system and party system, as those are often major time sinks and design challenges when programming a bedrock pvp server.
The least you can get out of this repo is a good idea on how to program something for your server.
# IMPORTANT
This repo is just the GAME engine. Any swim.gg anticheat and security related implementations are left out on purpose for a million different reasons. 
<br>
This repo does however have the bare minimum database tables created for storing the history of logged in players XUID, settings, punishments, and Ranks. Punishment commands for muting and banning are purposefully left in, but without any form of alt tracking such as use of IPs and other extra info from the client.
