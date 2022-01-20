<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $casts = [
        'rules' => 'array',
    ];

    use \App\Traits\UsesUuid;

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id', 'id');
    }

    public function myMember()
    {
        return $this->hasOne(GroupMember::class, 'group_id', 'id');
    }

}
