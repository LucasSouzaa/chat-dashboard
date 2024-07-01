<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Phone extends Model
{
    use HasFactory;


    public function dashboards(): BelongsToMany
    {
        return $this->belongsToMany(Dashboard::class);
    }

    protected function casts()
    {
        return [
            'memory' => 'json'
        ];
    }
}
