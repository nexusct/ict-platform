<?php

declare(strict_types=1);

/**
 * Global helper functions
 *
 * Provides backward-compatible global functions that wrap the new namespaced classes.
 *
 * @package ICT_Platform
 * @since   2.0.0
 */

use ICT_Platform\Container\Container;
use ICT_Platform\Util\Helper;
use ICT_Platform\Util\Cache;
use ICT_Platform\Util\SyncLogger;

if (!function_exists('ict_container')) {
    /**
     * Get the DI container instance
     *
     * @return Container
     */
    function ict_container(): Container
    {
        return Container::getInstance();
    }
}

if (!function_exists('ict_helper')) {
    /**
     * Get the Helper instance
     *
     * @return Helper
     */
    function ict_helper(): Helper
    {
        return ict_container()->resolve(Helper::class);
    }
}

if (!function_exists('ict_cache')) {
    /**
     * Get the Cache instance
     *
     * @return Cache
     */
    function ict_cache(): Cache
    {
        return ict_container()->resolve(Cache::class);
    }
}

if (!function_exists('ict_sync_logger')) {
    /**
     * Get the SyncLogger instance
     *
     * @return SyncLogger
     */
    function ict_sync_logger(): SyncLogger
    {
        return ict_container()->resolve(SyncLogger::class);
    }
}

if (!function_exists('ict_format_currency')) {
    /**
     * Format currency value (backward compatible)
     *
     * @param float       $amount   Amount to format
     * @param string|null $currency Currency code
     * @return string
     */
    function ict_format_currency(float $amount, ?string $currency = null): string
    {
        return ict_helper()->formatCurrency($amount, $currency);
    }
}

if (!function_exists('ict_calculate_hours')) {
    /**
     * Calculate hours between timestamps (backward compatible)
     *
     * @param string $start Start datetime
     * @param string $end   End datetime
     * @return float
     */
    function ict_calculate_hours(string $start, string $end): float
    {
        return ict_helper()->calculateHours($start, $end);
    }
}

if (!function_exists('ict_round_time')) {
    /**
     * Round time to interval (backward compatible)
     *
     * @param string $time     Time to round
     * @param int    $interval Interval in minutes
     * @return string
     */
    function ict_round_time(string $time, int $interval = 15): string
    {
        return ict_helper()->roundTime($time, $interval);
    }
}

if (!function_exists('ict_is_overtime')) {
    /**
     * Check if overtime (backward compatible)
     *
     * @param int    $technicianId Technician ID
     * @param float  $hours        Hours worked
     * @param string $date         Date
     * @return bool
     */
    function ict_is_overtime(int $technicianId, float $hours, string $date): bool
    {
        return ict_helper()->isOvertime($technicianId, $hours, $date);
    }
}

if (!function_exists('ict_generate_project_number')) {
    /**
     * Generate project number (backward compatible)
     *
     * @return string
     */
    function ict_generate_project_number(): string
    {
        return ict_helper()->generateProjectNumber();
    }
}

if (!function_exists('ict_generate_po_number')) {
    /**
     * Generate PO number (backward compatible)
     *
     * @return string
     */
    function ict_generate_po_number(): string
    {
        return ict_helper()->generatePoNumber();
    }
}

if (!function_exists('ict_sanitize_coordinates')) {
    /**
     * Sanitize coordinates (backward compatible)
     *
     * @param string|array $coords Coordinates
     * @return string|null
     */
    function ict_sanitize_coordinates(string|array $coords): ?string
    {
        return ict_helper()->sanitizeCoordinates($coords);
    }
}

if (!function_exists('ict_get_user_display_name')) {
    /**
     * Get user display name (backward compatible)
     *
     * @param int $userId User ID
     * @return string
     */
    function ict_get_user_display_name(int $userId): string
    {
        return ict_helper()->getUserDisplayName($userId);
    }
}

if (!function_exists('ict_log_sync')) {
    /**
     * Log sync activity (backward compatible)
     *
     * @param array $data Log data
     * @return int|false
     */
    function ict_log_sync(array $data): int|false
    {
        return ict_sync_logger()->log($data);
    }
}

if (!function_exists('ict_queue_sync')) {
    /**
     * Queue sync item (backward compatible)
     *
     * @param array $data Queue data
     * @return int|false
     */
    function ict_queue_sync(array $data): int|false
    {
        return ict_sync_logger()->queueSync($data);
    }
}

if (!function_exists('ict_cache_get')) {
    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @return mixed
     */
    function ict_cache_get(string $key): mixed
    {
        return ict_cache()->get($key);
    }
}

if (!function_exists('ict_cache_set')) {
    /**
     * Set cached value
     *
     * @param string $key   Cache key
     * @param mixed  $value Value
     * @param int    $ttl   TTL in seconds
     * @return bool
     */
    function ict_cache_set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return ict_cache()->set($key, $value, $ttl);
    }
}

if (!function_exists('ict_cache_delete')) {
    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @return bool
     */
    function ict_cache_delete(string $key): bool
    {
        return ict_cache()->delete($key);
    }
}

if (!function_exists('ict_cache_remember')) {
    /**
     * Get or set cached value
     *
     * @param string   $key      Cache key
     * @param callable $callback Callback
     * @param int      $ttl      TTL in seconds
     * @return mixed
     */
    function ict_cache_remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        return ict_cache()->remember($key, $callback, $ttl);
    }
}
