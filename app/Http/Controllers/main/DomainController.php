<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Domain;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use PHPUnit\Util\Exception;

class DomainController extends Controller
{
    public function index (Request $request) {

        $domains = null;

        if (isset($this->organization_group->domains)) {
            $domains = Domain::whereIn('name', $this->organization_group->domains)
                ->where('status', 1)
                ->get();
        }

        if ($this->user_login->is_admin) {
            $domains = Domain::all();
        }

        return parent::handleRespond($domains);
    }

    public function show(Request $request) {
        $id = $request->id;

        $res = $this->find($id);
        if ($res[BaseModel::STATUS] !== 200) return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        return parent::handleRespond($res);
    }

    public function store(Request $request) {


        if (!$request->name) {
            return parent::handleNotFound(null);
        }

        $data_submit = [
            "name" => $request->name,
            "description" => $request->description,
            "status" => $request->status,
        ];

        $domain = Domain::create($data_submit);

        return parent::handleRespond($domain);

    }

    public function update(Request $request, $action = null) {
        $id = $request->id;
        try {
            $res = $this->find($id);
            if ($res[BaseModel::STATUS] === 200) {
                $data = $res[BaseModel::DATA_TEXT];

                $data->update($request->all());
                return parent::handleRespond($data);
            } else {

            }

            return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function destroy(Request $request) {
        return $this->update($request, BaseModel::DELETE_TEXT);
    }

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $campaign = Domain::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $campaign;
            return $res;
        }
        return $res;
    }
}
