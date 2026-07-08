<?php

namespace App\Http\Resources;

use App\Models\Talent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Carte talent légère pour les listes (import, qualification, résultats).
 *
 * @mixin Talent
 */
class TalentCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->displayName(),
            'location' => $this->location,
            'source' => $this->source,
            'is_gold' => $this->is_gold,
            'photo_url' => $this->displayPhotoUrl(),
            'photos_count' => $this->relationLoaded('photos')
                ? $this->photos->count()
                : $this->photos()->count(),
            'is_analyzed' => $this->relationLoaded('appearance')
                ? $this->appearance !== null
                : $this->appearance()->exists(),
        ];
    }
}
