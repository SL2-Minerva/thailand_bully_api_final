<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageResult extends BaseModel
{
    use HasFactory;
    protected $table = 'message_results';
    protected $guarded = [];
}
