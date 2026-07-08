<?php

namespace App\Services\Import;

use App\Models\Talent;
use App\Models\TalentPhoto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import multi-source des visages (upload local + pull API TPDNE).
 * Import et analyse sont découplés : ici on ne fait que stocker + dédupe (PRD §5.1).
 */
class ImportService
{
    private const TPDNE_URL = 'https://thispersondoesnotexist.com/random-person.jpeg';

    private const DISK = 'public';

    /**
     * Stocke une image binaire : dédupe par sha256 (au niveau photo), crée talent + photo.
     * Retourne le talent créé, ou null si l'image est un doublon.
     */
    public function storeImage(string $binary, string $source = 'tpdne'): ?Talent
    {
        $hash = hash('sha256', $binary);

        if ($this->isDuplicate($hash)) {
            return null;
        }

        return DB::transaction(function () use ($binary, $hash, $source): Talent {
            $code = $this->nextCode();
            $talent = Talent::create([
                'code' => $code,
                'source' => $source,
                'image_hash' => $hash,
            ]);

            $this->attachPhoto($talent, $binary, $hash, $source, isPrimary: true, code: $code);

            return $talent;
        });
    }

    /**
     * Ajoute une photo à un talent existant. Devient principale si c'est la première.
     * Retourne la photo, ou null si doublon.
     */
    public function addPhotoToTalent(Talent $talent, string $binary, string $source = 'upload'): ?TalentPhoto
    {
        $hash = hash('sha256', $binary);

        if ($this->isDuplicate($hash)) {
            return null;
        }

        $isPrimary = $talent->photos()->count() === 0;

        return $this->attachPhoto($talent, $binary, $hash, $source, $isPrimary, $talent->code);
    }

    /**
     * Import depuis un fichier uploadé (nouveau talent).
     */
    public function fromUploadedFile(UploadedFile $file): ?Talent
    {
        $binary = file_get_contents($file->getRealPath());

        return $binary === false ? null : $this->storeImage($binary, 'upload');
    }

    private function isDuplicate(string $hash): bool
    {
        return TalentPhoto::where('image_hash', $hash)->exists();
    }

    /**
     * Écrit le fichier + la ligne talent_photos.
     */
    private function attachPhoto(
        Talent $talent,
        string $binary,
        string $hash,
        string $source,
        bool $isPrimary,
        string $code,
    ): TalentPhoto {
        [$width, $height] = $this->dimensions($binary);
        $path = 'talents/'.$code.'-'.substr($hash, 0, 8).'.jpg';

        Storage::disk(self::DISK)->put($path, $binary);

        return $talent->photos()->create([
            'path' => $path,
            'image_hash' => $hash,
            'is_primary' => $isPrimary,
            'width' => $width,
            'height' => $height,
            'source' => $source,
        ]);
    }

    /**
     * Pull de N visages depuis thispersondoesnotexist.com.
     * Délai + jitter entre les hits pour éviter de récupérer le même visage.
     *
     * @return array{imported: int, skipped: int, failed: int}
     */
    public function pullFromApi(int $count): array
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($i > 0) {
                // 1 à 2s de délai + jitter (le site régénère à intervalle régulier).
                usleep(random_int(1_000_000, 2_000_000));
            }

            try {
                $binary = $this->fetchOneFace();
            } catch (ConnectionException) {
                $failed++;

                continue;
            }

            if ($binary === null) {
                $failed++;

                continue;
            }

            $this->storeImage($binary, 'tpdne') !== null ? $imported++ : $skipped++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * Un hit API avec retry/backoff. Null si échec, ou si la réponse n'est pas une
     * image (le site sert parfois une page HTML anti-bot : on ne stocke jamais ça).
     */
    private function fetchOneFace(): ?string
    {
        $response = Http::retry(3, 800, throw: false)
            ->timeout(20)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 TrombiBot'])
            ->get(self::TPDNE_URL);

        if (! $response->successful()) {
            return null;
        }

        $contentType = $response->header('Content-Type');

        return str_contains(strtolower($contentType), 'image') ? $response->body() : null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(string $binary): array
    {
        $info = @getimagesizefromstring($binary);

        return $info === false ? [null, null] : [$info[0], $info[1]];
    }

    /**
     * Code lisible séquentiel : TAL-0001, TAL-0002...
     * Suffixe aléatoire en cas de course pour garantir l'unicité.
     */
    private function nextCode(): string
    {
        $next = (Talent::max('id') ?? 0) + 1;
        $code = 'TAL-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        while (Talent::where('code', $code)->exists()) {
            $code = 'TAL-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(3));
            $next++;
        }

        return $code;
    }
}
