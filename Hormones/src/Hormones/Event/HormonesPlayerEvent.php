<?php

/*
 * Hormone
 *
 * Copyright (C) 2015 LegendsOfMCPE and contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PEMapModder
 */

namespace Hormones\Event;

use Hormones\HormonesPlugin;
use pocketmine\Player;

class HormonesPlayerEvent extends HormonesEvent{
	/** @var Player */
	private $player;
	public function __construct(HormonesPlugin $hormones, Player $player){
		parent::__construct($hormones);
		$this->player = $player;
	}
	public function getPlayer(){
		return $this->player;
	}
}
