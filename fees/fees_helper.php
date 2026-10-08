<?php
/**
 * fees/fees_helper.php — Database & Layout Compatibility Helper for Fees Module
 * Arsalan Model School and College Management System
 */

if (!function_exists('fee_db_fetch_all')) {
    function fee_db_fetch_all(string $sql, array $params = [], string $types = ''): array {
        return db_fetch_all($sql, $types, $params);
    }
}

if (!function_exists('fee_db_fetch_one')) {
    function fee_db_fetch_one(string $sql, array $params = [], string $types = ''): ?array {
        return db_fetch_one($sql, $types, $params);
    }
}

if (!function_exists('fee_db_execute')) {
    function fee_db_execute(string $sql, array $params = [], string $types = ''): mixed {
        return db_execute($sql, $types, $params);
    }
}

if (!function_exists('fee_db_last_id')) {
    function fee_db_last_id(): int {
        return (int)get_db()->insert_id;
    }
}

if (!function_exists('fee_render_flash')) {
    function fee_render_flash(): void {
        if (function_exists('render_flash')) {
            render_flash();
        }
    }
}
