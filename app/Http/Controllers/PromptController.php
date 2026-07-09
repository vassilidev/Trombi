<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Services\Prompt\PromptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromptController extends Controller
{
    public function __construct(private readonly PromptService $prompts) {}

    public function index(): Response
    {
        $values = $this->prompts->placeholderValues();

        $descriptions = [
            '{{VOCABULAIRE}}' => 'Remplacé par la liste des valeurs autorisées de la taxonomie.',
            '{{TAGS}}' => 'Remplacé par la liste des tags autorisés (parsing uniquement).',
        ];

        return Inertia::render('Prompts', [
            'prompts' => Prompt::orderBy('id')->get()->map(fn (Prompt $p) => [
                'id' => $p->id,
                'key' => $p->key,
                'label' => $p->label,
                'content' => $p->content,
                'version' => $p->version,
                'updated_at' => $p->updated_at?->toDateTimeString(),
            ]),
            'defaults' => collect(PromptService::defaults())
                ->map(fn (array $d, string $key) => ['key' => $key, 'content' => $d['content']])
                ->values(),
            'placeholders' => collect($values)
                ->map(fn (string $value, string $token) => [
                    'token' => $token,
                    'desc' => $descriptions[$token] ?? '',
                    'value' => $value,
                ])
                ->values(),
        ]);
    }

    public function update(Request $request, Prompt $prompt): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:20'],
        ]);

        $prompt->update([
            'content' => $validated['content'],
            'version' => $prompt->version + 1,
        ]);

        return back()->with('flash', [
            'message' => "Prompt « {$prompt->label} » enregistré (version {$prompt->version}).",
        ]);
    }
}
