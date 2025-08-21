<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    use HasFactory;

    protected $table = 'user_permissions';
    protected $guarded = [];

    public const AUTHORIZED_CREATE = 'authorized_create';
    public const AUTHORIZED_EDIT = 'authorized_edit';
    public const AUTHORIZED_DELETE = 'authorized_delete';
    public const AUTHORIZED_VIEW = 'authorized_view';
    public const AUTHORIZED_EXPORT = 'authorized_export';
}
