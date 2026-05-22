<?php
/**
 * Base test case for all Git Updater tests.
 *
 * Handles save/restore of singleton state to prevent cross-test contamination.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Plugin;
use Fragen\Git_Updater\Theme;
use Fragen\Git_Updater\Branch;
use Fragen\Git_Updater\API\API;
use Fragen\Singleton;

abstract class GU_Test_Case extends WP_UnitTestCase {

	/** @var array<string, mixed> */
	private array $saved_base_options = [];

	/** @var array<string, stdClass> */
	private array $saved_plugin_config = [];

	/** @var array<string, stdClass> */
	private array $saved_theme_config = [];

	/** @var array<string, mixed> */
	private array $saved_branch_options = [];

	/** @var string|stdClass|null */
	private $saved_api_type = null;

	public function set_up(): void {
		parent::set_up();

		// Snapshot static state before tests mutate anything.
		$this->saved_base_options   = Base::$options;
		$this->saved_branch_options = $this->read_static_property( Branch::class, 'options' ) ?? [];

		$this->saved_plugin_config = $this->read_instance_config( Plugin::class );
		$this->saved_theme_config  = $this->read_instance_config( Theme::class );

		// API singleton may not exist yet; read if it does.
		$api = $this->get_singleton_if_exists( API::class );
		if ( null !== $api ) {
			$this->saved_api_type = $this->read_property( $api, 'type' );
		}
	}

	public function tear_down(): void {
		// Restore Base static options.
		Base::$options = $this->saved_base_options;

		// Restore Branch static options.
		$this->write_static_property( Branch::class, 'options', $this->saved_branch_options );

		// Restore Plugin/Theme config on singleton instances.
		$this->restore_instance_config( Plugin::class, $this->saved_plugin_config );
		$this->restore_instance_config( Theme::class, $this->saved_theme_config );

		// Restore API::$type if it was set.
		if ( null !== $this->saved_api_type ) {
			$api = $this->get_singleton_if_exists( API::class );
			if ( null !== $api ) {
				$this->write_property( $api, 'type', $this->saved_api_type );
			}
		}

		// Wipe the singleton cache so next test gets fresh instances.
		Singleton::reset();

		parent::tear_down();
	}

	// ---------------------------------------------------------------------------
	// Public mutators — for test methods to inject state during a test.
	// Save/restore is handled automatically in set_up() / tear_down().
	// ---------------------------------------------------------------------------

	/**
	 * Set Branch::$options (protected static).
	 *
	 * @param array<string, mixed> $options
	 */
	public function set_branch_options( array $options ): void {
		$this->write_static_property( Branch::class, 'options', $options );
	}

	/**
	 * Read Branch::$options (protected static).
	 *
	 * @return array<string, mixed>
	 */
	public function get_branch_options(): array {
		return $this->read_static_property( Branch::class, 'options' ) ?? [];
	}

	/**
	 * Inject config into the Plugin singleton.
	 *
	 * @param array<string, stdClass> $config
	 */
	public function set_plugin_config( array $config ): void {
		$this->restore_instance_config( Plugin::class, $config );
	}

	/**
	 * Inject config into the Theme singleton.
	 *
	 * @param array<string, stdClass> $config
	 */
	public function set_theme_config( array $config ): void {
		$this->restore_instance_config( Theme::class, $config );
	}

	/**
	 * Read current config from the Plugin singleton.
	 *
	 * @return array<string, stdClass>
	 */
	public function get_plugin_config(): array {
		return $this->read_instance_config( Plugin::class );
	}

	/**
	 * Read current config from the Theme singleton.
	 *
	 * @return array<string, stdClass>
	 */
	public function get_theme_config(): array {
		return $this->read_instance_config( Theme::class );
	}

	// ---------------------------------------------------------------------------
	// Reflection helpers
	// ---------------------------------------------------------------------------

	/**
	 * @param string $class
	 * @param string $prop
	 * @return mixed
	 */
	private function read_static_property( string $class, string $prop ): mixed {
		try {
			$rp = new ReflectionProperty( $class, $prop );
			$rp->setAccessible( true );
			return $rp->getValue( null );
		} catch ( ReflectionException ) {
			return null;
		}
	}

	/**
	 * @param string $class
	 * @param string $prop
	 * @param mixed  $value
	 */
	private function write_static_property( string $class, string $prop, mixed $value ): void {
		try {
			$rp = new ReflectionProperty( $class, $prop );
			$rp->setAccessible( true );
			$rp->setValue( null, $value );
		} catch ( ReflectionException ) {
			// Property not found — skip.
		}
	}

	/**
	 * @param object $obj
	 * @param string $prop
	 * @return mixed
	 */
	private function read_property( object $obj, string $prop ): mixed {
		try {
			$rp = new ReflectionProperty( $obj, $prop );
			$rp->setAccessible( true );
			return $rp->getValue( $obj );
		} catch ( ReflectionException ) {
			return null;
		}
	}

	/**
	 * @param object $obj
	 * @param string $prop
	 * @param mixed  $value
	 */
	private function write_property( object $obj, string $prop, mixed $value ): void {
		try {
			$rp = new ReflectionProperty( $obj, $prop );
			$rp->setAccessible( true );
			$rp->setValue( $obj, $value );
		} catch ( ReflectionException ) {
			// Property not found — skip.
		}
	}

	/**
	 * @param string $class FQCN or short name relative to caller namespace.
	 * @return object|null
	 */
	private function get_singleton_if_exists( string $class ): ?object {
		if ( ! class_exists( $class ) && ! class_exists( 'Fragen\\Git_Updater\\' . $class ) ) {
			return null;
		}
		try {
			return Singleton::get_instance( $class, $this );
		} catch ( \Throwable ) {
			return null;
		}
	}

	/**
	 * @param string $class
	 * @return array<string, stdClass>
	 */
	private function read_instance_config( string $class ): array {
		$singleton = $this->get_singleton_if_exists( $class );
		if ( null === $singleton ) {
			return [];
		}
		return $this->read_property( $singleton, 'config' ) ?? [];
	}

	/**
	 * @param string                  $class
	 * @param array<string, stdClass> $saved
	 */
	private function restore_instance_config( string $class, array $saved ): void {
		$singleton = $this->get_singleton_if_exists( $class );
		if ( null !== $singleton ) {
			$this->write_property( $singleton, 'config', $saved );
		}
	}
}
