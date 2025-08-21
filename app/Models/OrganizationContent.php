<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationContent extends Model
{
    use HasFactory;
    protected $guarded = [];

    const CONTENT_TEXT = 'content_text';

    protected $hidden = [
        BaseModel::CREATED_BY,
        BaseModel::UPDATED_BY,
    ];
}
