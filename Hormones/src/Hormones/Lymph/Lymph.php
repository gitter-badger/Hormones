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

namespace Hormones\Lymph;

use Hormones\HormonesPlugin;
use mysqli;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

/**
 * Used for exchanging tissue status with the Heart
 */
class Lymph extends AsyncTask{
	/** @var array */
	private $details;
	/** @var int */
	private $organFlag;
	/** @var string */
	private $serverID;
	/** @var int */
	private $maxPlayers, $playersCnt;
	public function __construct(HormonesPlugin $main){
		$this->details = $main->getMysqlDetails();
		$this->organFlag = 1 << $main->getOrgan();
		$this->serverID = $main->getServerID();
		$this->maxPlayers = $main->getMaxPlayerCount();
		$this->playersCnt = count($main->getServer()->getOnlinePlayers());
	}
	public function onRun(){
		$db = $this->getDb();
		$db->query("UPDATE tissues SET laston=unix_timestamp(), usedslots=$this->playersCnt WHERE id='{$db->escape_string($this->serverID)}'");
		$result = $db->query("SELECT usedslots, maxslots, organ, ip, port FROM tissues WHERE unix_timestamp()-laston < 5");
		$organTissues = 0;
		$organUsedSlots = 0;
		$organMaxSlots = 0;
		$totalTissues = 0;
		$totalUsedSlots = 0;
		$totalMaxSlots = 0;
		$altIp = null;
		$altPort = null;
		$maxAvailable = 0;
		while(is_array($row = $result->fetch_assoc())){
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
	/**
	 * @return mysqli
	 */
	public function getDb(){
		if(($db = $this->getFromThreadStore(HormonesPlugin::HORMONES_DB)) instanceof mysqli){
			return $db;
		}
		$db = HormonesPlugin::getMysqli($this->details);
		$this->saveToThreadStore(HormonesPlugin::HORMONES_DB, $db);
		return $db;
	}
	public function onCompletion(Server $server){
		$main = HormonesPlugin::getInstance($server);
		if($main !== null and $main->isEnabled()){
			/** @noinspection PhpInternalEntityUsedInspection */
			$main->refreshLymphResult($this->getResult());
		}
	}
}
