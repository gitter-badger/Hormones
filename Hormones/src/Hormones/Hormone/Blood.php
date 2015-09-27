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

class Blood extends HormonesQueryAsyncTask{
	public $queryFinished = false;
	private $shiftedOrgan;
	public function __construct(HormonesPlugin $main){
		parent::__construct($main->getMysqlDetails());
		$this->shiftedOrgan = 1 << $main->getOrgan();
	}
	public function onRun(){
		$db = $this->getDb();
		$mResult = $db->query("SELECT id,type,receptors,creation,json FROM blood WHERE (receptors & $this->shiftedOrgan) = $this->shiftedOrgan");
		$output = [];
		while(is_array($row = $mResult->fetch_assoc())){
			$output[] = $row;
		}
		$mResult->close();
		$this->queryFinished = true;
		$this->setResult($output);
	}
	public function onCompletion(Server $server){
		// TODO execute hormones
	}
}
