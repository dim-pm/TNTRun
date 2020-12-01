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

namespace Dim\TNTRun\command;

use Dim\TNTRun\Main;
use Dim\TNTRun\util\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class TNTRunCommand extends Command implements PluginIdentifiableCommand
{
    /**
     * @var Main
     */
    protected $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("tntrun", "TNTRun commands", "/tntrun <author|join|quit>", []);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (1 > count($args)) {
            $this->sendUsage($sender);
            return false;
        }
        switch (strtolower($args[0])) {
            case "author":
                $sender->sendMessage(TextFormat::AQUA . str_repeat("=", 5) . TextFormat::WHITE . TextFormat::BOLD . " TNTRUN " . TextFormat::RESET . TextFormat::AQUA . str_repeat("=", 5));
                $sender->sendMessage(TextFormat::GREEN . "Author: " . TextFormat::WHITE . $this->plugin->getDescription()->getAuthors()[0]);
                $sender->sendMessage(TextFormat::GREEN . "GitHub: " . TextFormat::WHITE . "https://github.com/DimBis");
                $sender->sendMessage(TextFormat::GREEN . "License: " . TextFormat::WHITE . "https://github.com/dim-pm/TNTRun/LICENSE");
                $sender->sendMessage(TextFormat::GREEN . "Repository: " . TextFormat::WHITE . "https://github.com/dim-pm/TNTRun");
                $sender->sendMessage(TextFormat::GREEN . "Version: " . TextFormat::WHITE . $this->plugin->getDescription()->getVersion());
                $sender->sendMessage(TextFormat::GREEN . "Website: " . TextFormat::WHITE . $this->plugin->getDescription()->getWebsite());
                $sender->sendMessage(TextFormat::AQUA . str_repeat("=", 18));
                break;
            case "join":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(TextFormat::RED . "Run this command in-game.");
                    return false;
                }
                if (!$sender->hasPermission("tntrun.command.join")) {
                    return false;
                }
                $game = $this->plugin->findGame();
                if ($game === null) {
                    $sender->sendMessage(TextFormat::RED . "Couldn't find a free game, please retry later.");
                    return false;
                }
                $game->addPlayer($sender);
                break;
            case "quit":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(TextFormat::RED . "Run this command in-game.");
                    return false;
                }
                if (!$sender->hasPermission("tntrun.command.quit")) {
                    return false;
                }
                $game = $this->plugin->getPlayerGame($sender);
                if ($game === null) {
                    $sender->sendMessage(TextFormat::RED . "You're not in a game.");
                    return false;
                }
                $game->eliminatePlayer($sender);
                break;
            case "create":
                if (!$sender->hasPermission("tntrun.command.create")) {
                    return false;
                }
                if (count($args) < 8) {
                    $sender->sendMessage(TextFormat::RED . "Usage /tntrun create <name> <world> <lobby> <players> <countdown> <time> <restart>");
                    return false;
                }
                $name = $args[1];
                $world = $args[2];
                $lobby = $args[3];
                $players = $args[4];
                $countdown = $args[5];
                $time = $args[6];
                $restart = $args[7];
                if (is_dir($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name)) {
                    $sender->sendMessage(TextFormat::RED . "Couldn't create the arena, An arena already exists with that name.");
                    return false;
                }
                @mkdir($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name);
                $config = new Config($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . "game.json", Config::JSON);
                $config->setAll(["name" => $name, "world" => $world, "lobby" => $lobby, "players" => $players, "countdown" => $countdown, "time" => $time, "restart" => $restart]);
                $config->save();
                $this->plugin->loadGame($name);
                $sender->sendMessage(TextFormat::GREEN . "Successfully created arena " . $name . ". " . TextFormat::WHITE . "name" . $name . " world: " . $world . " lobby:" . $lobby . " players:" . $players . " countdown: " . $countdown . " time: " . $time . " restart: " . $restart);
                break;
            case "delete":
                if (!$sender->hasPermission("tntrun.command.delete")) {
                    return false;
                }
                if (count($args) < 2) {
                    $sender->sendMessage(TextFormat::RED . "Usage /tntrun create <name> <world> <lobby> <players> <countdown> <time> <restart>");
                    return false;
                }
                $name = $args[1];
                if (!is_dir($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name)) {
                    $sender->sendMessage(TextFormat::RED . "Couldn't delete the arena, That arena does not exist.");
                    return false;
                }
                $this->plugin->unloadGame($name, true);
                Utils::recursiveDelete($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name);
                $sender->sendMessage(TextFormat::RED . "Successfully deleted arena " . $name . ".");
                break;
            default:
                $this->sendUsage($sender);
        }
        return true;
    }

    protected function sendUsage(CommandSender $sender): void
    {
        if ($sender->isOp()) {
            $sender->sendMessage(TextFormat::RED . "Usage /tntrun <author|create|delete|join|quit>");
        } else {
            $sender->sendMessage(TextFormat::RED . $this->getUsage());
        }
    }

    /**
     * @return Main
     */
    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }
}