<?php

namespace Database\Factories;

use App\Models\Talent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Talent>
 */
class TalentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'TAL-'.Str::upper(Str::random(6)),
            'source' => 'tpdne',
            'is_gold' => false,
            'is_active' => true,
            'image_hash' => hash('sha256', Str::random(40)),
        ];
    }

    /**
     * Talent faisant partie du set gold (annoté à la main).
     */
    public function gold(): static
    {
        return $this->state(fn (array $attributes): array => ['is_gold' => true]);
    }
}
