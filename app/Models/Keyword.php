<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Keyword extends BaseModel
{
    use HasFactory;

    protected $table = 'keywords';
    protected $guarded = [];

    public const LABEL = 'label';
    public const KEYWORD_OR = 'keyword_or';
    public const KEYWORD_AND = 'keyword_and';
    public const PARENT_ID = 'parent_id';
    public const KEYWORD_EXCLUDE = 'keyword_exclude';
    public const CAMPAIGN_ID = 'campaign_id';

//    protected $casts = [
//        self::KEYWORD_OR => array(),
//        self::KEYWORD_AND => array(),
//        self::KEYWORD_EXCLUDE => array(),
//    ];

}
