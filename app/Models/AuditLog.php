<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    public const TRANSACTION = 'transaction';
    public const PRIMARY_KEY = 'primary_key';
    public const ORIGINAL = 'original';
    public const CHANGED = 'changed';

    protected $guarded = [];

    protected $casts = [
        self::ORIGINAL => 'array',
        self::CHANGED => 'array'
    ];
}
