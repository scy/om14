<?php

namespace OM14\Shop;

use Silex\Provider\DoctrineServiceProvider;

class Database {

	const TABLE_QUEUE  = 'queue';
	const TABLE_ORDERS = 'orders';
	const TABLE_ITEMS  = 'items';
	const TABLE_ERRORS = 'errors';

	const VIEW_ITEMS = 'items_v';
	const VIEW_TAKEN = 'taken_v';

	const HRID_CHARS = 'BCDFGHJKLMNPQRSTVWXYZ';
	const HRID_TRIES = 25;

	protected $app;

	/**
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $db;

	public function __construct(Application $app) {
		$this->app = $app;
		$silex = $app->getSilexApplication();
		$silex->register(new DoctrineServiceProvider(), array(
			'db.options'   => array(
				'driver'   => 'pdo_mysql',
				'host'     => $app->getConfig('mysql/host'),
				'dbname'   => $app->getConfig('mysql/db'),
				'user'     => $app->getConfig('mysql/user'),
				'password' => $app->getConfig('mysql/pass'),
				'charset'  => 'utf8',
			)
		));
		$this->db = $silex['db'];
	}

	protected function generateHRID($length = 6) {
		$hrid = '';
		$numchars = strlen(self::HRID_CHARS);
		for ($i = $length; $i--;) {
			$hrid .= substr(self::HRID_CHARS, mt_rand(0, $numchars), 1);
		}
		return $hrid;
	}

	protected function getQueryBuilder() {
		return $this->db->createQueryBuilder();
	}

	protected function insertAndGetID($table, $data) {
		$result = $this->db->insert($table, $data);
		if ($result !== 1) {
			throw new \Exception('wrong number of inserted rows: ' . $result);
		}
		return (int)$this->db->lastInsertId();
	}

	protected function fetchOne($statement, $params = array()) {
		$res = $this->db->executeQuery($statement, $params);
		$res->setFetchMode(\PDO::FETCH_ASSOC);
		$row = $res->fetch();
		$anotherRow = $res->fetch();
		if ($anotherRow !== false) {
			throw new \Exception('more than one row returned');
		}
		return $row;
	}

	protected function fetchAll($statement, $params = array()) {
		$res = $this->db->executeQuery($statement, $params);
		$res->setFetchMode(\PDO::FETCH_ASSOC);
		return $res->fetchAll();
	}

	protected function delete($table, $where) {
		return $this->db->delete($table, $where);
	}

	public function acquireLock($name) {
		$res = $this->fetchOne("SELECT GET_LOCK('$name', 0) ok"); // FIXME SQLI
		if ((int)$res['ok'] !== 1) {
			throw new \Exception('could not acquire lock');
		}
		return true;
	}

	public function createQueueRequest() {
		return new QueueRequest($this);
	}

	protected function persistQueueRequest(QueueRequest $req) {
		$id = $req->getID();
		if ($id !== null) {
			throw new \Exception('requests cannot be updated');
		}
		$now = microtime(true);
		$id = $this->insertAndGetID(self::TABLE_QUEUE, array(
			'requested' => $now,
			'request' => json_encode($req->getData()),
		));
		$req->setID($id);
		return $id;
	}

	protected function persistQueueResponse(QueueResponse $res) {
		$id = $res->getID();
		$time = $res->getTime();
		if ($time !== null) {
			throw new \Exception('responses cannot be updated');
		}
		$now = microtime(true);
		$this->db->update(self::TABLE_QUEUE,
			array('responded' => $now, 'response' => json_encode($res->getData())),
			array('id' => $id)
		);
		$res->setTime($now);
		return $id;
	}

	protected function fetchQueueItem($id = null) {
		if ($id !== null && !is_int($id)) {
			throw new \InvalidArgumentException('id has to be null or an integer');
		}
		$qb = $this->getQueryBuilder();
		$qb->select('q.id', 'q.requested', 'q.request', 'q.responded', 'q.response')
			->from(self::TABLE_QUEUE, 'q');
		if ($id === null) {
			$qb->where('q.responded IS NULL');
			$qb->orderBy('q.requested', 'ASC');
			$qb->setMaxResults(1);
		} else {
			$qb->where('q.id = ' . (int)$id); // binding parameters seems not to work with getSQL()
		}
		$row = $this->fetchOne($qb->getSQL());
		if (!is_array($row)) {
			return false;
		}
		foreach ($row as $col => $val) {
			switch ($col) {
				case 'id':
					$row[$col] = (int)$val;
					break;
				case 'requested':
				case 'responded':
					if ($val !== null) {
						$row[$col] = (float)$val;
					}
					break;
				case 'request':
				case 'response':
					if ($val !== null) {
						$row[$col] = json_decode($val, true);
					}
			}
		}
		return $row;
	}

	public function fetchQueueRequest($id = null) {
		$row = $this->fetchQueueItem($id);
		return $row ? new QueueRequest($this, $row) : false;
	}

	public function fetchQueueResponse($id) {
		$row = $this->fetchQueueItem($id);
		return isset($row['responded']) ? new QueueResponse($this, $id, $row) : false;
	}

	public function getTakenTicketCount() {
		$rows = $this->fetchAll('
			  SELECT `type`, COUNT(*) count
			    FROM ' . self::VIEW_TAKEN . '
			GROUP BY `type`
		');
		$ret = array();
		foreach ($rows as $row) {
			$ret[$row['type']] = (int)$row['count'];
		}
		return $ret;
	}

	public function getTakenQuota($useCache = true) {
		static $cache = null;
		if ($useCache && $cache !== null) {
			return $cache;
		}
		$mapping = Item::getQuotaMapping();
		$taken = array();
		foreach ($mapping as $type => $quotas) {
			foreach ($quotas as $quota) {
				$taken[$quota] = 0;
			}
		}
		$ticketcounts = $this->getTakenTicketCount();
		foreach ($ticketcounts as $type => $count) {
			$quotas = $mapping[$type];
			foreach ($quotas as $quota) {
				$taken[$quota] += $count;
			}
		}
		$cache = $taken;
		return $taken;
	}

	public function getAvailableQuota($useCache = true) {
		static $cache = null;
		if ($useCache && $cache !== null) {
			return $cache;
		}
		$taken = $this->getTakenQuota($useCache);
		$left = array();
		foreach (Item::getQuotaLimits() as $quota => $max) {
			if (array_key_exists($quota, $taken)) {
				$left[$quota] = $max - $taken[$quota];
			}
		}
		$cache = $left;
		return $left;
	}

	public function createOrder() {
		return $this->insertAndGetID(self::TABLE_ORDERS, array(
			'created' => microtime(true),
			'state' => 'clicking',
			'data' => json_encode(array(
				'createdBy' => array(
					'addr' => @$_SERVER['REMOTE_ADDR'],
					'agent' => @$_SERVER['HTTP_USER_AGENT'],
				),
			)),
		));
	}

	public function getOrder($orderID) {
		return $this->fetchOne("
			SELECT *
			  FROM " . self::TABLE_ORDERS . "
			 WHERE `id` = :orderID
		", array('orderID' => $orderID));
	}

	public function getOrderState($orderID) {
		return array_reduce($this->fetchOne("
			SELECT `state`
			  FROM " . self::TABLE_ORDERS . "
			 WHERE `id` = :orderID
		", array('orderID' => $orderID)), function ($carry, $row) {
			return $row;
		});
	}

	public function placeOrder($orderID, $data) {
		$order = $this->getOrder($orderID);
		$datafield = json_decode($order['data'], true);
		$affected = false; $tries = 0;
		while ($affected === false && $tries <= self::HRID_TRIES) {
			$tries++;
			$hrid = $this->generateHRID(6);
			try {
				$affected = $this->db->update(self::TABLE_ORDERS,
					array(
						'state' => 'ordered',
						'hrid' => $hrid,
						'secret' => $this->generateHRID(10),
						'data' => json_encode(array_merge($datafield, $data)),
					),
					array(
						'id' => $orderID,
						'state' => 'clicking',
					)
				);
				if ($affected === 1) {
					return $hrid;
				}
			} catch (\Exception $e) {
				if ($tries > self::HRID_TRIES) {
					throw $e;
				}
			}
		}
		if ($affected !== 1) {
			throw new \Exception('could not place order, probably timed out');
		}
	}

	public function getCartContents($orderID) {
		return array_map(function ($item) {
			$item['data'] = json_decode($item['data'], true);
			return $item;
		}, $this->fetchAll("
			SELECT   *
			  FROM   " . self::VIEW_ITEMS . "
			 WHERE       `order` = :orderID
			         AND `state` = 'clicking'
			ORDER BY `id` ASC
		", array('orderID' => $orderID)));
	}

	public function insertItem($orderID, Item $item) {
		return $this->insertAndGetID(self::TABLE_ITEMS, array(
			'`order`' => $orderID, // yes I have to quote that myself because Doctrine rules so much
			'type' => $item->getType(),
			'price' => $item->getPrice(),
			'data' => json_encode($item->getData()),
		));
	}

	public function removeItem($orderID, $itemID) {
		return $this->delete(self::TABLE_ITEMS, array(
			'`order`' => $orderID,
			'id' => $itemID,
		));
	}

	public function dropPendingOrders($olderThan) {
		$olderThan = (int)$olderThan;
		$minTime = microtime(true) - $olderThan;
		return $this->db->exec("
			DELETE FROM " . self::TABLE_ORDERS . "
			 WHERE     `state` = 'clicking'
			       AND `created` < $minTime
		");
	}

	public function logError(\Exception $e, $more = array()) {
		$data = array_merge(array(
			'class'    => get_class($e),
			'message'  => $e->getMessage(),
			'location' => $e->getFile() . ':' . $e->getLine(),
			'trace'    => $e->getTraceAsString(),
		), $more);
		$this->insertAndGetID(self::TABLE_ERRORS, array(
			'time' => microtime(true),
			'data' => json_encode($data),
		));
		return $data;
	}

	public function createTables() {
		$tables = array(
			self::TABLE_QUEUE => "(
				`id`        INT    UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`requested` DOUBLE UNSIGNED NOT NULL,
				`request`   BLOB            NOT NULL,
				`responded` DOUBLE UNSIGNED     NULL,
				`response`  BLOB                NULL,
				KEY requested (requested),
				KEY responded (responded)
			)",
			self::TABLE_ORDERS => "(
				`id`      INT      UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`created` DOUBLE   UNSIGNED NOT NULL,
				`state`   ENUM
					('clicking', 'cancelled', 'ordered', 'paid', 'returned')
				                            NOT NULL DEFAULT 'clicking',
				`hrid`    CHAR(6)               NULL,
				`secret`  CHAR(10)              NULL,
				`data`    BLOB              NOT NULL,
				UNIQUE `hrid` (`hrid`)
			)",
			self::TABLE_ITEMS => "(
				`id`     INT          UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`order`  INT          UNSIGNED NOT NULL,
				`type`   VARCHAR(10)  NOT NULL,
				`price`  DECIMAL(7,2) NOT NULL,
				`hrid`   CHAR(6)          NULL,
				`data`   BLOB         NOT NULL,
				UNIQUE `hrid` (`hrid`),
				FOREIGN KEY (`order`) REFERENCES `" . self::TABLE_ORDERS . "` (`id`) ON DELETE CASCADE
			)",
			self::TABLE_ERRORS => "(
				`id`   INT    UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`time` DOUBLE UNSIGNED NOT NULL,
				`data` BLOB            NOT NULL
			)",
		);

		$views = array(
			self::VIEW_ITEMS => "
				   SELECT i.*, o.`state`, o.`data` order_data
				     FROM `" . self::TABLE_ITEMS . "` i
				LEFT JOIN `" . self::TABLE_ORDERS . "` o
				       ON o.`id` = i.`order`
			",
			self::VIEW_TAKEN => "
				SELECT *
				  FROM `" . self::VIEW_ITEMS . "`
				 WHERE `state` NOT IN ('cancelled', 'returned')
			"
		);

		foreach ($tables as $name => $definition) {
			$this->db->executeQuery("CREATE TABLE $name $definition ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		foreach ($views as $name => $definition) {
			$this->db->executeQuery("CREATE VIEW $name AS $definition");
		}
	}

}
