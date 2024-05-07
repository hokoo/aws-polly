<?php

class AmazonAI_CronData {
	public array $data = [];
	public string $task;
	public bool $unique;

	public function __construct( string $task, array $data = [], bool $unique = false ) {
		$this->task   = $task;
		$this->data   = $data;
		$this->unique = $unique;
	}
}
