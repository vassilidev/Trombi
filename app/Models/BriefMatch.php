<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefMatch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'brief_id',
        'talent_id',
        'score',
        'rank',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'rank' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Brief, $this>
     */
    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    /**
     * @return BelongsTo<Talent, $this>
     */
    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
