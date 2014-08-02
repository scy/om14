<?php

namespace OM14\Shop\Item;

class KonfTicket extends Ticket {

	protected static $type = 'KONF';
	protected static $title = 'Konferenzteilnahme ohne Übernachtung';
	protected static $quotas = 'KONF';
	protected static $minPrice = 45;

} 
