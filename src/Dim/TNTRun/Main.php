<?php

/**
 * Copyright (c) 2020 Dim Biskey
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

declare(strict_types=1);

namespace Dim\TNTRun;

use Dim\TNTRun\command\TNTRunCommand;
use Dim\TNTRun\constant\GameConstants;
use Dim\TNTRun\entity\FallingBlockEntity;
use Dim\TNTRun\game\Game;
use Dim\TNTRun\listener\EventListener;
use Dim\TNTRun\task\GameTickTask;
use Dim\TNTRun\task\MovementHandleTask;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase
{
    /**
     * @var Game[]
     */
    protected $games;

    public function onEnable(): void
    {
        @mkdir($this->getDataFolder() . "arenas");
        $this->games = [];
        $this->loadGames();
        Entity::registerEntity(FallingBlockEntity::class, true);
        $this->getScheduler()->scheduleRepeatingTask(new GameTickTask($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new MovementHandleTask($this), $this->getConfig()->get("block-remove-period"));
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getCommandMap()->register("tntrun", new TNTRunCommand($this));
    }

    protected function loadGames(): void
    {
        $path = $this->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR;
        $this->getLogger()->notice("Loading games...");
        foreach (scandir($path) as $game) {
            $this->loadGame($game);
        }
    }

    public function onDisable(): void
    {
        foreach ($this->games as $game => $_) {
            $this->unloadGame($game, true);
        }
    }

    public function unloadGame(string $name, bool $stop = false): bool
    {
        if (isset($this->games[$name])) {
            if ($stop) {
                $this->games[$name]->stopGame(false);
            }
            unset($this->games[$name]);
            return true;
        }
        return false;
    }

    public function loadGame(string $name): void
    {
        if (isset($this->games[$name])) {
            return;
        }
        $dir = $this->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name;
        $data = $dir . "/game.json";
        if (!is_file($data)) {
            return;
        }
        $info = json_decode(file_get_contents($data), true);
        $this->games[$info["name"]] = new Game($this, $info["name"], $info["world"], $info["lobby"], $info["players"], $info["time"], $info["countdown"], $info["restart"]);
    }

    /**
     * @return Game[]
     */
    public function getGames(): array
    {
        return $this->games;
    }

    public function getPlayerGame(Player $player): ?Game
    {
        foreach ($this->games as $game) {
            if ($game->isPlaying($player)) {
                return $game;
            }
        }
        return null;
    }

    public function getGameFromWorld(string $world): ?Game
    {
        foreach ($this->games as $game) {
            if ($game->getWorld()->getFolderName() === $world) {
                return $game;
            }
        }
        return null;
    }

    public function findGame(): ?Game
    {
        $games = [];
        foreach ($this->games as $game) {
            if ($game->getState() === GameConstants::GAME_RUNNING or $game->getState() === GameConstants::GAME_RESETTING or $game->getState() === GameConstants::GAME_SETUP or $game->isFull()) {
                continue;
            }
            $games[] = $game;
        }
        return count($games) > 0 ? $games[array_rand($games, 1)] : null;
    }

    public function isPlaying(Player $player): bool
    {
        foreach ($this->games as $game) {
            return $game->isPlaying($player);
        }
        return false;
    }
}
