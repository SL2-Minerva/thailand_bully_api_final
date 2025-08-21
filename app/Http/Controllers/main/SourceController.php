<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Sources;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index() {
        $sources = Sources::all();

        if (!$this->user_login->is_admin) {
            $sources = Sources::whereIn('name', $this->organization_group->platform)
            ->where('status', 1)
            ->get();
        }
       return parent::handleRespond($sources);
    }

    public function public_source() {
        $sources = Sources::where('status', 1)->get();
 
         if (!$this->user_login->is_admin) {
            $sources = Sources::whereIn('name', $this->organization_group->platform)
            ->where('status', 1)
            ->get();
         }

        // if ($sources) {
        //     error_log('source: '.$sources);
        //     $sources = $sources->get();
        // }

        return parent::handleRespond($sources);
     }

    public function data(Request $request) {
        // $sources = Sources::all();
        $sources = Sources::where('status', 1)->get();

        if (!$this->user_login->is_admin) {
            $sources = Sources::whereIn('name', $this->organization_group->platform)
            ->where('status', 1)
            ->get();
        }

        return parent::handleRespond($sources);
    }

    public function update(Request $request)
    {
        $res = $this->find($request->id);

        if ($res['status'] !== 200) {
            return parent::handleNotFound($res, $res[BaseModel::STATUS]);
        }

        $organizationGroup = $res[BaseModel::DATA_TEXT];
        $data = $request->all();

        if ($request->image) {
            $file = $request->image;
            $path = parent::uploadImage($file, 'source');
        }

        if ($organizationGroup) {
            if (isset($path)) {
                $data['image'] = $path;
            }
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

        if ($id && $sources = Sources::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $sources;
            return $res;
        }
        return $res;
    }

    public function store(Request $request)
    {
        $data = $request->all();

        if ($request->image) {
            $file = $request->image;
            $path = parent::uploadImage($file, 'source');
            $data['image'] = $path;
        }

        $sources = Sources::create($data);
        return parent::handleRespond($sources);
    }
}
