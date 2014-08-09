<?php

namespace OM14\Shop;

class QueueHandler {

	protected $app;
	protected $db;

	public function __construct(Application $app) {
		$this->app = $app;
		$this->db = $app->getDB();
		$this->db->acquireLock($app->getConfig('mysql/lock'));
	}

	public function handleNext() {
		$req = $this->db->fetchQueueRequest();
		if ($req === false) {
			// Nothing to do.
			return false;
		}
		$res = new QueueResponse($this->db, $req->getID());
		$reqData = $req->getData();
		$cmd = (isset($reqData['cmd']) && is_string($reqData['cmd'])) ? $reqData['cmd'] : null;
		if ($cmd === null) {
			$res->setData(array(
				'success' => false,
				'msg' => 'no command provided'
			));
		} else {
			try {
				switch ($cmd) {
					case 'addItem':
						$res->setData($this->addItem($reqData['order'], $reqData['item']));
						break;
					default:
						$res->setData(array(
							'success' => false,
							'msg' => 'unknown command: ' . $cmd,
						));
				}
			} catch (\Exception $e) {
				$res->setData(array(
					'success' => false,
					'msg' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				));
			}
		}
		$res->persist();
		return $res;
	}

	protected function addItem($orderID, $itemData) {
		$item = Item::createFromArray($itemData);
		$cart = new Cart($this->app);
		$itemID = $cart->addItem($item, $orderID);
		return array(
			'success' => (bool)$itemID,
			'itemID' => $itemID,
		);
	}

}
