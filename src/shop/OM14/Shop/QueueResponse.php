<?php

namespace OM14\Shop;

class QueueResponse extends Database {

	// TODO: QueueRequest and QueueResponse should maybe extend a common base class.

	protected $db;
	protected $id;
	protected $time;
	protected $data;

	public function __construct(Database $db, $id, $row = null) {
		$this->db = $db;
		$this->setID($id);
		if ($row !== null) {
			if (is_array($row)) {
				$this->setData($row['response']);
				$this->setTime($row['responded']);
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

	public function getTime() {
		return $this->time;
	}

	public function setTime($time) {
		if ($this->time !== null) {
			throw new \Exception('time cannot be changed once set');
		}
		if (!is_float($time)) {
			throw new \InvalidArgumentException('time needs to be a float');
		}
		$this->time = $time;
	}

	public function setData($data) {
		if ($this->time !== null) {
			throw new \Exception('responses cannot be changed once persisted');
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
		if ($this->time !== null) {
			throw new \Exception('responses cannot be persisted twice');
		}
		return $this->db->persistQueueResponse($this);
	}

} 
