<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new \OM14\Shop\Application();

$qh = new \OM14\Shop\QueueHandler($app);

while (true) {
	$qh->handleNext();
	if (time() % 60 === 0) {
		$app->getDB()->dropPendingOrders(3600);
	}
	usleep(200000);
}
