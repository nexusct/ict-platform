<?php

declare(strict_types=1);

namespace ICT_Platform\Core;

use ICT_Platform\Container\Container;
use ICT_Platform\Api\Router;
use ICT_Platform\Util\Helper;
use ICT_Platform\Util\Cache;
use ICT_Platform\Util\SyncLogger;

/**
 * Application Bootstrap Class
 *
 * Main entry point for the PSR-4 namespaced plugin architecture.
 * Configures the DI container and registers services.
 *
 * @package ICT_Platform\Core
 * @since   2.0.0
 */
class Application {

	/**
	 * Container instance
	 */
	private Container $container;

	/**
	 * Plugin version
	 */
	private string $version;

	/**
	 * Plugin name
	 */
	private string $pluginName;

	/**
	 * Whether the application has been booted
	 */
	private bool $booted = false;

	/**
	 * Constructor
	 *
	 * @param string $pluginName Plugin name
	 * @param string $version    Plugin version
	 */
	public function __construct( string $pluginName, string $version ) {
		$this->pluginName = $pluginName;
		$this->version    = $version;
		$this->container  = Container::getInstance();
	}

	/**
	 * Boot the application
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->registerServices();
		$this->registerHooks();

		$this->booted = true;
	}

	/**
	 * Register services in the container
	 */
	private function registerServices(): void {
		// Register self
		$this->container->instance( self::class, $this );

		// Register core utilities as singletons
		$this->container->singleton( Helper::class );
		$this->container->singleton( Cache::class );
		$this->container->singleton( SyncLogger::class );

		// Register API Router
		$this->container->singleton(
			Router::class,
			function ( Container $c ) {
				return new Router( $c );
			}
		);

		// Register controllers - they will be resolved with dependencies automatically
		$this->container->bind( \ICT_Platform\Api\Controllers\ProjectController::class );
		$this->container->bind( \ICT_Platform\Api\Controllers\TimeEntryController::class );
		$this->container->bind( \ICT_Platform\Api\Controllers\InventoryController::class );
		$this->container->bind( \ICT_Platform\Api\Controllers\PurchaseOrderController::class );
		$this->container->bind( \ICT_Platform\Api\Controllers\ResourceController::class );
		$this->container->bind( \ICT_Platform\Api\Controllers\ReportController::class );

		// SyncController needs SyncLogger injected
		$this->container->bind(
			\ICT_Platform\Api\Controllers\SyncController::class,
			function ( Container $c ) {
				return new \ICT_Platform\Api\Controllers\SyncController(
					$c->resolve( Helper::class ),
					$c->resolve( Cache::class ),
					$c->resolve( SyncLogger::class )
				);
			}
		);

		// Allow plugins/themes to register additional services
		do_action( 'ict_platform_register_services', $this->container );
	}

	/**
	 * Register WordPress hooks
	 */
	private function registerHooks(): void {
		// Register REST API routes
		add_action(
			'rest_api_init',
			function () {
				$router = $this->container->resolve( Router::class );
				$router->registerRoutes();
			}
		);

		// Allow plugins/themes to register additional hooks
		do_action( 'ict_platform_register_hooks', $this );
	}

	/**
	 * Get the container instance
	 *
	 * @return Container
	 */
	public function getContainer(): Container {
		return $this->container;
	}

	/**
	 * Get the plugin name
	 *
	 * @return string
	 */
	public function getPluginName(): string {
		return $this->pluginName;
	}

	/**
	 * Get the plugin version
	 *
	 * @return string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * Resolve a service from the container
	 *
	 * @param string $abstract Service identifier
	 * @return mixed
	 */
	public function resolve( string $abstract ): mixed {
		return $this->container->resolve( $abstract );
	}

	/**
	 * Register a service provider
	 *
	 * @param string $providerClass Provider class name
	 */
	public function registerProvider( string $providerClass ): void {
		if ( class_exists( $providerClass ) ) {
			$provider = new $providerClass( $this->container );
			if ( method_exists( $provider, 'register' ) ) {
				$provider->register();
			}
			if ( method_exists( $provider, 'boot' ) ) {
				$provider->boot();
			}
		}
	}
}
