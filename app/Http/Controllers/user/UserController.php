<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Campaign;
use App\Models\Keyword;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserOrganizationGroup;
use App\Models\UserPermission;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller
{


    public function update(User $user, Request $request)
    {
        $data = $request->all();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($request->password);
        }

        $data[BaseModel::UPDATED_BY] =  auth('api')->id() ?? 1;
        $user->update($data);
        return parent::handleRespond($request->id);
    }

    public function data(Request $request)
    {
        $users = User::where(BaseModel::STATUS, 2)->where('organization_id', $this->user_login->organization_id)->get();

        if ($this->user_login->is_admin) {
            $users = User::where(BaseModel::STATUS, 2)->get();
        }

        return parent::handleRespond($users);
    }

    public function list_active(Request $request)
    {
        $users = User::join('organizations', 'users.organization_id', '=', 'organizations.id')
            ->join('user_organization_groups', 'organizations.organization_group_id', '=', 'user_organization_groups.id')
            ->where('users.status', 1)
            ->where('users.is_admin', '!=', 1)
            ->orWhere('users.status', 0)
            ->where('organizations.status', 1)
            ->select('users.*', 'organizations.name as organization', 'user_organization_groups.organization_group_name as group')
            ->get();
//       $users = User::where(BaseModel::STATUS, 1)->get();
        return parent::handleRespond($users);
    }


    public function create(Request $request)
    {
        $data = $request->all();
        $data['password'] = Hash::make($request->password);
        $data[BaseModel::CREATED_BY] =  auth('api')->id() ?? 1;
        $data[BaseModel::UPDATED_BY] =  auth('api')->id() ?? 1;
        $data['is_admin'] = $request->is_admin ?? 0;
        $user = User::create($data);
        return parent::handleRespond($user);
    }

    public function delete(User $user, Request $request)
    {
        $user->update([BaseModel::STATUS => 0]);
        return parent::handleRespond($user);
    }

    public function info()
    {

        $user = auth('api')->user();
        $role_id = $user->role_id;
        
        $role_info = UserRole::where('id', $role_id)->first();
        $permissions = null;

        if ($user->role_id) {
            $row_permissions = UserPermission::where('role_id', $user->is_admin ? 0 : $user->role_id)->get([
                'authorized_create', 'authorized_view', 'authorized_edit', 'authorized_delete', 'authorized_export', 'menu', 'id'
            ]);

            foreach ($row_permissions as $permission) {
                $permissions[$permission->menu] = [
                    'authorized_create' => $permission->authorized_create,
                    'authorized_view' => $permission->authorized_view,
                    'authorized_edit' => $permission->authorized_edit,
                    'authorized_delete' => $permission->authorized_delete,
                    'authorized_export' => $permission->authorized_export,
                    'id' => $permission->id
                ];
            }
        }

        if ($user) {
            $data['info'] = $user;
            $data['info']['campaign_per_user'] = $this->campaign_per_user($user->organization_id);
            $data['info']['campaign_per_organize'] = $this->campaign_per_organize($user->organization_id);
            $data['organization_group'] = $this->organization_group($user->organization_id);
            $data['organization'] = $this->organization_name($user->organization_id);
            $data['role_description'] = 'ssss';
            $data['role_name'] = $role_info->user_role_name; 
            $data['permission'] = $permissions;
            $data['menu'] = ['all'];
            $data['is_admin'] = $user->is_admin;
            $data['authorized_report'] = $this->permission_report($user);
            return parent::handleRespond($data);
        }

        return parent::handleNotFound($user);
    }

    private function permission_report($user)
    {

        if ($user->is_admin) {
            $permissions = null;
            for ($i = 1; $i <= 111; $i++) {
                $permissions[] = strval($i);
            }
            return $permissions;
        } else {
            $role_id = $user->role_id;
            $role = UserRole::where(BaseModel::ID, $role_id)->first();
            return $role->authorized_report ?? null;
        }

    }

    public function search(Request $request)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;

        $user = User::join('organizations', 'users.organization_id', '=', 'organizations.id')
            ->join('user_organization_groups', 'organizations.organization_group_id', '=', 'user_organization_groups.id')
            ->join('user_organization_types', 'organizations.organization_type_id', '=', 'user_organization_types.id')
            ->select(
                'users.id', 
                'users.name', 
                'users.mobile', 
                'users.email', 
                'users.company', 
                'users.organization_id', 
                'users.role_id', 
                'users.status', 
                'users.created_at',
                'organizations.name AS organization_name',
                'user_organization_groups.organization_group_name AS organization_group_name',
                'user_organization_types.organization_type_name AS organization_type_name'
            )
            ->offset($start)->limit($limit);

        $status = $request->status ?? 1;

        if ($request->name) {
            $user = $user->where('users.name', 'like', "%$request->name%");
        }

        if (!$status || $status) {
            $user = $user->where('users.status', $status);
        }

        if ($request->organization_id) {
            $user = $user->where('users.organization_id', $request->organization_id);
        }

        if (!$this->user_login->is_admin) {
            $user->where('organization_id', $this->user_login->organization_id);
            $user->select(
                'users.id', 
                'users.name', 
                'users.mobile', 
                'users.email', 
                'users.company', 
                'users.organization_id', 
                'users.role_id', 
                'users.status', 
                'users.created_at',
                'organizations.name AS organization_name',
                'user_organization_groups.organization_group_name AS organization_group_name',
                'user_organization_types.organization_type_name AS organization_type_name'
            );
        }

        return parent::handleRespond($user->get());
    }

    public function forget_password(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users',
        ]);

        $token = Str::random(64);

        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        Mail::send('auth.forget-password-email', ['token' => $token, 'email' => $request->email], function($message) use($request){
            $message->to($request->email);
            $message->subject('Reset Password');
        });

    }

    public function reset_password(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required|string|min:6',
        ]);

        $updatePassword = DB::table('password_resets')->where([
            'email' => $request->email,
            'token' => $request->token
        ])->first();

        if(!$updatePassword){
            return parent::handleRespond(null, null, 404, 'Invalid token!');
        }

        $user = User::where('email', $request->email)->update(['password' => Hash::make($request->password)]);

        DB::table('password_resets')->where(['email'=> $request->email])->delete();

        return parent::handleRespond($user);

    }

    private function campaign_per_user($organization_id)
    {
        if ($organization_id) {
            $organization = Organization::where('id', $organization_id)->first();
            $organization_group = UserOrganizationGroup::where('id', $organization->organization_group_id)->first();

            $campaign_per_user = $organization_group->campaign_per_user;

            if ($campaign_per_user) {
                    $count_campaign = Campaign::Join('organizations', 'campaigns.organization_id', 'organizations.id')
                        ->Join('keywords', 'campaigns.id', 'keywords.campaign_id')
                        ->where('organizations.id', $organization_id)
                        ->where('keywords.created_by', $this->user_login->id)
                        ->select('keywords.*')
                        ->groupBy('campaign_id')
                        ->get()
                        ->count();
                        
                    return $campaign_per_user - $count_campaign;
            }

            return null;
        }
    }

    private function campaign_per_organize($organization_id)
    {
        if ($organization_id) {
            $organization = Organization::where('id', $organization_id)->first();
            $organization_group = UserOrganizationGroup::where('id', $organization->organization_group_id)->first();
            $campaign_per_organize = $organization_group->campaign_per_organize;

            if ($campaign_per_organize) {
                $count_campaign = Campaign::where('organization_id', $organization_id)
                ->whereNotNull('privacy_campaign')
                ->count();
                return $campaign_per_organize - $count_campaign;
            }

            return null;
        }
    }

    private function organization_group($organization_id)
    {
        if ($organization_id) {
            $organization = Organization::where('id', $organization_id)->first();
            $organization_group = UserOrganizationGroup::where('id', $organization->organization_group_id)->first();

            if ($organization_group) {
                return $organization_group;
            }

        }
    }

    private function organization_name($organization_id) {
        if ($organization_id) {
            $organization = Organization::where('id', $organization_id)
                ->select('id', 'name', 'description')
                ->first();

            if ($organization) {
                return $organization;
            }

        }

        return null;
    }

    public function info_transaction()
    {
        $user = auth('api')->user();
        if ($user) {
            $organization_id = $user->organization_id;
            
        }
        if ($organization_id) {
            $organization = Organization::where('id', $organization_id)
                ->select('id', 'name', 'description', 'transaction_limit', 'transaction_reamining', 'transaction_start_at', 'organization_group_id')
                ->first();
            $organization_group = UserOrganizationGroup::where('id', $organization->organization_group_id)->first();
            $organization['transaction_limit_group'] = $organization_group->msg_transaction ?? null;

            if ($organization) {
                $campaign_id = Campaign::where('organization_id', $user->organization_id)->select('id')->first();
                if (!empty($campaign_id->id)) {
                    $keyword_id = Keyword::where('campaign_id', $campaign_id->id)->select('id')->pluck('id');
                    
                    if (!empty($keyword_id)) {
                        $transaction_per_month = Message::whereMonth('message_datetime', Carbon::now()->month)
                            ->whereIn('keyword_id', $keyword_id)
                            ->select(DB::raw('COUNT(*) as transaction_per_month'))->first();
    
                        $organization['transaction_per_month'] = $transaction_per_month->transaction_per_month ?? null;
                    }
                }

            }

            if ($organization) {
                return parent::handleRespond($organization);
            }

        }

        return parent::handleRespond(null);
    }

}
