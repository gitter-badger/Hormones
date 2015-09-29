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

abstract class Hormone{
	/** @var int */
	private $receptors;
	/** @var int */
	private $creationTime;
	/** @var mixed */
	private $data;
	/** @var string[] */
	private $tags;
	/** @var int|null */
	private $id;
	public function __construct($receptors, $creationTime, $data, $tags = [], $id = null){
		$this->receptors = $receptors;
		$this->creationTime = $creationTime;
		$this->data = $data;
		$this->tags = $tags;
		$this->id = $id;
	}
	public static function getTypeName() : string{
		return (new \ReflectionClass(static::class))->getShortName();
	}
	/**
	 * @return int
	 */
	public function getReceptors(){
		return $this->receptors;
	}
	/**
	 * @return int
	 */
	public function getCreationTime(){
		return $this->creationTime;
	}
	/**
	 * @return mixed
	 */
	public function getData(){
		return $this->data;
	}
	/**
	 * @return \string[]
	 */
	public function getTags(){
		return $this->tags;
	}
	/**
	 * @return int|null
	 */
	public function getId(){
		return $this->id;
	}
	/**
	 * @param int|null $id
	 */
	public function setId($id){
		$this->id = $id;
	}
}
