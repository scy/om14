<?php

namespace OM14\Shop\Item;

class FirstUberTicket extends UberTicket {

	protected static $type = 'UBER-FT';
	protected static $title = 'Konferenz inkl. Übernachtung (meine erste openmind)';
	protected static $description = 'Teilnahme an der Konferenz und Übernachtung in der Jugendherberge. Reserviertes Kontingent für Leute, die noch nie an einer openmind teilgenommen haben.';
	protected static $quotas = 'UBER|FT';
	protected static $minPrice = 95;

} 
