<?php

use Illuminate\Support\Facades\Http;

it('renders the lab page', function () {
    $this->get('/labo')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Lab')->has('pipeline')->has('projection'));
});

it('renders the usage page without a live OpenRouter call blocking it', function () {
    Http::fake(['*' => Http::response(['data' => ['total_credits' => 10, 'total_usage' => 3]], 200)]);

    $this->get('/usage')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Usage')->has('models')->has('activity'));
});
