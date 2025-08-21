<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassificationType extends BaseModel
{
    use HasFactory;

    protected $table = 'user_classification_types';
    protected $guarded = [];
}
