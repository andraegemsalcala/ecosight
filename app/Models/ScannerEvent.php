<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'reason',
        'response_time_ms',
        'model_load_ms',
        'camera_start_ms',
    ];

    protected function casts(): array
    {
        return [
            'response_time_ms' => 'integer',
            'model_load_ms' => 'integer',
            'camera_start_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
