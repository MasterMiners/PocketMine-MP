<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\block;

use Ds\Deque;
use pocketmine\block\tile\Banner as TileBanner;
use pocketmine\block\utils\BannerPattern;
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\item\Banner as ItemBanner;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function assert;

abstract class BaseBanner extends Transparent{
	/** @var DyeColor */
	protected $baseColor;

	/**
	 * @var Deque|BannerPattern[]
	 * @phpstan-var Deque<BannerPattern>
	 */
	protected $patterns;

	public function __construct(BlockIdentifier $idInfo, string $name, ?BlockBreakInfo $breakInfo = null){
		parent::__construct($idInfo, $name, $breakInfo ?? new BlockBreakInfo(1.0, BlockToolType::AXE));
		$this->baseColor = DyeColor::BLACK();
		$this->patterns = new Deque();
	}

	public function __clone(){
		parent::__clone();
		//pattern objects are considered immutable, so they don't need to be copied
		$this->patterns = $this->patterns->copy();
	}

	public function readStateFromWorld() : void{
		parent::readStateFromWorld();
		$tile = $this->pos->getWorld()->getTile($this->pos);
		if($tile instanceof TileBanner){
			$this->baseColor = $tile->getBaseColor();
			$this->setPatterns($tile->getPatterns());
		}
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->pos->getWorld()->getTile($this->pos);
		assert($tile instanceof TileBanner);
		$tile->setBaseColor($this->baseColor);
		$tile->setPatterns($this->patterns);
	}

	public function isSolid() : bool{
		return false;
	}

	/**
	 * TODO: interface method? this is only the BASE colour...
	 */
	public function getColor() : DyeColor{
		return $this->baseColor;
	}

	/**
	 * @return Deque|BannerPattern[]
	 * @phpstan-return Deque<BannerPattern>
	 */
	public function getPatterns() : Deque{
		return $this->patterns;
	}

	/**
	 * @param Deque|BannerPattern[] $patterns
	 * @phpstan-param Deque<BannerPattern> $patterns
	 * @return $this
	 */
	public function setPatterns(Deque $patterns) : self{
		$checked = $patterns->filter(function($v) : bool{ return $v instanceof BannerPattern; });
		if($checked->count() !== $patterns->count()){
			throw new \TypeError("Deque must only contain " . BannerPattern::class . " objects");
		}
		$this->patterns = $checked;
		return $this;
	}

	/**
	 * @return AxisAlignedBB[]
	 */
	protected function recalculateCollisionBoxes() : array{
		return [];
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($item instanceof ItemBanner){
			$this->baseColor = $item->getColor();
			$this->setPatterns($item->getPatterns());
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	abstract protected function getSupportingFace() : int;

	public function onNearbyBlockChange() : void{
		if($this->getSide($this->getSupportingFace())->getId() === BlockLegacyIds::AIR){
			$this->pos->getWorld()->useBreakOn($this->pos);
		}
	}

	public function asItem() : Item{
		return ItemFactory::getInstance()->get(ItemIds::BANNER, DyeColorIdMap::getInstance()->toInvertedId($this->baseColor));
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		$drop = $this->asItem();
		if($drop instanceof ItemBanner and !$this->patterns->isEmpty()){
			$drop->setPatterns($this->patterns);
		}

		return [$drop];
	}

	public function getPickedItem(bool $addUserData = false) : Item{
		$result = $this->asItem();
		if($addUserData and $result instanceof ItemBanner and !$this->patterns->isEmpty()){
			$result->setPatterns($this->patterns);
		}
		return $result;
	}
}
