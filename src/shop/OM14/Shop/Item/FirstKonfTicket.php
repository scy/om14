<?php

namespace OM14\Shop\Item;

class FirstKonfTicket extends KonfTicket {

	protected static $type = 'KONF-FT';
	protected static $title = 'Konferenzteilnahme ohne Übernachtung (meine erste openmind)';
	protected static $description = 'Teilnahme an der Konferenz, selbst organisierte Übernachtung. Reserviertes Kontingent für Leute, die noch nie an einer openmind teilgenommen haben.';
	protected static $quotas = 'KONF|FT';
	protected static $minPrice = 45;

} 
