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
use Hormones\HormonesQueryAsyncTask;
use pocketmine\Server;

/**
 * Used for exchanging tissue status with the Heart
 */
class Lymph extends HormonesQueryAsyncTask{
	public $queryFinished = false;
	/** @var int */
	private $organFlag;
	/** @var string */
	private $serverID;
	/** @var int */
	private $maxPlayers, $playersCnt;
	public function __construct(HormonesPlugin $main){
		parent::__construct($main->getMysqlDetails());
		$this->organFlag = 1 << $main->getOrgan();
		$this->serverID = $main->getServerID();
		$this->maxPlayers = $main->getMaxPlayerCount();
		$this->playersCnt = count($main->getServer()->getOnlinePlayers());
	}
	public function onRun(){
		$db = $this->getDb();
		$db->query("UPDATE tissues SET laston=unix_timestamp(), usedslots=$this->playersCnt WHERE id='{$db->escape_string($this->serverID)}'");
		$mResult = $db->query("SELECT usedslots, maxslots, organ, ip, port FROM tissues WHERE unix_timestamp()-laston < 5");
		$organTissues = 0;
		$organUsedSlots = 0;
		$organMaxSlots = 0;
		$totalTissues = 0;
		$totalUsedSlots = 0;
		$totalMaxSlots = 0;
		$altIp = null;
		$altPort = null;
		$maxAvailable = 0;
		while(is_array($row = $mResult->fetch_assoc())){
			$totalTissues++;
			$totalUsedSlots += (int) $row["usedslots"];
			$totalMaxSlots += (int) $row["maxslots"];
			if(((int) $row["organ"]) === $this->organFlag){
				$organTissues++;
				$organUsedSlots += (int) $row["usedslots"];
				$organMaxSlots += (int) $row["maxslots"];
				$available = ((int) $row["maxslots"]) - ((int) $row["usedslots"]);
				if($available >= $maxAvailable){
					$maxAvailable = $available;
					$altIp = $row["ip"];
					$altPort = (int) $row["port"];
				}
			}
		}
		$mResult->close();
		$this->queryFinished = true;
		$result = new LymphResult;
		$result->organTissues = $organTissues;
		$result->organUsedSlots = $organUsedSlots;
		$result->organMaxSlots = $organMaxSlots;
		$result->totalTissues = $totalTissues;
		$result->totalUsedSlots = $totalUsedSlots;
		$result->totalMaxSlots = $totalMaxSlots;
		$result->altIp = $altIp;
		$result->altPort = $altPort;
		$this->setResult($result);
	}
	public function onCompletion(Server $server){
		$main = HormonesPlugin::getInstance($server);
		if($main !== null and $main->isEnabled()){
			/** @noinspection PhpInternalEntityUsedInspection */
			$main->refreshLymphResult($this->getResult());
		}
	}
}
