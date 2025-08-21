<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\UserOrganizationGroup;
use App\Models\UserOrganizationType;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class OrganizationTypeController extends Controller
{
    public function data(Request $request)
    {
        try {

            $organizationType = UserOrganizationType::where('id', $this->organization->organization_type_id)->get();

            if ($this->user_login->is_admin) {
                $organizationType = UserOrganizationType::all();
            }


            return parent::handleRespond($organizationType);
        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function store(Request $request)
    {
        try {
            $user = auth('api')->user();
            $data = [
                'organization_type_name' => $request->type ?? '',
                'organization_type_description' => $request->description ?? '',
                BaseModel::CREATED_BY => $user->id ?? 1,
                BaseModel::UPDATED_BY => $user->id ?? 1,
                BaseModel::STATUS => (boolean)$request->status

            ];
            $organization_group = UserOrganizationType::create($data);
            return parent::handleRespond($organization_group);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    private function find($id): array
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $organizationGroup = UserOrganizationType::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $organizationGroup;
            return $res;
        }
        return $res;
    }

    public function show(Request $request)
    {
        $res = $this->find($request->id);

        if ($res['status'] !== 200) {
            return parent::handleNotFound($res, $res[BaseModel::STATUS]);
        }

        return parent::handleRespond($res);
    }

    public function update(Request $request)
    {
        try {
            $req = [];

            if ($request->type) {
                $req['organization_type_name'] = $request->type;
            }

            if ($request->description) {
                $req['organization_type_description'] = $request->description;
            }

            $req[BaseModel::STATUS] = (boolean)$request->status;

            UserOrganizationType::Where('id', $request->id)->update($req);
            return parent::handleRespond(UserOrganizationType::find($request->id));

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }
}
