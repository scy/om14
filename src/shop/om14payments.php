<?php

require_once __DIR__ . '/vendor/autoload.php';

function msg($idx, $msg) {
	printf("line %d: %s\n", $idx + 1, $msg);
}

$app = new \OM14\Shop\Application();
$db  = $app->getDB();

$payments = file('php://stdin');

function sendMail($data, $hrid, $amount) {
	global $app;
	$text = "
		Hallo {$data['name']},

		wir haben deine Zahlung für die openmind #om14 in Höhe von {$amount}€ erhalten. Vielen Dank dafür!

		Sorry dafür, dass es mit der Bestätigung etwas gedauert hat; wir haben die Liste der Zahlungseingänge erst mit einiger Verzögerung von der Piratenpartei zur Verfügung gestellt bekommen.

		Deinen Ticketcode erhältst du zusammen mit den wichtigsten Last-Minute-Informationen rechtzeitig vor der #om14 per E-Mail.

		Bei Fragen stehen wir dir unter info@openmind-konferenz.de gern zur Verfügung. Bitte gib unbedingt immer deine Bestell-ID an, sie lautet: $hrid

		Wir freuen uns auf deinen Besuch!

		     Dein openmind-Team
		";
	$text = trim(str_replace(array("\t", "\n"), array('', "\r\n"), $text)) . "\r\n";
	foreach (array($app->getConfig('mail/cc'), $data['mail']) as $to) {
		mail($to, "openmind #om14: Deine Zahlung $hrid", $text,
			"From: openmind #om14 <info@openmind-konferenz.de>\r\nContent-Type: text/plain; charset=UTF-8",
			'-f info@openmind-konferenz.de'
		);
	}
}

function run($modify = false)
{
	global $payments, $db;

	$booked = 0.00;
	foreach ($payments as $idx => $payment) {

		list($hrid, $amount) = explode("\t", $payment, 2);
		if (!preg_match('/^[A-Z]{4,6}$/', $hrid)) {
			msg($idx, "skipping because of strange HRID: $hrid");
			continue;
		}
		if (!preg_match('/^[0-9]+[.,][0-9]{2}$/', $amount)) {
			msg($idx, "skipping because of strange amount: $amount");
			continue;
		}
		$amount = (float)str_replace(',', '.', $amount);

		$order = $db->getOrderByHRID($hrid);
		if (!$order) {
			msg($idx, "no such order: $hrid");
			continue;
		}
		$order['data'] = json_decode($order['data'], true);

		$price = (float)$order['price'];
		if ($price != $amount) {
			msg($idx, "skipping $hrid: booked amount of $amount does not correspond to order price $price");
			continue;
		}

		if ($order['state'] != 'ordered') {
			msg($idx, "skipping $hrid: order is {$order['state']}");
			continue;
		}

		msg($idx, ($modify ? 'setting' : 'would set') . " $hrid to 'paid'");
		if ($modify) {
			$db->setOrderState($order['id'], 'paid', $order['state']);
			sendMail($order['data'], $hrid, str_replace('.', ',', sprintf('%.2f', $amount)));
		}

		$booked += $amount;

	}
	printf("booked amount: %.2f\n", $booked);
}

run(false);
