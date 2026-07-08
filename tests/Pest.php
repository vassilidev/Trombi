<?php

use App\Models\Talent;
use App\Services\Import\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fake une réponse OpenRouter chat/completions renvoyant le payload donné.
 *
 * @param  array<string, mixed>  $payload
 */
function fakeVision(array $payload, float $cost = 0.0012, string $model = 'openai/gpt-4o'): void
{
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'model' => $model,
            'choices' => [['message' => ['content' => json_encode($payload)]]],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 120, 'cost' => $cost],
        ]),
    ]);
}

/**
 * Fake les deux endpoints OpenRouter : chat (vision) ET embeddings.
 *
 * @param  array<string, mixed>  $payload
 */
function fakeOpenRouter(array $payload, float $cost = 0.0012, string $model = 'openai/gpt-4o'): void
{
    Http::fake(function ($request) use ($payload, $cost, $model) {
        if (str_contains($request->url(), '/embeddings')) {
            return Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.01)]],
            ]);
        }

        return Http::response([
            'model' => $model,
            'choices' => [['message' => ['content' => json_encode($payload)]]],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 120, 'cost' => $cost],
        ]);
    });
}

/**
 * Crée un talent avec une image stockée sur le disque public (fake en test).
 */
function talentWithImage(): Talent
{
    // Contenu unique à chaque appel, sinon la dédupe par hash renvoie null.
    return app(ImportService::class)
        ->storeImage('FAKE_IMAGE_BYTES_'.uniqid('', true), 'upload');
}
