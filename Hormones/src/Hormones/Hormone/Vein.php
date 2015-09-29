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

namespace Hormones\Hormone;

use Hormones\HormonesPlugin;
use Hormones\HormonesQueryAsyncTask;
use pocketmine\Server;

class Vein extends HormonesQueryAsyncTask{
	/** @var string */
	private $type;
	/** @var int */
	private $receptors;
	/** @var int */
	private $creation;
	/** @var string */
	private $tags;
	/** @var string */
	private $json;

	/** @var int<Hormone> */
	private $hormoneRef;

	/**
	 * @param HormonesPlugin $main
	 * @param Hormone $hormone
	 */
	public function __construct(HormonesPlugin $main, Hormone $hormone){
		parent::__construct($main->getMysqlDetails());
		$this->type = $hormone->getTypeName();
		$this->receptors = $hormone->getReceptors();
		$this->creation = $hormone->getCreationTime();
		$this->tags = "," . implode(", ", $hormone->getTags()) . ",";
		$this->json = json_encode($hormone->getData());
		$this->hormoneRef = $main->storeObject($hormone); // weak?
	}
	public function onRun(){
		$db = $this->getDb();
		$db->query("INSERT INTO blood (type, receptors, creation, tags, json) VALUES ({$this->escape($this->type)}, $this->receptors, $this->creation, {$this->escape($this->tags)}, {$this->escape($this->json)})");
		$this->setResult($db->insert_id, false);
	}
	public function onCompletion(Server $server){
		$main = HormonesPlugin::getInstance($server);
		if($main instanceof HormonesPlugin and $main->isEnabled()){
			try{
				$object = $main->fetchObject($this->hormoneRef);
				if($object instanceof Hormone){
					$object->setId($this->getResult());
				}
			}catch(\RuntimeException $e){
			}
		}
	}
}
