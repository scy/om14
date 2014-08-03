<?php

namespace OM14\Shop\Item;

class SupportingUberTicket extends UberTicket {

	protected static $type = 'UBER-ST';
	protected static $title = 'Konferenz inkl. Übernachtung (Supporting)';
	protected static $description = 'Teilnahme an der Konferenz und Übernachtung in der Jugendherberge. Von dir festlegbarer Preis ohne Obergrenze. Für Leute, die freiwillig mehr bezahlen möchten, um unser Budget für Referent*innen und Sozialtickets aufzustocken.';
	protected static $minPrice = 100;
	protected static $variablePrice = true;
	protected static $replaces = null; // override UberTicket

} 
