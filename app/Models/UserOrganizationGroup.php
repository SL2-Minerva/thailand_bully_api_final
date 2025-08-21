<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOrganizationGroup extends BaseModel
{
    use HasFactory;
    protected $table = 'user_organization_groups';
    protected $guarded = [];

    public const ORGANIZATION_GROUP_NAME =  'organization_group_name';
    public const ORGANIZATION_GROUP_DESCRIPTION = 'organization_group_description';
    public const TOTAL_KEYWORD = 'total_keyword';
    public const KEYWORD_CONDITION = 'keyword_condition';
    public const MSG_TRANSACTION = 'msg_transaction';
    public const TOTAL_USER = 'total_user';
    public const CUSTOMER_SERVICE = 'customer_service';
    public const CAMPAIGN_PER_ORGANIZE = 'campaign_per_organize';
    public const CAMPAIGN_PER_USER = 'campaign_per_user';
    
    

    protected $casts = [
        'domains' => 'array',
        'platform' => 'array',
    ];
}
