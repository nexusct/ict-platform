<?php

declare(strict_types=1);

namespace ICT_Platform\Util;

/**
 * Cache utility class
 *
 * Provides caching functionality using WordPress transients and object cache.
 *
 * @package ICT_Platform\Util
 * @since   2.0.0
 */
class Cache
{
    /**
     * Cache prefix
     */
    private const PREFIX = 'ict_';

    /**
     * Default TTL in seconds (1 hour)
     */
    private const DEFAULT_TTL = 3600;

    /**
     * Cache group for object cache
     */
    private string $group;

    /**
     * Constructor
     *
     * @param string $group Cache group name
     */
    public function __construct(string $group = 'ict_platform')
    {
        $this->group = $group;
    }

    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found
     */
    public function get(string $key): mixed
    {
        $prefixedKey = $this->prefixKey($key);

        // Try object cache first
        if (wp_using_ext_object_cache()) {
            $value = wp_cache_get($prefixedKey, $this->group);
            return $value !== false ? $value : null;
        }

        // Fall back to transients
        $value = get_transient($prefixedKey);
        return $value !== false ? $value : null;
    }

    /**
     * Set a value in cache
     *
     * @param string $key   Cache key
     * @param mixed  $value Value to cache
     * @param int    $ttl   Time to live in seconds
     * @return bool True on success
     */
    public function set(string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool
    {
        $prefixedKey = $this->prefixKey($key);

        // Use object cache if available
        if (wp_using_ext_object_cache()) {
            return wp_cache_set($prefixedKey, $value, $this->group, $ttl);
        }

        // Fall back to transients
        return set_transient($prefixedKey, $value, $ttl);
    }

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool True on success
     */
    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefixKey($key);

        // Delete from object cache
        if (wp_using_ext_object_cache()) {
            return wp_cache_delete($prefixedKey, $this->group);
        }

        // Delete transient
        return delete_transient($prefixedKey);
    }

    /**
     * Check if a key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get or set a cached value
     *
     * @param string   $key      Cache key
     * @param callable $callback Callback to generate value if not cached
     * @param int      $ttl      Time to live in seconds
     * @return mixed The cached or generated value
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get or set a cached value forever (no expiration)
     *
     * @param string   $key      Cache key
     * @param callable $callback Callback to generate value if not cached
     * @return mixed The cached or generated value
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, $callback, 0);
    }

    /**
     * Flush all cache entries with our prefix
     *
     * @return bool True on success
     */
    public function flush(): bool
    {
        global $wpdb;

        // Flush object cache group if available
        if (wp_using_ext_object_cache()) {
            return wp_cache_flush_group($this->group);
        }

        // Delete all our transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . self::PREFIX . '%',
                '_transient_timeout_' . self::PREFIX . '%'
            )
        );

        return true;
    }

    /**
     * Increment a numeric cache value
     *
     * @param string $key    Cache key
     * @param int    $offset Amount to increment by
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $offset = 1): int|false
    {
        $prefixedKey = $this->prefixKey($key);

        if (wp_using_ext_object_cache()) {
            return wp_cache_incr($prefixedKey, $offset, $this->group);
        }

        // Manual increment for transients
        $value = $this->get($key);
        if ($value === null) {
            return false;
        }

        $newValue = (int) $value + $offset;
        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * Decrement a numeric cache value
     *
     * @param string $key    Cache key
     * @param int    $offset Amount to decrement by
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $offset = 1): int|false
    {
        $prefixedKey = $this->prefixKey($key);

        if (wp_using_ext_object_cache()) {
            return wp_cache_decr($prefixedKey, $offset, $this->group);
        }

        // Manual decrement for transients
        $value = $this->get($key);
        if ($value === null) {
            return false;
        }

        $newValue = (int) $value - $offset;
        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * Add prefix to cache key
     *
     * @param string $key Original key
     * @return string Prefixed key
     */
    private function prefixKey(string $key): string
    {
        return self::PREFIX . $key;
    }

    /**
     * Generate a cache key from arguments
     *
     * @param string       $prefix Key prefix
     * @param array<mixed> $args   Arguments to hash
     * @return string Cache key
     */
    public static function makeKey(string $prefix, array $args = []): string
    {
        if (empty($args)) {
            return $prefix;
        }

        return $prefix . '_' . md5(serialize($args));
    }
}
