<?php

declare(strict_types=1);

namespace ICT_Platform\Container;

use Psr\Container\ContainerInterface;
use ICT_Platform\Container\Exception\NotFoundException;
use ICT_Platform\Container\Exception\ContainerException;
use Closure;
use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;

/**
 * PSR-11 compliant Dependency Injection Container
 *
 * @package ICT_Platform\Container
 * @since   2.0.0
 */
class Container implements ContainerInterface {

	/**
	 * Singleton instance
	 */
	private static ?Container $instance = null;

	/**
	 * Container bindings
	 *
	 * @var array<string, Closure|string|object>
	 */
	private array $bindings = array();

	/**
	 * Resolved instances (singletons)
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Whether the binding is a singleton
	 *
	 * @var array<string, bool>
	 */
	private array $singletons = array();

	/**
	 * Get the singleton instance
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bind a class or interface to a concrete implementation
	 *
	 * @param string                     $abstract   The abstract type (interface or class name)
	 * @param Closure|string|object|null $concrete   The concrete implementation
	 * @param bool                       $singleton  Whether to treat as singleton
	 */
	public function bind( string $abstract, Closure|string|object|null $concrete = null, bool $singleton = false ): self {
		$this->bindings[ $abstract ]   = $concrete ?? $abstract;
		$this->singletons[ $abstract ] = $singleton;

		return $this;
	}

	/**
	 * Bind a singleton
	 *
	 * @param string                     $abstract  The abstract type
	 * @param Closure|string|object|null $concrete  The concrete implementation
	 */
	public function singleton( string $abstract, Closure|string|object|null $concrete = null ): self {
		return $this->bind( $abstract, $concrete, true );
	}

	/**
	 * Register an existing instance as a singleton
	 *
	 * @param string $abstract The abstract type
	 * @param object $instance The instance to register
	 */
	public function instance( string $abstract, object $instance ): self {
		$this->instances[ $abstract ]  = $instance;
		$this->singletons[ $abstract ] = true;

		return $this;
	}

	/**
	 * Check if the container has a binding
	 *
	 * @param string $id Identifier of the entry to look for
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Get an entry from the container
	 *
	 * @param string $id Identifier of the entry to look for
	 * @return mixed The resolved entry
	 * @throws NotFoundException  If no entry was found
	 * @throws ContainerException If there was an error resolving the entry
	 */
	public function get( string $id ): mixed {
		return $this->resolve( $id );
	}

	/**
	 * Resolve a type from the container
	 *
	 * @param string               $abstract   The abstract type to resolve
	 * @param array<string, mixed> $parameters Additional parameters to pass
	 * @return mixed The resolved instance
	 * @throws NotFoundException  If the type cannot be resolved
	 * @throws ContainerException If there was an error during resolution
	 */
	public function resolve( string $abstract, array $parameters = array() ): mixed {
		// Return cached singleton if available
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		// Get the concrete implementation
		$concrete = $this->bindings[ $abstract ] ?? $abstract;

		// Build the instance
		$instance = $this->build( $concrete, $parameters );

		// Cache if singleton
		if ( $this->isSingleton( $abstract ) ) {
			$this->instances[ $abstract ] = $instance;
		}

		return $instance;
	}

	/**
	 * Build a concrete instance
	 *
	 * @param Closure|string|object $concrete   The concrete type to build
	 * @param array<string, mixed>  $parameters Additional parameters
	 * @return mixed The built instance
	 * @throws ContainerException If the type cannot be built
	 */
	private function build( Closure|string|object $concrete, array $parameters = array() ): mixed {
		// If it's a Closure, execute it
		if ( $concrete instanceof Closure ) {
			return $concrete( $this, $parameters );
		}

		// If it's already an object, return it
		if ( is_object( $concrete ) ) {
			return $concrete;
		}

		// Try to instantiate the class
		try {
			$reflector = new ReflectionClass( $concrete );
		} catch ( \ReflectionException $e ) {
			throw new NotFoundException( "Class {$concrete} does not exist.", 0, $e );
		}

		// Check if instantiable
		if ( ! $reflector->isInstantiable() ) {
			throw new ContainerException( "Class {$concrete} is not instantiable." );
		}

		$constructor = $reflector->getConstructor();

		// No constructor, just instantiate
		if ( $constructor === null ) {
			return new $concrete();
		}

		// Resolve constructor dependencies
		$dependencies = $this->resolveDependencies(
			$constructor->getParameters(),
			$parameters
		);

		return $reflector->newInstanceArgs( $dependencies );
	}

	/**
	 * Resolve constructor dependencies
	 *
	 * @param array<ReflectionParameter> $dependencies The dependencies to resolve
	 * @param array<string, mixed>       $parameters   User-provided parameters
	 * @return array<mixed> The resolved dependencies
	 */
	private function resolveDependencies( array $dependencies, array $parameters ): array {
		$results = array();

		foreach ( $dependencies as $dependency ) {
			$name = $dependency->getName();

			// Check if user provided this parameter
			if ( array_key_exists( $name, $parameters ) ) {
				$results[] = $parameters[ $name ];
				continue;
			}

			// Try to resolve by type
			$type = $dependency->getType();

			if ( $type === null || $type->isBuiltin() ) {
				// No type hint or built-in type
				if ( $dependency->isDefaultValueAvailable() ) {
					$results[] = $dependency->getDefaultValue();
				} elseif ( $dependency->allowsNull() ) {
					$results[] = null;
				} else {
					throw new ContainerException(
						"Cannot resolve parameter \${$name} without a type hint or default value."
					);
				}
			} else {
				// Resolve the class
				/** @var ReflectionNamedType $type */
				$results[] = $this->resolve( $type->getName() );
			}
		}

		return $results;
	}

	/**
	 * Check if a binding is a singleton
	 */
	private function isSingleton( string $abstract ): bool {
		return $this->singletons[ $abstract ] ?? false;
	}

	/**
	 * Call a method on an object with dependency injection
	 *
	 * @param object|string        $target     The object or class::method string
	 * @param string|null          $method     The method name (if $target is object)
	 * @param array<string, mixed> $parameters Additional parameters
	 * @return mixed The method result
	 */
	public function call( object|string $target, ?string $method = null, array $parameters = array() ): mixed {
		if ( is_string( $target ) && str_contains( $target, '::' ) ) {
			[$class, $method] = explode( '::', $target );
			$target           = $this->resolve( $class );
		}

		if ( ! is_object( $target ) ) {
			$target = $this->resolve( $target );
		}

		$reflector    = new \ReflectionMethod( $target, $method );
		$dependencies = $this->resolveDependencies( $reflector->getParameters(), $parameters );

		return $reflector->invokeArgs( $target, $dependencies );
	}

	/**
	 * Create a new instance with fresh dependencies (not singleton)
	 *
	 * @param string               $abstract   The class to instantiate
	 * @param array<string, mixed> $parameters Additional parameters
	 * @return object The new instance
	 */
	public function make( string $abstract, array $parameters = array() ): object {
		$concrete = $this->bindings[ $abstract ] ?? $abstract;

		return $this->build( $concrete, $parameters );
	}

	/**
	 * Reset the container (useful for testing)
	 */
	public function reset(): void {
		$this->bindings   = array();
		$this->instances  = array();
		$this->singletons = array();
	}
}
