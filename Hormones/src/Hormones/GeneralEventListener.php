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

namespace Hormones;

use Hormones\Event\ServerOverloadedTransferEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;

class GeneralEventListener implements Listener{
	/** @var HormonesPlugin */
	private $main;
	public function __construct(HormonesPlugin $main){
		$this->main = $main;
	}
	/**
	 * @param PlayerPreLoginEvent $event
	 * @priority LOW
	 * @ignoreCancelled true
	 */
	public function onPreLogin(PlayerPreLoginEvent $event){
		if(count($this->getMain()->getServer()->getOnlinePlayers()) >= $this->getMain()->getMaxPlayerCount()){
			$result = $this->getMain()->getLastLymphResult();
			$transfer = new ServerOverloadedTransferEvent($this->getMain(), $event->getPlayer(), $result->altIp, $result->altPort);
			if($transfer->getAltIp() === null){
				$transfer->setTransfer(false);
			}
			$this->getMain()->getServer()->getPluginManager()->callEvent($transfer);
			if(!$transfer->isCancelled()){
				$event->setCancelled();
				$event->setKickMessage($transfer->getMessage());
				if($transfer->isTransfer()){
					$this->getMain()->transferPlayer($transfer->getPlayer(), $transfer->getAltIp(), $transfer->getAltPort(), $transfer->getMessage());
				}
			}
		}
	}
	/**
	 * @return HormonesPlugin
	 */
	public function getMain(){
		return $this->main;
	}
}
