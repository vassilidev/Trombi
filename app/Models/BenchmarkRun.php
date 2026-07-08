<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BenchmarkRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'label',
        'prompt_version',
        'models',
        'gold_count',
    ];

    protected function casts(): array
    {
        return [
            'models' => 'array',
            'gold_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<BenchmarkResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(BenchmarkResult::class, 'run_id');
    }
}
