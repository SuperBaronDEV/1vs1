<?php

/**
 * Copyright 2018-2019 GamakCZ
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace vixikhd\onevsone;

use pocketmine\command\Command;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use vixikhd\onevsone\arena\Arena;
use vixikhd\onevsone\commands\OneVsOneCommand;
use vixikhd\onevsone\math\Vector3;
use vixikhd\onevsone\provider\YamlDataProvider;

/**
 * Class OneVsOne
 * @package onevsone
 */
class OneVsOne extends PluginBase implements Listener {

    /** @var YamlDataProvider */
    public $dataProvider;

    /** @var EmptyArenaChooser $emptyArenaChooser */
    public $emptyArenaChooser;

    /** @var Command[] $commands */
    public $commands = [];

    /** @var Arena[] $arenas */
    public $arenas = [];

    /** @var Arena[] $setters */
    public $setters = [];

    /** @var int[] $setupData */
    public $setupData = [];

    public function onLoad() {
        $this->dataProvider = new YamlDataProvider($this);
    }

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->dataProvider->loadArenas();
        $this->emptyArenaChooser = new EmptyArenaChooser($this);
        $this->getServer()->getCommandMap()->register("1vs1", $this->commands[] = new OneVsOneCommand($this));
    }

    public function onDisable() {
        $this->dataProvider->saveArenas();
    }

    /**
     * @param PlayerChatEvent $event
     */
    public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();

        if(!isset($this->setters[$player->getName()])) {
            return;
        }

        $event->setCancelled(\true);
        $args = explode(" ", $event->getMessage());

        /** @var Arena $arena */
        $arena = $this->setters[$player->getName()];

        switch ($args[0]) {
            case "help":
                $player->sendMessage("§a> 1vs1 setup help (1/1):\n".
                "§7help : Ukážou ti setup commandy\n" .
                "§7level : Setneš level arény\n".
                "§7spawn : Setneš spawn arény\n".
                "§7joinsign : Setneš ceduli arény\n".
                "§7enable : Spustíš arénu");
                break;
            case "level":
                if(!isset($args[1])) {
                    $player->sendMessage("§cPoužij: §7level <jméno levelu>");
                    break;
                }
                if(!$this->getServer()->isLevelGenerated($args[1])) {
                    $player->sendMessage("§c> Level $args[1] není k dispozici!");
                    break;
                }
                $player->sendMessage("§a> Arena level byl updatnut $args[1]!");
                $arena->data["level"] = $args[1];
                break;
            case "spawn":
                if(!isset($args[1])) {
                    $player->sendMessage("§cPoužij: §7setspawn <kolikátý Spawn >");
                    break;
                }
                if(!is_numeric($args[1])) {
                    $player->sendMessage("§cNapiš číslo!");
                    break;
                }
                if((int)$args[1] > $arena->data["slots"]) {
                    $player->sendMessage("§cJe tu jen {$arena->data["slots"]} slotů!");
                    break;
                }

                $arena->data["spawns"]["spawn-{$args[1]}"] = (new Vector3($player->getX(), $player->getY(), $player->getZ()))->__toString();
                $player->sendMessage("§a> Spawn $args[1] byl udělán na X: " . (string)round($player->getX()) . " Y: " . (string)round($player->getY()) . " Z: " . (string)round($player->getZ()));
                break;
            case "joinsign":
                $player->sendMessage("§a> Znič blok a udělej aréna ceduli!");
                $this->setupData[$player->getName()] = 0;
                break;
            case "enable":
                if(!$arena->setup) {
                    $player->sendMessage("§6> Aréna je spuštěná!");
                    break;
                }
                if(!$arena->enable()) {
                    $player->sendMessage("§c> Nejde načíst aréna, chybí informace!");
                    break;
                }
                $player->sendMessage("§a> Aréna je spuštěná!");
                break;
            case "done":
                $player->sendMessage("§a> Úspěšně jsi opustil setup mód!");
                unset($this->setters[$player->getName()]);
                if(isset($this->setupData[$player->getName()])) {
                    unset($this->setupData[$player->getName()]);
                }
                break;
            default:
                $player->sendMessage("§6> Jsi v setup módu.\n".
                    "§7- použij §lhelp §r§7pro ukázání commandů\n"  .
                    "§7- nebo §ldone §r§7pro leavnutí setup módu");
                break;
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if(isset($this->setupData[$player->getName()])) {
            switch ($this->setupData[$player->getName()]) {
                case 0:
                    $this->setters[$player->getName()]->data["joinsign"] = [(new Vector3($block->getX(), $block->getY(), $block->getZ()))->__toString(), $block->getLevel()->getFolderName()];
                    $player->sendMessage("§a> Cedule updatnuta!");
                    unset($this->setupData[$player->getName()]);
                    $event->setCancelled(\true);
                    break;
            }
        }
    }

    /**
     * @param Player $player
     */
    public function joinToRandomArena(Player $player) {
        $arena = $this->emptyArenaChooser->getRandomArena();
        if(!is_null($arena)) {
            $arena->joinToArena($player);
            return;
        }
        $player->sendMessage("§c> Všechny arény jsou obsazeny, prosím počkej!");
    }
}
