<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasFactory;

    protected $table = 'user_roles';
    protected $guarded = [];

    protected $casts = [
        BaseModel::AUTHORIZED_MENU => 'array',
        'authorized_report' => 'array',
    ];

//    protected $hidden = ['id'];
}
