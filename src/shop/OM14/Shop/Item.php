<?php

namespace OM14\Shop;

abstract class Item {

	protected static $type;
	protected static $title;
	protected static $description;
	protected static $quotas;
	protected static $minPrice;
	protected static $variablePrice;
	protected static $maxTime;
	protected static $replaces;

	protected static $types = null;

	protected static $quotaLimits = array(
		'UBER' => 66,
		'KONF' => 50,
		'EB'   => 30,
		'FT'   => 30,
	);

	protected static $typesToQuotas = array();

	protected $id;
	protected $price;
	protected $name;
	protected $twitter;
	protected $size;

	public function __construct() {
		if (!static::$variablePrice) {
			$this->price = static::$minPrice;
		}
	}

	protected static final function notOnAbstractClass() {
		if (static::$type === null) {
			throw new \Exception('you cannot call this on the abstract Item class');
		}
	}

	protected static final function getSubclasses() {
		// Yes, I could get fancy here, but I don't have the time.
		return array('UberTicket', 'EarlyUberTicket', 'FirstUberTicket', 'SupportingUberTicket', 'KonfTicket', 'FirstKonfTicket', 'SupportingKonfTicket', 'Shirt');
	}

	public static final function fqClass($class) {
		return __NAMESPACE__ . "\\Item\\$class";
	}

	public static final function getClasses() {
		if (self::$types !== null) {
			return self::getSubclasses();
		}
		self::$types = array();
		foreach (self::getSubclasses() as $class) {
			$fqclass = self::fqClass($class);
			$refl = new \ReflectionClass($fqclass);
			$props = $refl->getStaticProperties();
			if (!isset($props['type'])) {
				throw new \Exception("$class does not set 'type' property");
			}
			$type = $props['type'];
			if (array_key_exists($type, self::$types)) {
				throw new \Exception(sprintf('type %s of %s already belongs to class %s',
					$type, $class, self::$types[$type]
				));
			}
			self::$types[$type] = $class;
			$quotas = isset($props['quotas']) ? explode('|', $props['quotas']) : array();
			foreach ($quotas as $quota) {
				if (!array_key_exists($quota, self::$quotaLimits)) {
					throw new \Exception("quota $quota of $class is unknown");
				}
			}
			self::$typesToQuotas[$type] = $quotas;
		}
		return self::getSubclasses();
	}

	public static final function getQuotaMapping() {
		self::getClasses();
		return self::$typesToQuotas;
	}

	public static final function getQuotaLimits() {
		return self::$quotaLimits;
	}

	public static final function getAvailableItems(Database $db, $useCache = true) {
		$available = array();
		foreach (self::getSubclasses() as $subclass) {
			$fqclass = self::fqClass($subclass);
			if ($fqclass::isAvailable($db, $useCache)) {
				$available[] = $subclass;
			}
		}
		return $available;
	}

	public static final function createFromArray($data) {
		if (!is_array($data)) {
			throw new \Exception('data needs to be an array');
		}
		self::getClasses();
		if (!isset(self::$types[$data['type']])) {
			throw new \Exception('unknown type: ' . $data['type']);
		}
		if (isset($data['data']) && is_array($data['data'])) {
			$data = array_merge($data['data'], $data);
		}
		$fqclass = self::fqClass(self::$types[$data['type']]);
		$item = new $fqclass();
		$item->fillFromArray($data);
		return $item;
	}

	public static final function getAvailableItemProperties(Database $db, $useCache = true) {
		$available = array();
		foreach (self::getAvailableItems($db, $useCache) as $class) {
			$fqclass = self::fqClass($class);
			$available[] = $fqclass::getProperties($db);
		}
		return $available;
	}

	public static function getType() {
		static::notOnAbstractClass();
		return static::$type;
	}

	public static function getTitle() {
		static::notOnAbstractClass();
		return static::$title;
	}

	public static function getDescription() {
		static::notOnAbstractClass();
		return static::$description;
	}

	public static function getMinPrice() {
		static::notOnAbstractClass();
		return static::$minPrice;
	}

	public static function getVariablePrice() {
		static::notOnAbstractClass();
		return static::$variablePrice;
	}

	public static function getQuotas() {
		static::notOnAbstractClass();
		return isset(static::$quotas) ? (is_array(static::$quotas) ?: explode('|', static::$quotas)) : array();
	}

	public static function getProperties(Database $db = null) {
		static::notOnAbstractClass();
		$result = array();
		foreach (array('Type', 'Title', 'Description', 'MinPrice') as $property) {
			$method = "get$property";
			$value = static::$method();
			if ($value !== null) {
				$result[lcfirst($property)] = $value;
			}
		};
		if (static::getVariablePrice()) {
			$result['variablePrice'] = true;
		}
		if ($db !== null) {
			$result['numAvailable'] = static::numAvailable($db);
		}
		return $result;
	}

	/**
	 * Whether the item is basically enabled, without checking the quota.
	 *
	 * @return bool False if the type has a time limitation and the current time is outside of that range. Else true.
	 */
	public static function isEnabled() {
		static::notOnAbstractClass();
		if (!isset(static::$maxTime)) {
			return true;
		}
		if (time() > static::$maxTime) {
			return false;
		}
		return true;
	}

	public static function numAvailable(Database $db, $useCache = true) {
		static::notOnAbstractClass();
		// If this is a replacement type, and the type it's supposed to replace is available, it is not available.
		if (isset(static::$replaces)) {
			$replaces = static::fqClass(static::$replaces);
			if ($replaces::isAvailable($db, $useCache)) {
				return 0;
			}
		}
		$available = $db->getAvailableQuota($useCache);
		$minAmount = null;
		foreach (static::getQuotas() as $quota) {
			if ($available[$quota] < 1) {
				return 0;
			}
			$minAmount = ($minAmount === null) ? $available[$quota] : min($minAmount, $available[$quota]);
		}
		// All quotas okay.
		return static::isEnabled() ? $minAmount : 0;
	}

	/**
	 * Can I currently buy one of these, without breaking quota?
	 *
	 * @param Database $db
	 * @param bool $useCache Whether to use cached quota information. Do not use this when actually reserving an item.
	 *
	 * @return bool Whether one of these items could be bought at the moment.
	 */
	public static function isAvailable(Database $db, $useCache = true) {
		$num = static::numAvailable($db, $useCache);
		return $num === null || $num > 0;
	}

	public function getPrice() {
		static::notOnAbstractClass();
		return static::$variablePrice ? $this->price : static::$minPrice;
	}

	public function setPrice($price) {
		static::notOnAbstractClass();
		if (!static::$variablePrice) {
			throw new \Exception('this item does not support setting the price');
		}
		$price = (int)$price;
		if ($price < static::$minPrice) {
			throw new \Exception('the minimum price is ' . static::$minPrice);
		}
		$this->price = $price;
	}

	public function getName() {
		static::notOnAbstractClass();
		return $this->name;
	}

	public function setName($name) {
		static::notOnAbstractClass();
		$this->name = (string)$name;
	}

	public function getTwitter() {
		static::notOnAbstractClass();
		return $this->twitter;
	}

	public function setTwitter($twitter) {
		static::notOnAbstractClass();
		$this->twitter = ($twitter === null) ? null : (string)$twitter;
	}

	public function getSize() {
		static::notOnAbstractClass();
		return $this->size;
	}

	public function setSize($size) {
		static::notOnAbstractClass();
		if ($size === null) {
			$this->size = null;
		} else {
			$size = (string)$size;
			if (!preg_match('/^(?:Unisex|Girlie) (?:S|M|L|[234]?XL)$/', $size)) {
				throw new \Exception('invalid size value');
			}
			$this->size = $size;
		}
	}

	public function getData() {
		static::notOnAbstractClass();
		$ret = array();
		foreach (array('Name', 'Twitter', 'Size') as $prop) {
			$method = "get$prop";
			if ($this->$method() !== null) {
				$ret[lcfirst($prop)] = $this->$method();
			}
		}
		return $ret;
	}

	public function fillFromArray($data) {
		$this->id = isset($data['id']) ? (int)$data['id'] : null;
		if (static::$variablePrice && isset($data['price'])) {
			$this->setPrice($data['price']);
		}
		foreach (array('Name', 'Twitter', 'Size') as $prop) {
			$method = "set$prop";
			if (isset($data[lcfirst($prop)])) {
				$this->$method($data[lcfirst($prop)]);
			}
		}
	}

	public function getAsArray() {
		return array(
			'id' => $this->id,
			'type' => static::getType(),
			'title' => static::getTitle(),
			'price' => $this->getPrice(),
			'name' => $this->getName(),
			'twitter' => $this->getTwitter(),
			'size' => $this->getSize(),
			'variablePrice' => true,
		);
	}

}
