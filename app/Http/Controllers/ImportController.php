<?php

namespace App\Http\Controllers;

use App\Http\Resources\TalentCardResource;
use App\Models\Talent;
use App\Services\Import\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $import) {}

    public function index(): Response
    {
        return Inertia::render('Import', [
            'talents' => TalentCardResource::collection(
                Talent::with(['photos', 'appearance'])->latest('id')->limit(24)->get()
            ),
            'stats' => [
                'total' => Talent::count(),
                'analyzed' => Talent::whereHas('appearance')->count(),
                'gold' => Talent::where('is_gold', true)->count(),
            ],
        ]);
    }

    /**
     * Upload local : un ou plusieurs fichiers image.
     */
    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:100'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ]);

        $imported = 0;
        $skipped = 0;

        foreach ($validated['photos'] as $photo) {
            $this->import->fromUploadedFile($photo) !== null ? $imported++ : $skipped++;
        }

        return back()->with('flash', [
            'message' => "Upload : {$imported} importé(s), {$skipped} doublon(s) ignoré(s).",
        ]);
    }

    /**
     * Pull API : récupère N visages depuis thispersondoesnotexist.com.
     */
    public function pull(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->import->pullFromApi($validated['count']);

        return back()->with('flash', [
            'message' => "Pull API : {$result['imported']} importé(s), {$result['skipped']} doublon(s), {$result['failed']} échec(s).",
        ]);
    }
}
