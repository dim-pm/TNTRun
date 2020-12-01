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

namespace Dim\TNTRun\listener;

use Dim\TNTRun\constant\GameConstants;
use Dim\TNTRun\Main;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class EventListener implements Listener
{
    /**
     * @var Main
     */
    protected $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        if ($this->plugin->isPlaying($player)) {
            $this->plugin->getPlayerGame($player)->eliminatePlayer($player, true, false);
        }
    }

    public function onDamage(EntityDamageEvent $event)
    {
        $entity = $event->getEntity();
        if ($entity instanceof Player and $this->plugin->isPlaying($entity)) {
            $game = $this->plugin->getPlayerGame($entity);
            if ($game->getState() !== GameConstants::GAME_RUNNING) {
                $event->setCancelled();
                return;
            }
            $event->setCancelled();
            if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
                $event->setCancelled();
                $this->plugin->getPlayerGame($entity)->eliminatePlayer($entity, false, true);
            }
        }
    }

    public function onHold(PlayerItemHeldEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        switch ($item->getName()) {
            case TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . "Back to Hub":
                $player->getInventory()->clearAll();
                $player->getArmorInventory()->clearAll();
                $player->removeAllEffects();
                $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());
                $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
                $player->sendMessage($this->getTranslation("player.hub"));
                break;
            case TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . "Play Again":
                $game = $this->plugin->findGame();
                if ($game !== null and $game->addPlayer($player)) {
                    return;
                }
                $player->sendMessage($this->getTranslation("games.notfound"));
                break;
            case TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . "Spectate":
                $game = $this->plugin->getGameFromWorld($player->getLevel()->getFolderName());
                if ($game !== null and count($game->getPlayers()) >= 1) {
                    $randomPlayer = $game->getPlayers()[array_rand($game->getPlayers(), 1)];
                    $player->teleport($randomPlayer->getPlayer());
                    $player->sendMessage($this->getTranslation("player.spectate", ["{player}"], [$randomPlayer->getPlayer()->getName()]));
                    return;
                }
                $player->sendMessage($this->getTranslation("spectate.noplayers"));
        }
    }

    public function getTranslation(string $message, array $replace = [], array $conversion = []): string
    {
        $msg = $this->plugin->getConfig()->getAll()[$message] ? TextFormat::colorize($this->plugin->getConfig()->getAll()[$message]) : "Translation not found.";
        return str_replace($replace, $conversion, $msg);
    }
}