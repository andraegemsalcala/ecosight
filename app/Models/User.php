<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function addedAnimals(): HasMany
    {
        return $this->hasMany(Animal::class, 'added_by');
    }

    public function scanFeedback(): HasMany
    {
        return $this->hasMany(ScanFeedback::class);
    }

    public function scannerEvents(): HasMany
    {
        return $this->hasMany(ScannerEvent::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function actionCompletions(): HasMany
    {
        return $this->hasMany(ActionCompletion::class);
    }
}
