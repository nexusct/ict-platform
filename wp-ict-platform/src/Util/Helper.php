<?php

declare(strict_types=1);

namespace ICT_Platform\Util;

/**
 * Helper utility class
 *
 * Provides utility functions used throughout the plugin.
 * Refactored from ICT_Helper to use PSR-4 namespacing and dependency injection.
 *
 * @package ICT_Platform\Util
 * @since   2.0.0
 */
class Helper
{
    /**
     * Currency symbols mapping
     *
     * @var array<string, string>
     */
    private const CURRENCY_SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥',
        'CHF' => 'CHF',
        'NZD' => 'NZ$',
    ];

    /**
     * Valid sync statuses
     *
     * @var array<string>
     */
    private const SYNC_STATUSES = ['pending', 'syncing', 'synced', 'error', 'conflict'];

    /**
     * Format currency value
     *
     * @param float       $amount   The amount to format
     * @param string|null $currency Currency code (default: from options)
     * @return string Formatted currency string
     */
    public function formatCurrency(float $amount, ?string $currency = null): string
    {
        $currency ??= get_option('ict_currency', 'USD');
        $symbol = self::CURRENCY_SYMBOLS[$currency] ?? $currency . ' ';

        return $symbol . number_format($amount, 2, '.', ',');
    }

    /**
     * Calculate hours between two timestamps
     *
     * @param string $start Start datetime
     * @param string $end   End datetime
     * @return float Hours difference
     */
    public function calculateHours(string $start, string $end): float
    {
        $startTime = strtotime($start);
        $endTime = strtotime($end);

        if ($startTime === false || $endTime === false || $endTime < $startTime) {
            return 0.0;
        }

        $diffSeconds = $endTime - $startTime;
        return round($diffSeconds / 3600, 2);
    }

    /**
     * Round time to nearest interval
     *
     * @param string $time     Time to round
     * @param int    $interval Minutes interval (default: 15)
     * @return string Rounded time
     */
    public function roundTime(string $time, int $interval = 15): string
    {
        $timestamp = strtotime($time);

        if ($timestamp === false) {
            return $time;
        }

        $minutes = (int) date('i', $timestamp);
        $hours = (int) date('H', $timestamp);

        $roundedMinutes = (int) round($minutes / $interval) * $interval;

        if ($roundedMinutes >= 60) {
            $hours++;
            $roundedMinutes = 0;
        }

        return date('Y-m-d', $timestamp) . ' ' . sprintf('%02d:%02d:00', $hours, $roundedMinutes);
    }

    /**
     * Check if time entry is overtime
     *
     * @param int    $technicianId Technician user ID
     * @param float  $hours        Hours worked
     * @param string $date         Date to check
     * @return bool True if overtime
     */
    public function isOvertime(int $technicianId, float $hours, string $date): bool
    {
        global $wpdb;

        $threshold = (float) get_option('ict_overtime_threshold', 8);

        $totalHours = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_hours) FROM " . ICT_TIME_ENTRIES_TABLE . "
                WHERE technician_id = %d
                AND DATE(clock_in) = %s",
                $technicianId,
                $date
            )
        );

        $totalHours = (float) $totalHours + $hours;

        return $totalHours > $threshold;
    }

    /**
     * Sanitize sync status
     *
     * @param string $status Status value
     * @return string Sanitized status
     */
    public function sanitizeSyncStatus(string $status): string
    {
        return in_array($status, self::SYNC_STATUSES, true) ? $status : 'pending';
    }

    /**
     * Generate unique project number
     *
     * @return string Project number
     */
    public function generateProjectNumber(): string
    {
        global $wpdb;

        $prefix = get_option('ict_project_number_prefix', 'PRJ');
        $year = date('Y');

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . ICT_PROJECTS_TABLE . "
            WHERE YEAR(created_at) = " . $year
        );

        $number = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return $prefix . '-' . $year . '-' . $number;
    }

    /**
     * Generate unique PO number
     *
     * @return string PO number
     */
    public function generatePoNumber(): string
    {
        global $wpdb;

        $prefix = get_option('ict_po_number_prefix', 'PO');
        $year = date('Y');

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . ICT_PURCHASE_ORDERS_TABLE . "
            WHERE YEAR(created_at) = " . $year
        );

        $number = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return $prefix . '-' . $year . '-' . $number;
    }

    /**
     * Sanitize and validate coordinates
     *
     * @param string|array<float> $coords Coordinates (lat,lng string or array)
     * @return string|null Sanitized coordinates or null
     */
    public function sanitizeCoordinates(string|array $coords): ?string
    {
        if (is_string($coords)) {
            $coords = explode(',', $coords);
        }

        if (!is_array($coords) || count($coords) !== 2) {
            return null;
        }

        $lat = (float) trim((string) $coords[0]);
        $lng = (float) trim((string) $coords[1]);

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return $lat . ',' . $lng;
    }

    /**
     * Get user's full name
     *
     * @param int $userId User ID
     * @return string Full name or username
     */
    public function getUserDisplayName(int $userId): string
    {
        $user = get_userdata($userId);

        if (!$user) {
            return __('Unknown User', 'ict-platform');
        }

        $firstName = get_user_meta($userId, 'first_name', true);
        $lastName = get_user_meta($userId, 'last_name', true);

        if ($firstName || $lastName) {
            return trim($firstName . ' ' . $lastName);
        }

        return $user->display_name;
    }

    /**
     * Get currency symbols
     *
     * @return array<string, string>
     */
    public function getCurrencySymbols(): array
    {
        return self::CURRENCY_SYMBOLS;
    }

    /**
     * Get valid sync statuses
     *
     * @return array<string>
     */
    public function getSyncStatuses(): array
    {
        return self::SYNC_STATUSES;
    }
}
