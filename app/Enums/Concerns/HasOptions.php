<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Shared helpers for string-backed taxonomy enums.
 *
 * Every taxonomy enum is the single source of truth for the values the AI is
 * allowed to produce and the values the search parser is allowed to extract.
 */
trait HasOptions
{
    /**
     * The raw allowed values, e.g. ['femme', 'homme', ...].
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Human readable label, derived from the value (POC-grade, no i18n).
     */
    public function label(): string
    {
        return Str::of($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * Value/label pairs for building selects on the front-end.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * Resolve a case from a raw value, or null if it is not in the taxonomy.
     */
    public static function tryFromValue(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
