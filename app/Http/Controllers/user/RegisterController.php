<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\user\RegisterRequest;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {

        $data = [
            BaseModel::NAME => $request->name,
            BaseModel::COMPANY => $request->company,
            BaseModel::EMAIL => $request->email,
            BaseModel::MOBILE => $request->email,
            BaseModel::ROLE_ID => 1,
            BaseModel::STATUS => 3,
            BaseModel::ORGANIZATION_ID => 2,
        ];
        try {
            $user = User::create($data);
            $user_id = $user->id;
            $user->update([
                BaseModel::CREATED_BY => $user_id,
                BaseModel::UPDATED_BY => $user_id]
            );

            return parent::handleRespond($user);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

}
