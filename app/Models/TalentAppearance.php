<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentAppearance extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'talent_id';

    protected $keyType = 'int';

    protected $fillable = [
        'talent_id',
        'genre',
        'age_min',
        'age_max',
        'type_percu',
        'carnation',
        'cheveux_couleur',
        'cheveux_longueur',
        'cheveux_texture',
        'yeux_couleur',
        'forme_visage',
        'pilosite',
        'expression',
        'morphologie',
        'source_label',
        'raw_analysis',
        'model_used',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'age_min' => 'integer',
            'age_max' => 'integer',
            'raw_analysis' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Talent, $this>
     */
    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
