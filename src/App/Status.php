<?php

namespace Fawaz\App;

final class Status {

	const NORMAL = 0;
	const SUSPENDED = 1;
	const ARCHIVED = 2;
	const BANNED = 3;
	const LOCKED = 4;
	const PENDING_REVIEW = 5;

	public static function getMap() : array
	{
		$reflectionClass = new \ReflectionClass(static::class);
		return \array_flip($reflectionClass->getConstants());
	}

	public static function getNames() : array 
	{
		$reflectionClass = new \ReflectionClass(static::class);
		return \array_keys($reflectionClass->getConstants());
	}

	public static function getValues() : array 
	{
		$reflectionClass = new \ReflectionClass(static::class);
		return \array_values($reflectionClass->getConstants());
	}

	private function __construct() {}
}
