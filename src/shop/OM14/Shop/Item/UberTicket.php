<?php

namespace OM14\Shop\Item;

class UberTicket extends Ticket {

	protected static $type = 'UBER';
	protected static $title = 'Konferenz inkl. Übernachtung';
	protected static $quotas = 'UBER|KONF';
	protected static $minPrice = 95;
	protected static $replaces = 'EarlyUberTicket';

} 
