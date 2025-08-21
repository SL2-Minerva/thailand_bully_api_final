<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeleteLog extends Model
{
    use HasFactory;
    protected $table = 'messages_delete_log';
    protected $guarded = [];
}
