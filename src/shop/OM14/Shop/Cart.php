<?php

namespace OM14\Shop;

use Symfony\Component\HttpFoundation\Request;

class Cart {

	protected $app;

	public function __construct(Application $app) {
		$this->app = $app;
	}

	protected function checkItemLimits(Item $item, $orderID) {
		// TODO: Implement.
		return true;
	}

	public function getContents($orderID = null) {
		if ($orderID === null) {
			// FIXME: Actually, manually specifying an order ID is for debugging only and should be removed.
			$orderID = $this->getOrderID();
		}
		if ($orderID === null) {
			return array();
		}
		$contentArray = $this->getDB()->getCartContents($orderID);
		$items = array();
		foreach ($contentArray as $content) {
			$items[] = Item::createFromArray($content);
		}
		return $items;
	}

	public function getContentsAsArray() {
		return array_map(function (Item $item) {
			return $item->getAsArray();
		}, $this->getContents());
	}

	public function getOrderID() {
		return $this->app->getSession()->getOrderID();
	}

	public function getDB() {
		return $this->app->getDB();
	}

	public function createOrder() {
		$order = $this->getOrderID();
		if ($order !== null) {
			return $order;
		}
		$order = $this->getDB()->createOrder();
		$this->app->getSession()->setOrderID($order);
		return $order;
	}

	public function handleAddRequest(Request $req) {
		$orderID = $this->createOrder();
		$qreqData = array(
			'cmd' => 'addItem',
			'order' => $orderID,
			'item' => array(
				'type' => $req->get('type'),
			),
		);
		$qreq = new QueueRequest($this->getDB());
		$qreq->setData($qreqData);
		$qres = $qreq->sendAndFetchResponse();
		$qresData = $qres->getData();
		if (isset($qresData['success']) && isset($qresData['msg'])) {
			$this->app->getSession()->addFlashMessage('error', $qresData['msg']);
		} else {
			$this->app->getSession()->addFlashMessage('ok', 'Zum Warenkorb hinzugefügt!'); // yes, this should rather be somewhere else
		}
	}

	public function addItem(Item $item, $orderID) {
		if (!$item->isAvailable($this->getDB(), false)) {
			return null;
		}
		if (!$this->checkItemLimits($item, $orderID)) {
			return null;
		}
		return $this->getDB()->insertItem($orderID, $item);
	}

	public function handleRemoveRequest(Request $req) {
		$orderID = $this->getOrderID();
		if ($orderID === null) {
			return;
		}
		$itemID = (int)$req->get('id');
		$this->removeItem($itemID, $orderID);
	}

	public function removeItem($itemID, $orderID) {
		$this->getDB()->removeItem($orderID, $itemID);
	}

}
