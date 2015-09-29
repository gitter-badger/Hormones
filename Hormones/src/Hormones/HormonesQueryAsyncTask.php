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

namespace Hormones;

use mysqli;
use pocketmine\scheduler\AsyncTask;

abstract class HormonesQueryAsyncTask extends AsyncTask{
	private $details;
	public function __construct(array $mysqlDetails){
		$this->details = $mysqlDetails;
	}
	/**
	 * @return mysqli
	 */
	public function getDb() : mysqli{
		if(($db = $this->getFromThreadStore(HormonesPlugin::HORMONES_DB)) instanceof mysqli){
			return $db;
		}
		$db = HormonesPlugin::getMysqli($this->details);
		$this->saveToThreadStore(HormonesPlugin::HORMONES_DB, $db);
		return $db;
	}
	protected function escape(string $string) : string{
		return "'{$this->getDb()->escape_string($string)}'";
	}
}
