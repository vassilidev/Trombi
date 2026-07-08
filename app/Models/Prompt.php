<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    protected $fillable = [
        'key',
        'label',
        'content',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }
}
