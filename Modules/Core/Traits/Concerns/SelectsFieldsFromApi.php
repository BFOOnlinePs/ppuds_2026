<?php

namespace Modules\Core\Traits\Concerns;

trait SelectsFieldsFromApi {
    /**
     * Filter the given data array by the request's 'fields' and 'except' query parameters.
     *
     * @param array $data The full data array to be filtered.
     * @return array The filtered data.
     */
    protected function filterFields(array $data): array
    {
        $request = request(); // Access the global request instance

        // Logic to include specific fields ('only', 'fields', 'fields.type')
        $rawFields = $request->query('fields')
            ?? $request->query('fields.' . $this->guessType())
            ?? $request->query('only');

        if ($rawFields) {
            $fields = is_array($rawFields) ? $rawFields : array_filter(array_map('trim', explode(',', (string) $rawFields)));
            if (!empty($fields)) {
                $data = array_intersect_key($data, array_flip($fields));
            }
        }

        // Logic to exclude specific fields ('except')
        $exceptRaw = $request->query('except');
        if ($exceptRaw) {
            $except = array_filter(array_map('trim', explode(',', (string) $exceptRaw)));
            if (!empty($except)) {
                $data = array_diff_key($data, array_flip($except));
            }
        }

        return $data;
    }

    /**
     * Guess the resource type from the resource class name.
     * e.g., BranchResource -> "users"
     */
    protected function guessType(): string
    {
        $class = class_basename(static::class);
        return str($class)->beforeLast('Resource')->plural()->lower()->value();
    }
}
