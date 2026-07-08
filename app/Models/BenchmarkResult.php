<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenchmarkResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'run_id',
        'talent_id',
        'model',
        'payload',
        'is_valid_json',
        'agreement_score',
        'per_field_result',
        'latency_ms',
        'cost_usd',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'per_field_result' => 'array',
            'is_valid_json' => 'boolean',
            'agreement_score' => 'float',
            'cost_usd' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<BenchmarkRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(BenchmarkRun::class, 'run_id');
    }

    /**
     * @return BelongsTo<Talent, $this>
     */
    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
