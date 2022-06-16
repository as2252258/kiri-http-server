<?php

namespace Kiri\Server;


/**
 *
 */
class Config
{

	public string $type;


	public string $host = '';


	public int $port = 0;


	public string $name = '';


	public int $mode = SWOOLE_SOCK_TCP;


	public array $settings = [];


	public array $events = [];

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	public function setType(string $type): void
	{
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getHost(): string
	{
		return $this->host;
	}

	/**
	 * @param string $host
	 */
	public function setHost(string $host): void
	{
		$this->host = $host;
	}

	/**
	 * @return int
	 */
	public function getPort(): int
	{
		return $this->port;
	}

	/**
	 * @param int $port
	 */
	public function setPort(int $port): void
	{
		$this->port = $port;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @return int
	 */
	public function getMode(): int
	{
		return $this->mode;
	}

	/**
	 * @param int $mode
	 */
	public function setMode(int $mode): void
	{
		$this->mode = $mode;
	}

	/**
	 * @return array
	 */
	public function getSettings(): array
	{
		return $this->settings;
	}

	/**
	 * @param array $settings
	 */
	public function setSettings(array $settings): void
	{
		$this->settings = $settings;
	}

	/**
	 * @return array
	 */
	public function getEvents(): array
	{
		return $this->events;
	}

	/**
	 * @param array $events
	 */
	public function setEvents(array $events): void
	{
		$this->events = $events;
	}

}
