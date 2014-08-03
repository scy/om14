<?php

namespace OM14\Shop\Item;

use OM14\Shop\Item;

abstract class Ticket extends Item {

	public function fillFromArray($data) {
		parent::fillFromArray($data);
	}

	public function getAsArray() {
		return array(
			'id' => $this->id,
			'type' => static::getType(),
			'title' => static::getTitle(),
		);
	}

} 
