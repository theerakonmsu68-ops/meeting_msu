<?php

if (!function_exists('safeHttpUrl')) {
    function safeHttpUrl(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return in_array($scheme, ['http', 'https'], true) ? $value : '';
    }
}
