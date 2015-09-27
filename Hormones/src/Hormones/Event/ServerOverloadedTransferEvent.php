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

namespace Hormones\Event;

use Hormones\HormonesPlugin;
use pocketmine\event\Cancellable;
use pocketmine\Player;

class ServerOverloadedTransferEvent extends HormonesPlayerEvent implements Cancellable{
	public static $handlerList = null;

	/** @var bool */
	private $transfer = true;
	/** @var string|null */
	private $message = "Server overloaded";
	/** @var string|null */
	private $altIp;
	/** @var int|null */
	private $altPort;

	public function __construct(HormonesPlugin $hormones, Player $player, $altIp, $altPort){
		parent::__construct($hormones, $player);
		$this->altIp = $altIp;
		$this->altPort = $altPort;
	}

	/**
	 * @return null|string
	 */
	public function getAltIp(){
		return $this->altIp;
	}
	/**
	 * @param null|string $altIp
	 */
	public function setAltIp($altIp){
		$this->altIp = $altIp;
	}
	/**
	 * @return int|null
	 */
	public function getAltPort(){
		return $this->altPort;
	}
	/**
	 * @param int|null $altPort
	 */
	public function setAltPort($altPort){
		$this->altPort = $altPort;
	}
	/**
	 * @return null|string
	 */
	public function getMessage(){
		return $this->message;
	}
	/**
	 * @param null|string $message
	 */
	public function setMessage($message){
		$this->message = $message;
	}
	/**
	 * @return boolean
	 */
	public function isTransfer(){
		return $this->transfer;
	}
	/**
	 * @param boolean $transfer
	 */
	public function setTransfer($transfer){
		$this->transfer = $transfer;
	}

}
