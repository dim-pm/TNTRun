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

namespace Dim\TNTRun\entity;

use pocketmine\block\Block;
use pocketmine\block\Fallable;
use pocketmine\entity\object\FallingBlock;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\nbt\tag\CompoundTag;

class FallingBlockEntity extends FallingBlock
{
    protected $ticksBeforeDespawn = 60;

    public function __construct(Level $level, Block $block, CompoundTag $nbt)
    {
        $this->block = $block;
        $nbt->setInt("TileID", $block->getId());
        parent::__construct($level, $nbt);
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->closed) {
            return false;
        }
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if (!$this->isFlaggedForDespawn()) {
            $pos = Position::fromObject($this->add(-$this->width / 2, $this->height, -$this->width / 2)->floor(), $this->getLevel());
            $this->block->position($pos);
            $blockTarget = null;
            if ($this->block instanceof Fallable) {
                $blockTarget = $this->block->tickFalling();
            }
            if ($this->onGround or $blockTarget !== null) {
                $this->flagForDespawn();
                $hasUpdate = true;
            }
        }
        if ($this->ticksBeforeDespawn > 0) {
            $this->ticksBeforeDespawn--;
        } else {
            $this->flagForDespawn();
        }
        return $hasUpdate;
    }
}