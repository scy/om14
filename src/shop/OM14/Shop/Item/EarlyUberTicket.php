<?php

namespace OM14\Shop\Item;

class EarlyUberTicket extends UberTicket {

	protected static $type = 'UBER-EB';
	protected static $title = 'Konferenz inkl. Übernachtung (Early Bird)';
	protected static $description = 'Teilnahme an der Konferenz und Übernachtung in der Jugendherberge. Reduzierter Preis für die ersten Buchungen.';
	protected static $minPrice = 75;
	protected static $maxTime = 1408831199; // 2014-08-23 23:59:59 CEST
	protected static $replaces = null; // override UberTicket
	protected static $quotas = 'EB';

} 
