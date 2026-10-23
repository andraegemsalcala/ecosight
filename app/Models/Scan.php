<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'animal_id',
        'detected_label',
        'model_source',
        'top2_label',
        'top2_confidence',
        'user_confirmed',
        'variety_id',
        'variety_label',
        'variety_confidence',
        'variety_confirmed',
        'confidence',
        'response_time_ms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:4',
            'top2_confidence' => 'decimal:4',
            'variety_confidence' => 'decimal:4',
            'user_confirmed' => 'boolean',
            'variety_confirmed' => 'boolean',
            'response_time_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(AnimalVariety::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(ScanFeedback::class);
    }
}
