<?php

namespace App\Models;

use Database\Factories\AnimalVarietyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalVariety extends Model
{
    /** @use HasFactory<AnimalVarietyFactory> */
    use HasFactory;

    protected $fillable = [
        'animal_id',
        'label',
        'kind',
        'clip_names',
    ];

    protected $casts = [
        'clip_names' => 'array',
    ];

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
