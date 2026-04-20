<?php

namespace KP\AgentReady\Modules;

/**
 * Base class for all plugin modules.
 */
abstract class AbstractModule
{

    /** @param array<string, mixed> $options Loaded plugin option array. */
    public function __construct(protected array $options) {}

    abstract public function register(): void;

    /**
     * Retrieve an option value with a fallback default.
     *
     * @param string $key     Option field ID.
     * @param mixed  $default Fallback value.
     */
    protected function opt(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
