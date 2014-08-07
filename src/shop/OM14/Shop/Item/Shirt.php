<?php

namespace OM14\Shop\Item;

use OM14\Shop\Item;

class Shirt extends Item {

	protected static $type = 'SHIRT';
	protected static $title = 'T-Shirt';
	protected static $description = 'Baumwolle, kleidsames Schwarz, selbstverständlich mit #om14-Logo. Achtung: nur bis 20.08. 12:00 Uhr mittags bestellbar!';
	protected static $maxTime = 1408528800; // 2014-08-20 12:00:00 CEST
	protected static $minPrice = 25;

} 
