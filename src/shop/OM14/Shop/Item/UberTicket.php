<?php

namespace OM14\Shop\Item;

class UberTicket extends Ticket {

	protected static $type = 'UBER';
	protected static $title = 'Konferenz inkl. Übernachtung';
	protected static $description = 'Teilnahme an der Konferenz und Übernachtung in der Jugendherberge.';
	protected static $quotas = 'UBER';
	protected static $minPrice = 95;
	protected static $replaces = 'EarlyUberTicket';

} 
