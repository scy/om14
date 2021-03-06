<?php

namespace OM14\Shop;

use Igorw\Silex\ConfigServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Request;

class Application {

	protected $app;
	protected $db;

	/**
	 * @var Session
	 */
	protected $session;

	/**
	 * @var Cart
	 */
	protected $cart;

	public function __construct() {
		$this->app = new \Silex\Application();
		$this->registerProviders();
	}

	protected function registerProviders() {
		try {
			$this->app->register(new ConfigServiceProvider(
				__DIR__ . '/../../config.yml', array(), null, 'conf'
			));
		} catch (\InvalidArgumentException $e) {
			// Config file could not be read. We'll fail later.
		}
		$this->app->register(new TwigServiceProvider(), array(
			'twig.path' => __DIR__ . '/../../views',
		));
		$this->app->register(new SessionServiceProvider());
		$this->app->register(new UrlGeneratorServiceProvider());
		$this->db = new Database($this);
		return $this;
	}

	protected function registerErrorHandler($web) {
		$app = $this->app; $db = $this->getDB();
		$this->app->error(function (\Exception $e, $code) use ($app, $db, $web) {
			$data = array(
				'code' => $code,
			);
			if ($web) {
				$data = array_merge($data, array(
					'addr' => $_SERVER['REMOTE_ADDR'],
					'agent' => $_SERVER['HTTP_USER_AGENT'],
				));
			}
			if ($code != 404) {
				$data = $db->logError($e, $data);
			}
			// TODO: New Relic integration?
			if ($web) {
				return $app['twig']->render('error.twig', $data);
			}
			return json_encode($data);
		});
		return $this;
	}

	protected function enhanceTwig() {
		$this->app['twig'] = $this->app->share($this->app->extend('twig', function ($twig, $app) {
			$twig->addFunction(new \Twig_SimpleFunction('h1', function ($text) {
				return '<h1><span class="wrapper">' . $text . '</span></h1>';
			}, array('is_safe' => array('html'))));
			return $twig;
		}));
		return $this;
	}

	protected function initSession() {
		$this->session = new Session($this);
		$this->cart = new Cart($this);
		return $this;
	}

	protected function defineRoutes() {
		$app = $this->app; $db = $this->getDB(); $session = $this->session; $shop = $this;
		$app->get('/', function () use ($app, $db, $session, $shop) {
			return $app['twig']->render('home.twig', array(
				'messages' => $session->getFlashMessages(),
				'availableItems' => Item::getAvailableItemProperties($db, true),
				'cart' => $shop->getCart()->getContentsAsArray(),
				'cartSum' => $shop->getCart()->getSum(),
				'timeLeft' => $shop->getCart()->getTimeLeft(),
				'addURL' => $app['url_generator']->generate('addItem'),
				'removeURL' => $app['url_generator']->generate('removeItem'),
				'orderURL' => $app['url_generator']->generate('order'),
				'csrfToken' => $session->getCSRFToken(),
			));
		})->bind('home');
		$app->post('/add', function (Request $req) use ($app, $shop, $session) {
			$session->checkCSRFToken($req);
			$shop->getCart()->handleAddRequest($req);
			return $app->redirect($app['url_generator']->generate('home'), 303);
		})->bind('addItem');
		$app->post('/remove', function (Request $req) use ($app, $shop, $session) {
			$session->checkCSRFToken($req);
			$shop->getCart()->handleRemoveRequest($req);
			return $app->redirect($app['url_generator']->generate('home'), 303);
		})->bind('removeItem');
		$app->post('/order', function (Request $req) use ($app, $shop, $session) {
			$session->checkCSRFToken($req);
			$shop->getCart()->handleOrderRequest($req);
			return $app->redirect($app['url_generator']->generate('home'), 303);
		})->bind('order');
		return $this;
	}

	public function getConfig($path) {
		$conf = $this->app['conf'];
		if (!is_array($conf)) {
			throw new \Exception('no config loaded');
		}
		$pieces = explode('/', (string)$path);
		$current = array();
		foreach ($pieces as $piece) {
			$current[] = $piece;
			if (array_key_exists($piece, $conf)) {
				$conf =& $conf[$piece];
			} else {
				throw new \Exception(sprintf('no such config item: %s (looking for: %s)',
					implode('/', $current), $path
				));
			}
		}
		return $conf;
	}

	/**
	 * @return Database The OM14 Database instance.
	 */
	public function getDB() {
		return $this->db;
	}

	public function getSession() {
		return $this->session;
	}

	public function getCart() {
		return $this->cart;
	}

	/**
	 * @return \Silex\Application
	 */
	public function getSilexApplication() {
		return $this->app;
	}

	public function prepareWeb() {
		$this->registerErrorHandler(true)
		     ->enhanceTwig()
		     ->initSession()
		     ->defineRoutes();
		return $this;
	}

	public function runWeb() {
		$this->prepareWeb()
		     ->app->run();
		return $this;
	}

}
