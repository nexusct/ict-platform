<?php

declare(strict_types=1);

namespace ICT_Platform\Api;

use ICT_Platform\Container\Container;
use ICT_Platform\Api\Controllers\ProjectController;
use ICT_Platform\Api\Controllers\TimeEntryController;
use ICT_Platform\Api\Controllers\InventoryController;
use ICT_Platform\Api\Controllers\PurchaseOrderController;
use ICT_Platform\Api\Controllers\ResourceController;
use ICT_Platform\Api\Controllers\SyncController;
use ICT_Platform\Api\Controllers\ReportController;
// New feature controllers (v2.1.0)
use ICT_Platform\Api\Controllers\DocumentController;
use ICT_Platform\Api\Controllers\EquipmentController;
use ICT_Platform\Api\Controllers\ExpenseController;
use ICT_Platform\Api\Controllers\SignatureController;
use ICT_Platform\Api\Controllers\VoiceNoteController;
use ICT_Platform\Api\Controllers\FleetController;
use ICT_Platform\Api\Controllers\NotificationController;
use ICT_Platform\Api\Controllers\QrCodeController;
use ICT_Platform\Api\Controllers\ActivityController;
use ICT_Platform\Api\Controllers\WeatherController;
use ICT_Platform\Api\Controllers\ClientPortalController;

/**
 * REST API Router
 *
 * Registers and coordinates all REST API routes.
 * Refactored from ICT_API to use PSR-4 namespacing and dependency injection.
 *
 * @package ICT_Platform\Api
 * @since   2.0.0
 */
class Router {

	/**
	 * API namespace
	 */
	public const NAMESPACE = 'ict/v1';

	/**
	 * Dependency injection container
	 */
	private Container $container;

	/**
	 * Registered controllers
	 *
	 * @var array<string, string>
	 */
	private array $controllers = array(
		// Core controllers
		'projects'        => ProjectController::class,
		'time-entries'    => TimeEntryController::class,
		'inventory'       => InventoryController::class,
		'purchase-orders' => PurchaseOrderController::class,
		'resources'       => ResourceController::class,
		'sync'            => SyncController::class,
		'reports'         => ReportController::class,
		// New feature controllers (v2.1.0)
		'documents'       => DocumentController::class,
		'equipment'       => EquipmentController::class,
		'expenses'        => ExpenseController::class,
		'signatures'      => SignatureController::class,
		'voice-notes'     => VoiceNoteController::class,
		'fleet'           => FleetController::class,
		'notifications'   => NotificationController::class,
		'qr-codes'        => QrCodeController::class,
		'activity'        => ActivityController::class,
		'weather'         => WeatherController::class,
		'client-portal'   => ClientPortalController::class,
	);

	/**
	 * Constructor
	 *
	 * @param Container $container DI container
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register all REST API routes
	 */
	public function registerRoutes(): void {
		foreach ( $this->controllers as $key => $controllerClass ) {
			if ( class_exists( $controllerClass ) ) {
				/** @var AbstractController $controller */
				$controller = $this->container->resolve( $controllerClass );
				$controller->registerRoutes();
			}
		}
	}

	/**
	 * Get the API namespace
	 */
	public static function getNamespace(): string {
		return self::NAMESPACE;
	}

	/**
	 * Check if Divi builder request
	 *
	 * @return bool
	 */
	public static function isDiviBuilderRequest(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['et_fb'] ) && '1' === $_GET['et_fb'] ) {
			return true;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['action'] ) && strpos( $_POST['action'], 'et_fb_' ) === 0 ) {
			return true;
		}

		if ( isset( $_SERVER['HTTP_X_DIVI_BUILDER'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Divi is active
	 *
	 * @return bool
	 */
	public static function isDiviActive(): bool {
		return defined( 'ET_CORE_VERSION' ) || defined( 'ET_BUILDER_PLUGIN_DIR' );
	}

	/**
	 * Register a custom controller
	 *
	 * @param string $key             Controller key
	 * @param string $controllerClass Controller class name
	 */
	public function registerController( string $key, string $controllerClass ): void {
		$this->controllers[ $key ] = $controllerClass;
	}

	/**
	 * Get registered controllers
	 *
	 * @return array<string, string>
	 */
	public function getControllers(): array {
		return $this->controllers;
	}
}
