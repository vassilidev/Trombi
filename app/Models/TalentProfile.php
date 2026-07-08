<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentProfile extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'talent_id';

    protected $keyType = 'int';

    protected $fillable = [
        'talent_id',
        'description_fr',
        'searchable_text',
        'model_used',
        'embedded_at',
    ];

    /**
     * Le vecteur brut n'est jamais sérialisé vers le front.
     *
     * @var list<string>
     */
    protected $hidden = [
        'description_embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedded_at' => 'datetime',
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
