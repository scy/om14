<?php

namespace OM14\Shop;

use Igorw\Silex\ConfigServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

class Application {

	protected $app;
	protected $db;

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
		$app = $this->app;
		$this->app->error(function (\Exception $e, $code) use ($app, $web) {
			$data = array(
				'code'     => $code,
				'class'    => get_class($e),
				'message'  => $e->getMessage(),
				'location' => $e->getFile() . ':' . $e->getLine(),
				'trace'    => $e->getTraceAsString(),
			);
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
		if ($this->app['session']->get('started') === null) {
			$this->app['session']->set('started', microtime(true));
		}
		return $this;
	}

	protected function defineRoutes() {
		$app = $this->app; $db = $this->getDB();
		$app->get('/', function () use ($app, $db) {
			return $app['twig']->render('home.twig', array(
				'availableItems' => Item::getAvailableItemProperties($db, true),
				'postURL' => $app['url_generator']->generate('addItem'),
			));
		})->bind('home');
		$app->post('/add', function () use ($app, $db) {
			return $app->redirect($app['url_generator']->generate('home'), 303);
		})->bind('addItem');
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

	/**
	 * @return \Silex\Application
	 */
	public function getSilexApplication() {
		return $this->app;
	}

	public function runWeb() {
		$this->registerErrorHandler(true)
		     ->enhanceTwig()
		     ->initSession()
		     ->defineRoutes()
		     ->app->run();
	}

}
