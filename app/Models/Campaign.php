<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends BaseModel
{
    use HasFactory;

    protected $table = 'campaigns';
    protected $guarded = [];

    public const DOMAIN_ID = 'domain_id';
    public const EXCLUDE_CAMPAIGN = 'exclude_campaign';
    public const START_AT = 'start_at';
    public const END_AT = 'end_at';
    public const DESCRIPTION = 'description';
    public const FREQUENCY = 'frequency';
    public const PRIVACY_CAMPAIGN = 'privacy_campaign';
    public const PLATFORM = 'platform';

    protected $casts = [
        'platform' => 'array',
    ];
}
