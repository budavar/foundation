<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $casts = [
        'publish_in_newsfeed' => 'boolean',
        'meta' => 'array',
    ];

    use \App\Traits\UsesUuid;
    
}
