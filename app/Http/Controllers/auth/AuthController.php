<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Exception;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request)
    {
        $username = $request->username;
        $password = $request->password;

        $user = User::where(BaseModel::EMAIL, $username)->first();

        error_log(json_encode($user));

        if (!$user || ($user->status == 0 && $user->is_admin != 1)) {
            return parent::handleNotFound(['email' => $username]);
        }

        if (!$token = auth('api')->attempt(['email' => $username, 'password' => $password])) {
            return parent::handleRespond(null, [], 404, 'Unauthorized');
        }

        return $this->respondWithToken($token);
    }

    // public function login(Request $request)
    // {
    //     $username = $request->username;
    //     $password = $request->password;

    //    $user = User::where(BaseModel::EMAIL, $username)
    //         ->where('password', $password)
    //         ->where('status', 1)
    //         ->orWhere('is_admin', 1)
    //         ->first();

    //      if ($user) {
    //         if (!$token = auth('api')->attempt(['email' => $username, 'password' => $password])) {
    //             return parent::handleRespond(null, [], 404, 'Unauthorized');
    //         }

    //         return $this->respondWithToken($token);
    //     }

    //     return parent::handleNotFound(['email' => $username]);
    // }


    public function register(Request $request)
    {
        $rules = [
            'username' => 'required',
            'company' => 'required',
            'email' => 'required',
            'mobile' => 'required',
            'password' => 'required',
        ];

        $request_data = parent::validate($request, $rules);

        if (isset($request_data['invalid'])) {
            return parent::handleRespond($request_data, [], 500, 'Error');
        }


        // todo check company and email and phone


        try {
            $request_data['password'] = Hash::make($request_data['password']);
            $request_data['name'] = $request_data['username'];
            unset($request_data['username']);
            $request_data['role_id'] = 1; // temp
            $request_data['organization_id'] = 1; // temp
            $request_data['is_admin'] = 0; // temp
            $request_data['created_by'] = 0; // temp
            $request_data['update_by'] = 0; // temp
            $request_data['status'] = 2; // temp

            $user = User::create($request_data);
            parent::audi_log(
                $request,
                BaseModel::CREATE_TEXT,
                'user_id',
                $user->id,
                'USER',
                null,
                $request_data
            );

            return parent::handleRespond($user);

        } catch (Exception $exception) {
            return parent::handleRespond($request->all(), [], 500, 'Error');
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {

        return response()->json(auth('api')->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return parent::handleRespond([], [], 200, 'Successfully logged out');
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {

        return parent::handleRespond([
            'accessToken' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 120
        ], null);
    }

    public function reset_password(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'new_password' => 'required|string|min:6',
            'old_password' => 'required|string|min:6',
        ]);

        $user = User::findOrFail($request->id);

        if (Hash::check($request->old_password, $user->password)) {
            $user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();

        } else {
            return parent::handleRespond(null, null, 400, 'Old Password does not match!');
        }

        return parent::handleRespond($user);

    }

}
