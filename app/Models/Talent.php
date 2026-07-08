<?php

namespace App\Models;

use Database\Factories\TalentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Talent extends Model
{
    /** @use HasFactory<TalentFactory> */
    use HasFactory;

    // "talent" est traité comme invariable par le pluralizer : on fixe la table.
    protected $table = 'talents';

    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'location',
        'source',
        'is_gold',
        'is_active',
        'image_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_gold' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TalentPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(TalentPhoto::class);
    }

    /**
     * @return HasOne<TalentPhoto, $this>
     */
    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(TalentPhoto::class)->where('is_primary', true);
    }

    /**
     * @return HasOne<TalentAppearance, $this>
     */
    public function appearance(): HasOne
    {
        return $this->hasOne(TalentAppearance::class);
    }

    /**
     * @return HasOne<TalentProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(TalentProfile::class);
    }

    /**
     * @return HasMany<Annotation, $this>
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'talent_tag');
    }

    /**
     * Talents pas encore profilés (analyse IA + embedding restant à faire).
     *
     * @param  Builder<Talent>  $query
     */
    public function scopePendingAnalysis(Builder $query): void
    {
        $query->whereDoesntHave('profile');
    }

    /**
     * Nom affichable (« Prénom Nom ») si renseigné, sinon null.
     */
    public function displayName(): ?string
    {
        $name = trim("{$this->first_name} {$this->last_name}");

        return $name === '' ? null : $name;
    }

    /**
     * URL publique de la photo principale (ou la première trouvée).
     */
    public function displayPhotoUrl(): ?string
    {
        return $this->displayPhoto()?->url();
    }

    /**
     * Chemin storage de la photo principale (ou la première trouvée).
     */
    public function displayPhotoPath(): ?string
    {
        return $this->displayPhoto()?->path;
    }

    private function displayPhoto(): ?TalentPhoto
    {
        return $this->photos->firstWhere('is_primary', true) ?? $this->photos->first();
    }
}
