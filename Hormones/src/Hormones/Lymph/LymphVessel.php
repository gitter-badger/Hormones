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
 * @author LegendsOfMCPE
 */

namespace Hormones\Lymph;

use Hormones\HormonesPlugin;
use pocketmine\scheduler\PluginTask;

/**
 * Responsible for creating new Lymph to the heart
 */
class LymphVessel extends PluginTask{
	/** @var Lymph */
	private $lastLymph = null;
	public function onRun($currentTick){
		if($this->lastLymph !== null and !$this->lastLymph->queryFinished){
			return;
		}
		/** @var HormonesPlugin $owner */ // ASSERTION!!!
		$owner = $this->getOwner();
		$owner->getServer()->getScheduler()->scheduleAsyncTask($this->lastLymph = new Lymph($owner));
	}
}
