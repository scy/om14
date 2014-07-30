<?php

namespace OM14\Shop;

use Doctrine\DBAL\Schema\Schema;
use Silex\Provider\DoctrineServiceProvider;

class Database {

	const TABLE_QUEUE = 'queue';

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

	public function createTables() {
		$schema = new Schema();

		$queue = $schema->createTable(self::TABLE_QUEUE);
		$queue->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
		$queue->addColumn('requested', 'float', array('notnull' => true));
		$queue->addColumn('request', 'blob', array('notnull' => true));
		$queue->addColumn('responded', 'float', array('notnull' => false));
		$queue->addColumn('response', 'blob', array('notnull' => false));
		$queue->setPrimaryKey(array('id'));
		$queue->addIndex(array('requested'), 'requested');
		$queue->addIndex(array('responded'), 'responded');

		$sqlArray = $schema->toSql($this->db->getDatabasePlatform());

		$this->db->beginTransaction();
		try {
			foreach ($sqlArray as $sql) {
				$this->db->executeQuery($sql);
			}
		} catch (\Exception $e) {
			var_dump($e);
			$this->db->rollback();
			throw $e;
		}
	}

}
