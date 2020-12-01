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

namespace Dim\TNTRun\game;

use Dim\TNTRun\constant\GameConstants;
use Dim\TNTRun\entity\FallingBlockEntity;
use Dim\TNTRun\Main;
use Dim\TNTRun\session\Session;
use Dim\TNTRun\util\ScoreboardManager;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class Game
{
    /**
     * @var Main
     */
    protected $plugin;
    /**
     * @var Session[]
     */
    protected $players;
    /**
     * @var int
     */
    protected $state;
    /**
     * @var int
     */
    protected $countdown;
    /**
     * @var int
     */
    protected $elapsedTime;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var Level|null
     */
    protected $world;
    /**
     * @var Level|null
     */
    protected $waitingLobby;
    /**
     * @var int
     */
    protected $maximumPlayers;
    /**
     * @var int
     */
    protected $time;
    /**
     * @var int
     */
    protected $resetPeriod;

    public function __construct(Main $plugin, string $name, string $world, string $lobby, int $maximumPlayers, int $time, int $countdown, int $reset)
    {
        $this->plugin = $plugin;
        $this->name = $name;
        $this->waitingLobby = null;
        $this->players = [];
        $this->maximumPlayers = $maximumPlayers;
        $this->time = $time * 60;
        $this->countdown = $countdown;
        $this->resetPeriod = $reset;
        if (!$this->plugin->getServer()->loadLevel($world)) {
            $plugin->getLogger()->error("Couldn't load the world for game: " . $name);
            $this->state = GameConstants::GAME_SETUP;
            return;
        }
        if (!$this->plugin->getServer()->loadLevel($lobby)) {
            $plugin->getLogger()->error("Couldn't load the lobby for game: " . $name);
            $this->state = GameConstants::GAME_SETUP;
            return;
        }
        $this->world = $plugin->getServer()->getLevelByName($world);
        $this->world->setAutoSave(false);
        $this->world->setTime(Level::TIME_DAY);
        $this->world->stopTime();
        $this->waitingLobby = $plugin->getServer()->getLevelByName($lobby);
        $this->state = GameConstants::GAME_IDLE;
        $plugin->getLogger()->notice("Successfully loaded game: " . $name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPlugin(): Main
    {
        return $this->plugin;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function setState(int $state): void
    {
        $this->state = $state;
    }

    public function getWorld(): ?Level
    {
        return $this->world;
    }

    public function getResetPeriod(): int
    {
        return $this->resetPeriod;
    }

    public function getWaitingLobby(): ?Level
    {
        return $this->waitingLobby;
    }

    public function getCountdown(): int
    {
        return $this->countdown;
    }

    public function getMaximumPlayers(): int
    {
        return $this->maximumPlayers;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function getElapsedTime(): int
    {
        return $this->elapsedTime;
    }

    public function addPlayer(Player $player): bool
    {
        if ($this->isPlaying($player) or $this->plugin->isPlaying($player)) {
            $player->sendMessage(TextFormat::RED . "You're already playing.");
            return false;
        }
        if ($this->state === GameConstants::GAME_RUNNING or $this->state === GameConstants::GAME_RESETTING or $this->state === GameConstants::GAME_RESETTING or $this->state === GameConstants::GAME_COUNTDOWN and $this->countdown <= 5) {
            $player->sendMessage(TextFormat::RED . "That game already started.");
            return false;
        }
        if ($this->isFull()) {
            $player->sendMessage(TextFormat::RED . "That game is full");
            return false;
        }
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->removeAllEffects();
        $player->setGamemode(Player::ADVENTURE);
        $player->teleport($this->waitingLobby->getSpawnLocation());
        $this->players[$player->getName()] = new Session($this->plugin, $player);
        $this->sendMessage("player.game.join", ["{player}"], [$player->getName()]);
        ScoreboardManager::createDisplay($player, TextFormat::colorize($this->plugin->getConfig()->get("scoreboard-title")));
        return true;
    }

    public function isPlaying(Player $player): bool
    {
        return isset($this->players[$player->getName()]);
    }

    public function isFull(): bool
    {
        return count($this->players) >= $this->maximumPlayers;
    }

    public function sendMessage(string $message, array $replace = [], array $conversion = []): void
    {
        foreach ($this->getPlayers() as $player) {
            $player->sendMessage(str_replace($replace, $conversion, $this->getTranslation($message)));
        }
    }

    /**
     * @return Session[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function getTranslation(string $message): string
    {
        return isset($this->plugin->getConfig()->getAll()[$message]) ? TextFormat::colorize($this->plugin->getConfig()->getAll()[$message]) : "Translation not found.";
    }

    public function sendSubTitle(string $message, array $replace = [], array $conversion = []): void
    {
        foreach ($this->getPlayers() as $player) {
            $player->sendSubTitle(str_replace($replace, $conversion, $this->getTranslation($message)));
        }
    }

    public function handleGameScoreboard(): void
    {
        switch ($this->state) {
            case GameConstants::GAME_WAITING:
                foreach ($this->players as $player) {
                    ScoreboardManager::removeDisplay($player->getPlayer());
                    ScoreboardManager::createDisplay($player->getPlayer(), TextFormat::colorize($this->plugin->getConfig()->get("scoreboard-title")));
                    $i = 0;
                    foreach ($this->plugin->getConfig()->get("waiting-scoreboard") as $line) {
                        $formatted = str_replace(["{players}", "{map}"], [count($this->players), $this->name], $line);
                        ScoreboardManager::sendLine($player->getPlayer(), TextFormat::colorize($formatted), $i);
                        $i++;
                    }
                }
                break;
            case GameConstants::GAME_COUNTDOWN:
                foreach ($this->players as $player) {
                    ScoreboardManager::removeDisplay($player->getPlayer());
                    ScoreboardManager::createDisplay($player->getPlayer(), TextFormat::colorize($this->plugin->getConfig()->get("scoreboard-title")));
                    $i = 0;
                    foreach ($this->plugin->getConfig()->get("countdown-scoreboard") as $line) {
                        $formatted = str_replace(["{players}", "{map}", "{count}"], [count($this->players), $this->name, $this->countdown], $line);
                        ScoreboardManager::sendLine($player->getPlayer(), TextFormat::colorize($formatted), $i);
                        $i++;
                    }
                }
                break;
            case GameConstants::GAME_RUNNING:
                foreach ($this->players as $player) {
                    ScoreboardManager::removeDisplay($player->getPlayer());
                    ScoreboardManager::createDisplay($player->getPlayer(), TextFormat::colorize($this->plugin->getConfig()->get("scoreboard-title")));
                    $i = 0;
                    foreach ($this->plugin->getConfig()->get("running-scoreboard") as $line) {
                        $formatted = str_replace(["{players}", "{map}", "{time}"], [count($this->players), $this->name, gmdate("i:s", $this->time - $this->elapsedTime)], $line);
                        ScoreboardManager::sendLine($player->getPlayer(), TextFormat::colorize($formatted), $i);
                        $i++;
                    }
                }
        }
    }

    public function sendTip(string $message, array $replace = [], array $conversion = []): void
    {
        foreach ($this->getPlayers() as $player) {
            $player->sendTip(str_replace($replace, $conversion, $this->getTranslation($message)));
        }
    }

    public function handleMovements(): void
    {
        $blocks = [];
        foreach ($this->players as $player) {
            if (!$player->getPlayer()->hasMovementUpdate()) {
                continue;
            }
            foreach ($player->getPlayer()->getLevel()->getCollisionBlocks($player->getPlayer()->getBoundingBox()->offsetCopy(0, -0.5, 0)) as $block) {
                if ($block->getY() < $player->getPlayer()->getY()) {
                    $blocks[] = $block;
                }
            }
        }
        foreach ($blocks as $block) {
            if (in_array($block->getId(), $this->plugin->getConfig()->get("breakable-blocks"))) {
                $this->world->setBlock($block, BlockFactory::get(BlockIds::AIR));
                $entity = new FallingBlockEntity($this->world, $block, Entity::createBaseNBT($block));
                $entity->spawnToAll();
            }
        }
    }

    public function tickGame(): void
    {
        switch ($this->state) {
            case GameConstants::GAME_IDLE:
                if (count($this->players) >= 1) {
                    $this->state = GameConstants::GAME_WAITING;
                    return;
                }
                break;
            case GameConstants::GAME_WAITING:
                if (count($this->players) >= 2) {
                    $this->state = GameConstants::GAME_COUNTDOWN;
                    return;
                }
                if (count($this->players) < 1) {
                    $this->state = GameConstants::GAME_IDLE;
                    return;
                }
                break;
            case GameConstants::GAME_COUNTDOWN:
                if (count($this->players) < 2) {
                    $this->state = GameConstants::GAME_WAITING;
                    return;
                }
                if ($this->countdown > 0) {
                    if ($this->countdown === 6) {
                        foreach ($this->players as $player) {
                            $player->getPlayer()->teleport($this->world->getSpawnLocation());
                            $player->getPlayer()->addEffect(new EffectInstance(Effect::getEffect(Effect::NIGHT_VISION), 9999, 255, false));
                        }
                    }
                    switch ($this->countdown) {
                        case 5:
                        case 4:
                        case 3:
                        case 2:
                        case 1:
                            $this->sendTitle("countdown.title", ["{count}"], [$this->countdown]);
                            $this->sendMessage("countdown.message", ["{count}"], [$this->countdown]);
                            foreach ($this->players as $player) {
                                $player->getPlayer()->getLevel()->addSound(new ClickSound($player->getPlayer()));
                            }
                    }
                    $this->countdown--;
                    return;
                }
                $this->startGame();
                $this->state = GameConstants::GAME_RUNNING;
                break;
            case GameConstants::GAME_RUNNING:
                if (count($this->players) < 2) {
                    $this->state = GameConstants::GAME_RESETTING;
                    foreach ($this->players as $player) {
                        $this->setSpectator($player->getPlayer());
                        $this->sendTitle("win.title");
                        $this->world->broadcastLevelSoundEvent($player->getPlayer(), LevelSoundEventPacket::SOUND_LEVELUP);
                    }
                    return;
                }
                if ($this->elapsedTime >= $this->time) {
                    $this->state = GameConstants::GAME_RESETTING;
                    return;
                }
                $this->elapsedTime++;
                break;
            case GameConstants::GAME_RESETTING:
                if ($this->resetPeriod > 1) {
                    $this->resetPeriod--;
                    return;
                }
                $this->stopGame();
        }
    }

    public function sendTitle(string $message, array $replace = [], array $conversion = []): void
    {
        foreach ($this->getPlayers() as $player) {
            $player->sendTitle(str_replace($replace, $conversion, $this->getTranslation($message)));
        }
    }

    public function startGame(): void
    {
        $this->sendTitle("game.start");
        $this->sendMessage("game.start.message");
        foreach ($this->players as $player) {
            $player->getPlayer()->getLevel()->addSound(new BlazeShootSound($player->getPlayer()));
        }
    }

    public function setSpectator(Player $player): void
    {
        $player->addTitle(TextFormat::colorize($this->getTranslation("lose.title")));
        $player->addSubTitle(TextFormat::colorize($this->getTranslation("lose.subtitle")));
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->setHealth($player->getMaxHealth());
        $player->setGamemode(Player::SPECTATOR);
        $player->teleport($this->world->getSpawnLocation());
        $player->getInventory()->setItem(2, ItemFactory::get(Item::BLAZE_POWDER)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . "Play Again"));
        $player->getInventory()->setItem(4, ItemFactory::get(Item::COMPASS)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . "Spectate"));
        $player->getInventory()->setItem(6, ItemFactory::get(Item::BED, 14)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . "Back to Hub"));
    }

    public function stopGame(bool $reload = true): void
    {
        foreach ($this->players as $player) {
            $this->eliminatePlayer($player->getPlayer(), true, false);
            ScoreboardManager::removeDisplay($player->getPlayer());
        }
        foreach ($this->world->getPlayers() as $player) {
            ScoreboardManager::removeDisplay($player);
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->removeAllEffects();
            $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());
            $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
        }
        $this->players = [];
        $this->plugin->unloadGame($this->name);
        $this->plugin->getServer()->unloadLevel($this->world, true);
        $this->plugin->getServer()->unloadLevel($this->waitingLobby, true);
        if ($reload) {
            $this->plugin->loadGame($this->name);
        }
    }

    public function eliminatePlayer(Player $player, bool $left = false, $spectatorMode = false): bool
    {
        if (!$this->isPlaying($player)) {
            return false;
        }
        unset($this->players[$player->getName()]);
        if ($left) {
            $this->sendMessage("player.game.left", ["{player}"], [$player->getName()]);
        } else {
            $this->sendMessage("player.game.elimination", ["{player}"], [$player->getName()]);
        }
        if ($spectatorMode) {
            $this->setSpectator($player);
        } else {
            ScoreboardManager::removeDisplay($player);
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->removeAllEffects();
            $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());
            $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
        }
        return true;
    }
}