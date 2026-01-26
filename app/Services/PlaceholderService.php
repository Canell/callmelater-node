<?php

namespace App\Services;

class PlaceholderService
{
    /**
     * Extract placeholder names from a string.
     * Placeholders use the format {{name}}.
     *
     * @return array<string> List of unique placeholder names
     */
    public function extractPlaceholders(string $text): array
    {
        preg_match_all('/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $text, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Substitute placeholders in a string with values.
     * Unmatched placeholders are kept as-is.
     */
    public function substitute(string $text, array $values): string
    {
        return preg_replace_callback(
            '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/',
            function ($matches) use ($values) {
                $key = $matches[1];

                // Return the value if it exists, otherwise keep the placeholder
                if (array_key_exists($key, $values)) {
                    return (string) $values[$key];
                }

                return $matches[0];
            },
            $text
        ) ?? $text;
    }

    /**
     * Recursively substitute placeholders in arrays/objects.
     */
    public function substituteDeep(mixed $data, array $values): mixed
    {
        if (is_string($data)) {
            return $this->substitute($data, $values);
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                // Also substitute in keys if they're strings
                $newKey = is_string($key) ? $this->substitute($key, $values) : $key;
                $result[$newKey] = $this->substituteDeep($value, $values);
            }

            return $result;
        }

        // Return non-string, non-array values as-is (int, bool, null, etc.)
        return $data;
    }

    /**
     * Validate that all required placeholders have values.
     *
     * @param  array<array<string, mixed>>  $placeholderDefs  Placeholder definitions
     * @param  array<string, mixed>  $providedParams  Provided parameter values
     * @return array<string, string> Errors keyed by placeholder name
     */
    public function validateRequired(array $placeholderDefs, array $providedParams): array
    {
        $errors = [];

        foreach ($placeholderDefs as $def) {
            $name = $def['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }
            $isRequired = $def['required'] ?? false;

            if ($isRequired && ! array_key_exists($name, $providedParams)) {
                $errors[$name] = "Required parameter '{$name}' is missing.";
            }
        }

        return $errors;
    }

    /**
     * Apply default values for missing optional placeholders.
     *
     * @param  array<array<string, mixed>>  $placeholderDefs  Placeholder definitions
     * @param  array<string, mixed>  $providedParams  Provided parameter values
     * @return array<string, mixed> Parameters with defaults applied
     */
    public function applyDefaults(array $placeholderDefs, array $providedParams): array
    {
        foreach ($placeholderDefs as $def) {
            $name = $def['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }

            // Only apply default if key is not present and default is defined
            if (! array_key_exists($name, $providedParams) && array_key_exists('default', $def)) {
                $providedParams[$name] = $def['default'];
            }
        }

        return $providedParams;
    }

    /**
     * Extract all placeholders from a template configuration.
     * Scans request_config, gate_config, and name.
     *
     * @return array<string> List of unique placeholder names found
     */
    public function extractFromConfig(array $config): array
    {
        $allPlaceholders = [];

        // Check name
        if (isset($config['name']) && is_string($config['name'])) {
            $allPlaceholders = array_merge($allPlaceholders, $this->extractPlaceholders($config['name']));
        }

        // Check request_config
        if (isset($config['request_config'])) {
            $allPlaceholders = array_merge($allPlaceholders, $this->extractFromArray($config['request_config']));
        }

        // Check gate_config
        if (isset($config['gate_config'])) {
            $allPlaceholders = array_merge($allPlaceholders, $this->extractFromArray($config['gate_config']));
        }

        return array_unique($allPlaceholders);
    }

    /**
     * Recursively extract placeholders from an array.
     *
     * @return array<string>
     */
    private function extractFromArray(mixed $data): array
    {
        $placeholders = [];

        if (is_string($data)) {
            return $this->extractPlaceholders($data);
        }

        if (is_array($data)) {
            foreach ($data as $value) {
                $placeholders = array_merge($placeholders, $this->extractFromArray($value));
            }
        }

        return $placeholders;
    }
}
