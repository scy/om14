<?php

namespace OM14\Shop;

use Symfony\Component\HttpFoundation\Request;

class Session {

	const SECRET_LENGTH = 16;
	const CSRF_TIMEOUT = 3600;

	protected $app;
	protected $session;

	public function __construct(Application $app) {
		$this->app = $app;
		$silex = $app->getSilexApplication();
		$this->session =& $silex['session'];
		$this->init();
	}

	protected function init() {
		if ($this->session->get('started') === null) {
			$this->session->set('started', microtime(true));
		}
		if ($this->session->get('secret') === null) {
			$this->session->set('secret', function_exists('openssl_random_pseudo_bytes')
				? openssl_random_pseudo_bytes(self::SECRET_LENGTH)
				: call_user_func(function () {
					$ret = '';
					for ($i = self::SECRET_LENGTH; $i--;) {
						$ret .= chr(mt_rand(0, 255));
					}
					return $ret;
				})
			);
		}
		return $this;
	}

	public function getCSRFToken() {
		$now = time();
		return "$now:" . sha1("$now:" . $this->session->get('secret'));
	}

	public function checkCSRFToken($token) {
		if ($token instanceof Request) {
			$token = $token->get('csrf');
		}
		list($time, $candidate) = explode(':', $token, 2);
		if ((int)$time < time() - self::CSRF_TIMEOUT) {
			throw new \Exception('CSRF timeout');
		}
		if (sha1("$time:" . $this->session->get('secret')) !== $candidate) {
			throw new \Exception('CSRF token invalid');
		}
		return $this;
	}

	public function addFlashMessage($type, $message) {
		$this->session->getFlashBag()->add($type, $message);
	}

	public function getFlashMessages() {
		return $this->session->getFlashBag()->all();
	}

	public function getOrderID() {
		return $this->session->get('order');
	}

	public function setOrderID($orderID) {
		return $this->session->set('order', $orderID === null ? $orderID : (int)$orderID);
	}

} 
