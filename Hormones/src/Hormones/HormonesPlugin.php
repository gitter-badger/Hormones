<?php

/*
 * Hormones
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

namespace Hormones;

use mysqli;
use Phar;
use pocketmine\plugin\PluginBase;

class HormonesPlugin extends PluginBase{
	private $mysqlDetails;
	/** @var int */
	private $organ;
	/** @var string */
	private $organName;
	/** @var string */
	private $serverID;
	public function onLoad(){
		if(!is_file($this->getDataFolder() . "config.yml")){
			$this->getLogger()->warning("You are strongly recommended to run the phar file " . Phar::running(false) . " to configure Hormones.");
			$this->getLogger()->warning("You can do so by running this in your COMMAND TERMINAL (not the PocketMine console!): `" . PHP_BINARY . " " . Phar::running(false) . "`");
		}
	}
	public function onEnable(){
		$this->getLogger()->debug("Loading config...");
		$this->saveDefaultConfig();
		$this->mysqlDetails = $this->getConfig()->get("mysql", [
			"hostname" => "127.0.0.1",
			"username" => "root",
			"password" => "",
			"schema" => "hormones",
		]);
		$this->getLogger()->debug("Testing Heart connection...");
		/** @noinspection PhpUsageOfSilenceOperatorInspection */
		$conn = @new mysqli(
			isset($this->mysqlDetails["hostname"]) ? $this->mysqlDetails["hostname"] : "127.0.0.1",
			isset($this->mysqlDetails["hostname"]) ? $this->mysqlDetails["username"] : "root",
			isset($this->mysqlDetails["hostname"]) ? $this->mysqlDetails["password"] : "",
			isset($this->mysqlDetails["hostname"]) ? $this->mysqlDetails["schema"] : "hormones",
			isset($this->mysqlDetails["hostname"]) ? $this->mysqlDetails["port"] : 3306
		);
		if($conn->connect_error){
			$this->getLogger()->critical("Could not connect to MySQL database: " . $conn->connect_error);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->getLogger()->debug("Building Heart...");
		$res = $this->getResource("dbInit.sql");
		$conn->query(stream_get_contents($res));
		fclose($res);
		if($conn->error){
			$this->getLogger()->critical("Failed to prepare Heart: " . $conn->error);
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
		$organ = $this->getConfig()->getNested("localize.organ");
		if(is_string($organ)){
			$organName = $organ;
			unset($organ);
			$this->getLogger()->notice("Converting organ name '$organName' into organ ID...");
			$result = $conn->query("SELECT flag FROM organs WHERE name='{$conn->escape_string($organName)}'");
			$row = $result->fetch_assoc();
			$result->close();
			if(is_array($row)){
				$organ = (int) $row["flag"];
			}else{
				$this->getLogger()->notice("Registering new organ type: '$organName'");
				$conn->query("INSERT INTO organs (name) VALUES ('{$conn->escape_string($organName)}')");
				$organ = $conn->insert_id;
			}
		}elseif(is_int($organ)){
			$result = $conn->query("SELECT name FROM organs WHERE flag=$organ");
			$row = $result->fetch_assoc();
			if(is_array($row)){
				$organName = $row["name"];
			}else{
				$this->getLogger()->critical("Fatal: Unregistered organ ID $organ");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				return;
			}
		}else{
			$this->getLogger()->critical("Fatal: Illegal organ type " . gettype($organ));
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->getLogger()->info("Starting tissue " . ($this->serverID = $this->getServer()->getServerUniqueId()) . " of organ '$organName' (#$organ)...");
		$this->organ = $organ;
		$this->organName = $organName;
		$playerCnt = count($this->getServer()->getOnlinePlayers());
		$conn->query("INSERT INTO tissues (id, organ, laston, usedslots, maxslots) VALUES ('{$conn->escape_string($this->serverID)}', 1 << $this->organ, unix_timestamp(), $playerCnt, $this->maxPlayerCnt)");
	}
}