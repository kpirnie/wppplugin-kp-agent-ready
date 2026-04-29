<?php

/** 
 * AbstractModule
 * 
 * Base class for all plugin modules. Provides constructor injection of the
 * options array and the opt() convenience helper.
 * 
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 * 
 */

// setup the namespace
namespace KPAgentReady\Modules;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

/**
 * AbstractModule
 *
 * Base class for all plugin modules. Provides constructor injection of the
 * options array and the opt() convenience helper.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
abstract class AbstractModule
{

    /** @param array<string, mixed> $options Loaded plugin option array. */
    public function __construct(protected array $options) {}

    abstract public function register(): void;

    /**
     * opt
     *
     * Retrieves a single option value from the loaded options array,
     * returning a default if the key is not set.
     *
     * @since 1.0.0
     * @access protected
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $key     The option field ID to retrieve
     * @param mixed  $default Fallback value when the key is not present
     *
     * @return mixed The stored option value or the provided default
     *
     */
    protected function opt(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
