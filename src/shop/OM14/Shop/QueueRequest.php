<?php

namespace OM14\Shop;

class QueueRequest extends Database {

	// TODO: QueueRequest and QueueResponse should maybe extend a common base class.

	protected $db;
	protected $id;
	protected $data;

	public function __construct(Database $db, $row = null) {
		$this->db = $db;
		if ($row !== null) {
			if (is_array($row)) {
				$this->setData($row['request']);
				$this->setID($row['id']);
			} else {
				throw new \InvalidArgumentException('$row has to be an array or null');
			}
		}
	}

	public function getID() {
		return $this->id;
	}

	public function setID($id) {
		if ($this->id !== null) {
			throw new \Exception('ID cannot be changed once set');
		}
		if (!is_int($id)) {
			throw new \InvalidArgumentException('ID needs to be an integer');
		}
		$this->id = $id;
	}

	public function setData($data) {
		if ($this->id !== null) {
			throw new \Exception('requests cannot be changed once persisted');
		}
		if (!is_array($data)) {
			throw new \InvalidArgumentException('data needs to be an array');
		}
		$this->data = $data;
		return $this;
	}

	public function getData() {
		if (!is_array($this->data)) {
			throw new \Exception('no data was set yet');
		}
		return $this->data;
	}

	public function persist() {
		if ($this->id !== null) {
			throw new \Exception('requests cannot be persisted twice');
		}
		return $this->db->persistQueueRequest($this);
	}

	public function sendAndFetchResponse() {
		$this->persist();
		$start = microtime(true); $stop = $start + 5;
		while (microtime(true) < $stop) {
			$response = $this->db->fetchQueueResponse($this->id);
			if ($response) {
				return $response;
			}
			usleep(250000);
		}
		throw new \Exception('no response received');
	}

	public function request() {
		$this->persist();
	}

} 
