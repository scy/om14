<?php

namespace OM14\Shop;

abstract class Item {

	protected static $type;
	protected static $title;
	protected static $quotas;
	protected static $minPrice;
	protected static $maxTime;
	protected static $replaces;

	protected static $types = null;

	protected static $quotaLimits = array(
		'UBER' => 100, // FIXME non-final
		'KONF' => 50,  // FIXME non-final
	);

	protected static $typesToQuotas = array();

	protected static final function fqClass($class) {
		return __NAMESPACE__ . "\\Item\\$class";
	}

	protected static final function notOnAbstractClass() {
		if (static::$type === null) {
			throw new \Exception('you cannot call this on the abstract Item class');
		}
	}

	protected static final function getSubclasses() {
		// Yes, I could get fancy here, but I don't have the time.
		return array('UberTicket', 'EarlyUberTicket', 'KonfTicket');
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

	public static function getQuotas() {
		static::notOnAbstractClass();
		return is_array(static::$quotas) ?: explode('|', static::$quotas);
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

	/**
	 * Can I currently buy one of these, without breaking quota?
	 *
	 * @param Database $db
	 * @param bool $useCache Whether to use cached quota information. Do not use this when actually reserving an item.
	 *
	 * @return bool Whether one of these items could be bought at the moment.
	 */
	public static function isAvailable(Database $db, $useCache = true) {
		static::notOnAbstractClass();
		// If this is a replacement type, and the type it's supposed to replace is available, it is not available.
		if (isset(static::$replaces)) {
			$replaces = static::fqClass(static::$replaces);
			if ($replaces::isAvailable($db, $useCache)) {
				return false;
			}
		}
		$available = $db->getAvailableQuota($useCache);
		foreach (static::getQuotas() as $quota) {
			if ($available[$quota] < 1) {
				return false;
			}
		}
		// All quotas okay.
		return static::isEnabled();
	}

}
