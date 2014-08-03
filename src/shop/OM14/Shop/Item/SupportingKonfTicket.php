<?php

namespace OM14\Shop\Item;

class SupportingKonfTicket extends KonfTicket {

	protected static $type = 'KONF-ST';
	protected static $title = 'Konferenzteilnahme ohne Übernachtung (Supporting)';
	protected static $description = 'Teilnahme an der Konferenz, selbst organisierte Übernachtung. Von dir festlegbarer Preis ohne Obergrenze. Für Leute, die freiwillig mehr bezahlen möchten, um unser Budget für Referent*innen und Sozialtickets aufzustocken.';
	protected static $minPrice = 50;
	protected static $variablePrice = true;

} 
