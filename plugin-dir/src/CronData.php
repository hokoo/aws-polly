<?php

namespace iTRON\PollyTTS;

class CronData {
	public array $data = array();
	public string $task;
	public bool $unique;

	public function __construct( string $task, array $data = array(), bool $unique = false ) {
		$this->task   = $task;
		$this->data   = $data;
		$this->unique = $unique;
	}
}
