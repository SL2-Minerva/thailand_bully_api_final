<?php

namespace App\Http\Controllers\permission;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Exception;

class RoleController extends Controller
{


    public function index(Request $request)
    {
        $data = UserRole::where(BaseModel::ID, '!=', 0)->get();


        if (!$this->user_login->is_admin) {
            $roles = null;
            $roles = User::where('organization_id', $this->organization->id)->pluck('role_id')->toArray();
            $data = UserRole::whereIn('id', $roles)->get();
        }

        foreach ($data as $item) {

            $row_permissions = UserPermission::where('role_id', $item->id)->get([
                'authorized_create', 'authorized_view', 'authorized_edit', 'authorized_delete', 'authorized_export', 'menu', 'id'
            ]);

            $permissions = null;
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

            $item->permission = $permissions;
        }


        return parent::handleRespond($data ?? []);
    }

    public function update(Request $request)
    {
        $id = $request->id;
        $user = auth('api')->user();
        try {
            $res = $this->find($id);

            if ($res[BaseModel::STATUS] === 200) {
                $data = $res[BaseModel::DATA_TEXT];

                $handle_data = [];
                if (!$request->status || $request->status) {
                    $handle_data[BaseModel::STATUS] = $request->status ?? 1;
                }

                if ($request->role_name) {
                    $handle_data[BaseModel::ROLE_NAME] = $request->role_name;
                }

                if ($request->role_description) {
                    $handle_data[BaseModel::ROLE_DESCRIPTION] = $request->role_description;
                }

                if ($request->authorized_report) {
                    $handle_data[BaseModel::AUTHORIZED_REPORT] = $request->authorized_report;
                }

                $data->update($handle_data);

                $permissions = $request->permission;
                if ($permissions) {
                    foreach ($permissions as $key => $permission) {
                        $permission[BaseModel::STATUS] = 1;
                        $permission['menu'] = $key;
                        $permission['role_id'] = $id;
                        $permission[BaseModel::UPDATED_BY] = $user->id;

                        if (isset($permission['id'])) {
                            UserPermission::where(BaseModel::ID, $permission['id'])->update($permission);;
                        } else {
                            unset($permission['id']);
                            $permission[BaseModel::CREATED_BY] = $user->id;
                            UserPermission::create($permission);
                        }

                    }
                }

                return parent::handleRespond($data);
            }

            return parent::handleRespond(null);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $data = UserRole::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $data;
            return $res;
        }
        return $res;
    }

    public function store(Request $request)
    {

        $user = auth('api')->user();
        $data = [
            BaseModel::ROLE_NAME => $request->role_name,
            BaseModel::ROLE_DESCRIPTION => $request->role_description,
            BaseModel::CREATED_BY => $user->id ?? 1,
            BaseModel::UPDATED_BY => $user->id ?? 1,
            BaseModel::AUTHORIZED_MENU => [],
            BaseModel::AUTHORIZED_REPORT => $request->authorized_report ?? [],
        ];


        try {
            $role = UserRole::create($data);

            $permissions = $request->permission;
            if ($permissions) {
                foreach ($permissions as $key => $permission) {
                    $permission[BaseModel::STATUS] = 1;
                    $permission['menu'] = $key;
                    $permission['role_id'] = $role->id ?? 2;
                    $permission[BaseModel::CREATED_BY] = $user->id;
                    $permission[BaseModel::UPDATED_BY] = $user->id;

                    UserPermission::create($permission);
                }
            }

            return parent::handleRespond($role);

        } catch (Exception $exception) {
            parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function destroy(Request $request)
    {


        return $this->update($request, BaseModel::UPDATE_TEXT);
    }
}
