<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brief extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'raw_text',
        'source_kind',
        'parsed_filters',
        'semantic_text',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'query_embedding',
    ];

    protected function casts(): array
    {
        return [
            'parsed_filters' => 'array',
        ];
    }

    /**
     * @return HasMany<BriefMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(BriefMatch::class);
    }
}
