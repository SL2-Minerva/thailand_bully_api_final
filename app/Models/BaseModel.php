<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BaseModel extends Model
{
    use HasFactory;

    public const UUID = "uuid";
    public const ID = "id";
    public const STATUS = "status";
    public const CREATED_AT = "created_at";
    public const UPDATED_AT = "updated_at";
    public const CREATED_BY = "created_by";
    public const UPDATED_BY = "updated_by";
    public const DESCRIPTION = "description";
    public const NAME = 'name';
    public const EMAIL = 'email';
    public const COMPANY = 'company';
    public const MOBILE = 'mobile';
    public const CREATE_TEXT = 'CREATE';
    public const UPDATE_TEXT = 'UPDATE';
    public const DELETE_TEXT = 'DELETE';
    public const DATA_TEXT = 'data';
    public const NOT_FOUND_TEXT = 'Not found';
    public const MSG_TEXT = 'msg';
    public const SUCCESS_TEXT = 'success';
    public const DOMAINS = 'domains';
    public const PLATFORM = 'platform';
    public const USER_ID = 'user_id';
    public const ROLE_ID = 'role_id';
    public const ORGANIZATION_ID = 'organization_id';
    public const SOURCE = 'source';
    public const ROLE_NAME = 'user_role_name';
    public const ROLE_DESCRIPTION = 'user_role_description';
    public const AUTHORIZED_MENU = 'authorized_menu';
    public const AUTHORIZED_REPORT = 'authorized_report';

    protected $hidden = [
//        BaseModel::UUID,
//        BaseModel::STATUS,
//        BaseModel::CREATED_BY
    ];
}
