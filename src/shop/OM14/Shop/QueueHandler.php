<?php

namespace OM14\Shop;

class QueueHandler {

	const LOCK_NAME = 'OM14_QueueHandler';

	protected $app;
	protected $db;

	public function __construct(Application $app) {
		$this->app = $app;
		$this->db = $app->getDB();
		$this->db->acquireLock(self::LOCK_NAME);
	}

	public function handleNext() {
		$req = $this->db->fetchQueueRequest();
		if ($req === false) {
			// Nothing to do.
			return false;
		}
		$res = new QueueResponse($this->db, $req->getID());
		$res->setData(array('echo' => $req->getData())); // TODO: Replace with something useful.
		$res->persist();
		return $res;
	}

}
