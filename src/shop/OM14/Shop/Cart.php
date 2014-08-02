<?php

namespace OM14\Shop;

class Cart {

	protected $app;
	protected $orderID;

	public function __construct(Application $app) {
		$this->app = $app;
	}

	public function getContents($orderID = null) {
		if ($orderID === null) {
			// FIXME: Actually, manually specifying an order ID is for debugging only and should be removed.
			$orderID = $this->app->getSession()->getOrderID();
		}
		if ($orderID === null) {
			return array();
		}
		$contentArray = $this->app->getDB()->getCartContents($orderID);
		$items = array();
		foreach ($contentArray as $content) {
			$items[] = Item::createFromArray($content);
		}
		return $items;
	}

} 
