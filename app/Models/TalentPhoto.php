<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TalentPhoto extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'talent_id',
        'path',
        'image_hash',
        'is_primary',
        'width',
        'height',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Talent, $this>
     */
    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    /**
     * URL publique servie via le disque public.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
