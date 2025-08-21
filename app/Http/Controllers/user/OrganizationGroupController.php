<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\organization\OrganizationGroupRequest;
use App\Models\BaseModel;
use App\Models\UserOrganizationGroup;
use App\Models\UserOrganizationType;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class OrganizationGroupController extends Controller
{
    public function data(Request $request)
    {
        try {

            $organizationGroup = UserOrganizationGroup::where('id', $this->organization->organization_group_id)->get();

            if ($this->user_login->is_admin) {
                $organizationGroup = UserOrganizationGroup::all();
            }

            return parent::handleRespond($organizationGroup);
        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function store(OrganizationGroupRequest $request)
    {

        try {
            $data = $request->all();
            $user = auth('api')->user();

            $data[BaseModel::CREATED_BY] = $user->id ?? 1;
            $data[BaseModel::UPDATED_BY] = $user->id ?? 1;
            $data[UserOrganizationGroup::CUSTOMER_SERVICE] = $request->customer_service === 'true';
            $data[UserOrganizationGroup::CAMPAIGN_PER_ORGANIZE] = $request->campaign_per_organize ?? 0;
            $data[UserOrganizationGroup::CAMPAIGN_PER_USER] = $request->campaign_per_user ?? 0;
            $data[BaseModel::STATUS] = (boolean)$request->status;
            $organization_group = UserOrganizationGroup::create($data);


            return parent::handleRespond($organization_group);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function update(Request $request)
    {
        $res = $this->find($request->id);

        if ($res['status'] !== 200) {
            return parent::handleNotFound($res, $res[BaseModel::STATUS]);
        }

        $organizationGroup = $res[BaseModel::DATA_TEXT];

        if ($organizationGroup) {
            //todo dd check update
            $data = $request->all();
            $data[UserOrganizationGroup::CUSTOMER_SERVICE] = $request->customer_service === 'true';
            $data[BaseModel::UPDATED_BY]= auth('api')->user()->id ?? 1;
            $data[UserOrganizationGroup::CAMPAIGN_PER_ORGANIZE] = $request->campaign_per_organize ?? 0;
            $data[UserOrganizationGroup::CAMPAIGN_PER_USER] = $request->campaign_per_user ?? 0;
            $organizationGroup->update($data);

            return parent::handleRespond($res);
        }
    }

    private function find($id): array
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $organizationGroup = UserOrganizationGroup::find($id)) {
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

    public function destroy(Request $request)
    {
        return $this->update($request);
    }

}
