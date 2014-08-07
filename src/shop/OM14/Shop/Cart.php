<?php

namespace OM14\Shop;

use Symfony\Component\HttpFoundation\Request;

class Cart {

	protected static $limits = array(
		'Ticket' => 4,
	);

	protected $app;

	public function __construct(Application $app) {
		$this->app = $app;
	}

	protected function checkItemLimits(Item $newItem, $orderID) {
		$used = array();
		$contents = $this->getContents($orderID);
		foreach ($contents as $cartItem) {
			foreach (self::$limits as $limitClass => $limit) {
				if (is_subclass_of($cartItem, Item::fqClass($limitClass))) {
					if (!isset($used[$limitClass])) {
						$used[$limitClass] = 0;
					}
					$used[$limitClass]++;
				}
			}
		}
		foreach (self::$limits as $limitClass => $limit) {
			if (is_subclass_of($newItem, Item::fqClass($limitClass))
			 && isset($used[$limitClass])
			 && $used[$limitClass] >= $limit) {
				return false;
				// throw new \Exception("you cannot have more than $limit of these items in your cart");
			}
		}
		return true;
	}

	/**
	 * @param int|null $orderID
	 * @return Item[]
	 */
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

	public function getSum() {
		return array_reduce($this->getContents(), function ($carry, Item $item) {
			return $carry + $item->getPrice();
		}, 0);
	}

	public function getTimeLeft() {
		$orderID = $this->getOrderID();
		$order = $this->getDB()->getOrder($orderID);
		return max(0, 3600 - (microtime(true) - (float)$order['created']));
	}

	public function getOrderID() {
		$orderID = $this->app->getSession()->getOrderID();
		if ($orderID === null) {
			return $orderID;
		}
		// confirm with the DB
		$state = $this->getDB()->getOrderState($orderID);
		if ($state !== 'clicking') {
			$this->app->getSession()->setOrderID(null);
			return null;
		}
		return $orderID;
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
				'price' => $req->get('price'),
				'name' => $req->get('name'),
				'twitter' => $req->get('twitter'),
				'size' => $req->get('size'),
			),
		);
		$qreq = new QueueRequest($this->getDB());
		$qreq->setData($qreqData);
		$qres = $qreq->sendAndFetchResponse();
		$qresData = $qres->getData();
		if (isset($qresData['success']) && $qresData['success']) {
			$this->app->getSession()->addFlashMessage('ok', 'Zum Warenkorb hinzugefügt!'); // yes, this should rather be somewhere else
		} elseif (isset($qresData['msg'])) {
			$this->app->getSession()->addFlashMessage('error', $qresData['msg']);
		} else {
			$this->app->getSession()->addFlashMessage('error', 'Nicht mehr verfügbar (oder zu viele im Warenkorb)!');
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

	public function handleOrderRequest(Request $req) {
		$orderID = $this->getOrderID();
		if ($orderID === null) {
			return;
		}
		$items = $this->getContents($orderID);
		$data = array(
			'ordered' => microtime(true),
			'name'    => $req->get('name'),
			'street'  => $req->get('street'),
			'city'    => $req->get('city'),
			'mail'    => $req->get('mail'),
			'comment' => $req->get('comment'),
		);
		$hrid = $this->getDB()->placeOrder($orderID, $data);
		$this->app->getSession()->setOrderID(null);
		$this->sendOrderConfirmationMail($data, $hrid, $items);
		$this->app->getSession()->addFlashMessage('ok', 'Vielen Dank für deine Bestellung!');
	}

	public function sendOrderConfirmationMail($data, $hrid, $items) {
		$sum = 0;
		$text = "
		Hallo {$data['name']},

		vielen Dank für deine Anmeldung für die openmind #om14!

		Folgendes hast du bestellt:

		";
		foreach ($items as $item) {
			$sum += $item->getPrice();
			$text .= sprintf("%5d€  %s  (%s)\n", $item->getPrice(), $item->getTitle(),
				$item::getType() === 'SHIRT' ? $item->getSize() : $item->getName()
			);
		}
		$text .= "
		Bitte überweise den Gesamtbetrag von {$sum}€ innerhalb einer Woche auf unser Konto:

		    Piratenpartei Deutschland
		    IBAN DE52 4306 0967 7006 0279 03
		    GLS-Bank, BIC GENODEM1GLS
		    (Konto 7006027903, BLZ 43060967)
		    Verwendungszweck: openmind om14 {$hrid}

		Falls du diese Mail fälschlicherweise erhalten und gar nichts bestellt hast, musst du dich nicht weiter darum kümmern. Nicht bezahlte Tickets werden nach einer gewissen Zeit wieder storniert.

		Bei Fragen stehen wir dir unter info@openmind-konferenz.de gern zur Verfügung. Bitte gib unbedingt immer deine Bestell-ID an, sie lautet: $hrid

		Wir freuen uns auf deinen Besuch!

		     Dein openmind-Team
		";
		$text = trim(str_replace(array("\t", "\n"), array('', "\r\n"), $text)) . "\r\n";
		foreach (array('scy-om14-signup#scy.name', $data['mail']) as $to) {
			mail(str_replace('#', '@', $to), "openmind #om14: Deine Anmeldung $hrid", $text, array(
				'From: openmind #om14 <info@openmind-konferenz.de>',
				'Content-Type: text/plain; charset=UTF-8',
			));
		}
	}

}
