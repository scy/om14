<?php

namespace OM14\Shop;

use Silex\Provider\DoctrineServiceProvider;

class Database {

	protected $app = null;

	public function __construct(Application $app) {
		$this->app = $app;
		$app->register(new DoctrineServiceProvider(), array(
			'db.options'   => array(
				'driver'   => 'pdo_mysql',
				'host'     => $app->getConfig('mysql/host'),
				'dbname'   => $app->getConfig('mysql/db'),
				'user'     => $app->getConfig('mysql/user'),
				'password' => $app->getConfig('mysql/pass'),
				'charset'  => 'utf8',
			)
		));
	}

} 
