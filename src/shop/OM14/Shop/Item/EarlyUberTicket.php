<?php

namespace OM14\Shop\Item;

class EarlyUberTicket extends UberTicket {

	protected static $type = 'UBER-EB';
	protected static $title = 'Konferenz inkl. Übernachtung (Early Bird)';
	protected static $minPrice = 75;
	protected static $maxTime = 1408312799; // 2014-08-17 23:59:59 CEST
	protected static $replaces = null; // override UberTicket

} 
