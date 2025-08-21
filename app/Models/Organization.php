<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends BaseModel
{
    use HasFactory;

    protected $table = 'organizations';
    protected $guarded = [];

    public const GROUP_ID = 'organization_group_id';
    public const TYPE_ID = 'organization_type_id';


}
