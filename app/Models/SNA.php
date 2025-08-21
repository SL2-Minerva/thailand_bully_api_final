<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SNA extends Model
{
    use HasFactory;

    protected $table = 'total_sna_root_node';
    public const MESSAGE_ID = 'message_id';
    public const CAMPAIGN_ID = 'campaign_id';
    public const REFERENCE_MESSAGE_ID = 'reference_message_id';
}
