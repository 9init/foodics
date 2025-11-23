<?php

if (!function_exists('trace_id')) {
    /**
     * Get the current request trace ID
     *
     * Format: TRC-YYYYMMDD-SERVERID-NNNNNN
     * Example: TRC-20251123-A1B2C3D4-000001
     *
     * The SERVERID ensures global uniqueness in distributed environments.
     *
     * @return string|null
     */
    function trace_id(): ?string
    {
        if (request()->attributes->has('trace_id')) {
            return request()->attributes->get('trace_id');
        }

        return config('app.trace_id');
    }
}

if (!function_exists('set_trace_id')) {
    /**
     * Set the current request trace ID
     *
     * @param string $traceId
     * @return void
     */
    function set_trace_id(string $traceId): void
    {
        if (app()->has('request') && app('request')) {
            try {
                request()->attributes->set('trace_id', $traceId);
            } catch (\Throwable $e) {
            }
        }

        config(['app.trace_id' => $traceId]);
    }
}

if (!function_exists('with_trace')) {
    /**
     * Add trace ID to log context array
     *
     * @param array $context
     * @return array
     */
    function with_trace(array $context = []): array
    {
        return array_merge(['trace_id' => trace_id()], $context);
    }
}
