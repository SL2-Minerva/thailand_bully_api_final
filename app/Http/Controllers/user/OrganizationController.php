<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BaseModel;
use App\Models\Organization;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class OrganizationController extends Controller
{
    public function data(Request $request) {
        $organizations = Organization::join('user_organization_types', 'user_organization_types.id', '=', 'organizations.organization_type_id')
            ->join('user_organization_groups', 'user_organization_groups.id', '=', 'organizations.organization_group_id')
            ->select('organizations.*', 'user_organization_types.organization_type_name as type', 'user_organization_groups.organization_group_name as group');
        
        if (!$this->user_login->is_admin) {
            $organizations->where('organizations.id', $this->user_login['organization_id']);
        }

        return parent::handleRespond($organizations->get());
    }

    public function show()
    {

    }


    /**
     * this function for create Organization
     *
     * @param Request $request
     * @param name
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [
            BaseModel::NAME => $request->name,
            BaseModel::STATUS => $request->status ?? 1,
            Organization::GROUP_ID => $request->organization_group_id,
            Organization::DESCRIPTION => $request->description,
            Organization::TYPE_ID => $request->organization_type_id,
            BaseModel::CREATED_BY => auth()->id() ?? null, // todo check auth
            BaseModel::UPDATED_BY => auth()->id() ?? null, // todo check auth
        ];

        try {
            $organization = Organization::create($data);
            parent::audi_log($request, BaseModel::CREATE_TEXT, $organization->id, auth()->id(), null, $request->all());

            return parent::handleRespond($organization, [], 200, BaseModel::SUCCESS_TEXT);

        } catch (Exception $exception) {

            return parent::handleRespond(
                $exception->getMessage(),
                null,
                $exception->getCode(),
                $exception->getMessage());
        }
    }


    public function destory(Request $request)
    {
       return $this->update($request);
    }

    public function update(Request $request, $action = null)
    {
        $organization = Organization::where('id', $request->id);
        if ($organization) {
            try {
                //todo check update;
                $organization->update($request->all());
                return parent::handleRespond($organization);
            } catch (Exception $exception) {
                return parent::handleRespond(
                    $exception->getMessage(),
                    null,
                    $exception->getCode(),
                    $exception->getMessage());
            }
        }
    }


    public function destroy(Request $request) {
        return $this->update($request, BaseModel::DELETE_TEXT);
    }

    public function search(Request $request) {

        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;

        $data = Organization::join('user_organization_types', 'user_organization_types.id', '=', 'organizations.organization_type_id')
            ->join('user_organization_groups', 'user_organization_groups.id', '=', 'organizations.organization_group_id')
            ->select('organizations.*', 'user_organization_types.organization_type_name as type', 'user_organization_groups.organization_group_name as group');
            /* ->offset($start)->limit($limit); */

        if ($request->name) {
            $data->where('organizations.name','like', "%$request->name%");
        }

        if ($request->status || $request->status === '0') {
            $data->where('organizations.status', $request->status);
        }

        if ($request->group) {
            $data->where('organizations.organization_group_id', $request->group);
        }

        if ($request->type) {
            $data->where('organizations.organization_type_id', $request->type);
        }

        if (!$this->user_login->is_admin) {
            $data->where('organizations.id', $this->user_login->organization_id);
        }

        return parent::handleRespond($data->get());

    }

}
