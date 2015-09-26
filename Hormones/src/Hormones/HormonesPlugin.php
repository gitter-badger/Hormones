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

use Hormones\Lymph\LymphResult;
use Hormones\Lymph\LymphVessel;
use mysqli;
use Phar;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class HormonesPlugin extends PluginBase{
	const HORMONES_DB = "hormones.db";
	/** @var array */
	private $mysqlDetails;
	/** @var int */
	private $organ;
	/** @var string */
	private $organName;
	/** @var string */
	private $serverID;
	/** @var int */
	private $maxPlayerCnt;
	/** @var LymphResult */
	private $lymphResult;
	/**
	 * @internal
	 */
	public function onLoad(){
		if(!is_file($this->getDataFolder() . "config.yml")){
			$this->getLogger()->warning("You are strongly recommended to run the phar file " . Phar::running(false) . " to configure Hormones.");
			$this->getLogger()->warning("You can do so by running this in your COMMAND TERMINAL (not the PocketMine console!): `" . PHP_BINARY . " " . Phar::running(false) . "`");
		}
	}
	/**
	 * @internal
	 */
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
		$conn = @self::getMysqli($this->mysqlDetails);
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
		$this->maxPlayerCnt = (int) $this->getConfig()->getNested("localize.maxPlayers", 20);
		$playerCnt = count($this->getServer()->getOnlinePlayers());
		$conn->query("INSERT INTO tissues (id, organ, laston, usedslots, maxslots) VALUES ('{$conn->escape_string($this->serverID)}', 1 << $this->organ, unix_timestamp(), $playerCnt, $this->maxPlayerCnt)");

		$this->getServer()->getScheduler()->scheduleRepeatingTask(new LymphVessel($this), 1);

		$this->getLogger()->info("Startup completed.");
	}
	/**
	 * @internal
	 */
	public function onDisable(){
		if(isset($this->mysqlDetails)){
			/** @noinspection PhpUsageOfSilenceOperatorInspection */
			$db = @self::getMysqli($this->mysqlDetails);
			$db->query("UPDATE tissues SET laston=0 WHERE id='{$this->getServer()->getServerUniqueId()}'");
			$db->close();
		}
	}
	/**
	 * @param array $mysqlDetails
	 * @return mysqli
	 */
	public static function getMysqli(array $mysqlDetails){
		return new mysqli(
			isset($mysqlDetails["hostname"]) ? $mysqlDetails["hostname"] : "127.0.0.1",
			isset($mysqlDetails["hostname"]) ? $mysqlDetails["username"] : "root",
			isset($mysqlDetails["hostname"]) ? $mysqlDetails["password"] : "",
			isset($mysqlDetails["hostname"]) ? $mysqlDetails["schema"] : "hormones",
			isset($mysqlDetails["hostname"]) ? $mysqlDetails["port"] : 3306
		);
	}
	/**
	 * @return array
	 */
	public function getMysqlDetails(){
		return $this->mysqlDetails;
	}
	/**
	 * @return int
	 */
	public function getOrgan(){
		return $this->organ;
	}
	/**
	 * @return string
	 */
	public function getOrganName(){
		return $this->organName;
	}
	/**
	 * @return int
	 */
	public function getMaxPlayerCount(){
		return $this->maxPlayerCnt;
	}
	/**
	 * @return string
	 */
	public function getServerID(){
		return $this->serverID;
	}
	public function getLastLymphResult(){
		return $this->lymphResult;
	}
	/**
	 * @param LymphResult $result
	 * @internal
	 */
	public function refreshLymphResult(LymphResult $result){
		$this->lymphResult = $result;
	}
	/**
	 * @param Server $server
	 * @return HormonesPlugin|null
	 */
	public static function getInstance(Server $server){
		return $server->getPluginManager()->getPlugin("Hormones");
	}
}
