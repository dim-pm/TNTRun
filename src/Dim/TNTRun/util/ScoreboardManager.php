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

namespace Dim\TNTRun\util;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;

class ScoreboardManager
{
    /**
     * @var int[]
     */
    protected static $sessions = [];

    public static function createDisplay(Player $player, string $title): void
    {
        $packet = new SetDisplayObjectivePacket();
        $packet->objectiveName = $player->getName();
        $packet->displayName = $title;
        $packet->displaySlot = "sidebar";
        $packet->criteriaName = "dummy";
        $packet->sortOrder = 0;
        $player->sendDataPacket($packet);
        self::$sessions[$player->getName()] = 1;
    }

    public static function sendLine(Player $player, string $text, int $line): void
    {
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $player->getName();
        $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entry->customName = $text;
        $entry->score = $line;
        $entry->scoreboardId = $line;
        $packet = new SetScorePacket();
        $packet->type = $packet::TYPE_CHANGE;
        $packet->entries[] = $entry;
        $player->sendDataPacket($packet);
    }

    public static function removeDisplay(Player $player): void
    {
        if (isset(self::$sessions[$player->getName()])) {
            $packet = new RemoveObjectivePacket();
            $packet->objectiveName = $player->getName();
            $player->sendDataPacket($packet);
            unset(self::$sessions[$player->getName()]);
        }
    }
}