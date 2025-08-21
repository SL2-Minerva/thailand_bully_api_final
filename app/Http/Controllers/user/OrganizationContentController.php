<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\OrganizationContent;
use App\Models\User;
use Illuminate\Http\Request;

class OrganizationContentController extends Controller
{

    public function index(Request $request)
    {

        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $organization_content = OrganizationContent::where('organization_id', $this->organization->id);

        if ($request->content_id) {
            $organization_content->where('content_id', $request->content_id);
        }

        if ($request->title) {
            $organization_content->where('title', 'like', "%$request->title%");
        }

        if ($request->status || $request->status === '0') {
            $organization_content->where('status', $request->status);
        }

        if ($request->date) {
            $organization_content->where('date', $request->date);
        }

        $data['total'] = $organization_content->count();
        $data['data'] = $organization_content->offset($start)->limit($limit)->get();
        
        return parent::handleRespond($data);
    }

    public function show(Request $request)
    {

        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $organization_content = OrganizationContent::join('users', 'organization_contents.created_by', 'users.id')
            ->select(
                "organization_contents.id",
                "organization_contents.organization_id",
                "organization_contents.title",
                "organization_contents.content_text",
                "organization_contents.picture",
                "organization_contents.date",
                "organization_contents.content_id",
                "organization_contents.status",
                "organization_contents.created_by",
                "organization_contents.updated_by",
                "organization_contents.created_at",
                "organization_contents.updated_at",
                "users.is_admin"
            )
            ->orderBy('is_admin', 'desc');

        if ($request->content_id) {
            $organization_content->where('organization_contents.content_id', $request->content_id);
        }

        if ($request->title) {
            $organization_content->where('organization_contents.title', 'like', "%$request->title%");
        }

        if ($request->status || $request->status === '0') {
            $organization_content->where('organization_contents.status', $request->status);
        }

        if ($request->date) {
            $organization_content->where('organization_contents.date', $request->date);
        }

        $data['total'] = 0;
        $data['data'] = null;
        $data_content = null;

        $content = $organization_content->get();
        foreach ($content as $item) {

            if ($item->is_admin) {
                $data_content[] = $item;
            }

            if (!$item->is_admin && $item->organization_id == $this->organization->id) {
                $data_content[] = $item;
            }
        }

        $data['total'] = is_array($data_content) ? count($data_content) : 0;
        $data['data'] = $data_content ?? null;
        
        return parent::handleRespond($data);
    }

    public function store(Request $request)
    {
        $rules = [
            'title' => 'required',
            'content' => 'nullable',
            'content_id' => 'nullable',
            'status' => 'nullable',
            'picture' => 'nullable|image|mimes:png,jpg,jpeg|max:2048'
        ];

        $request_data = parent::validate($request, $rules);

        if (isset($request_data['invalid'])) {
            return parent::handleRespond($request_data, [], 500, 'Error');
        }

        $data = [
            'title' => $request->title,
            'content_text' => $request->content_text,
            'content_id' => $request->content_id ?? 3,
            'status' => $request->status ?? 1,
            'organization_id' => $this->organization->id,
            'date' => $request->date ?? date('Y-m-d'),
            BaseModel::CREATED_BY => auth('api')->id() ?? 1, // todo check auth
            BaseModel::UPDATED_BY => auth('api')->id() ?? 1, // todo check auth
        ];


        if ($request->picture) {
            $data['picture'] = parent::uploadImage($request->picture);
        }

        $organization_content = OrganizationContent::create($data);
        return parent::handleRespond($organization_content);
    }

    public function update(Request $request)
    {

        $id = $request->id;


        if (!$id) {
            return parent::handleNotFound('content id not found');
        }

        $rules = [
            'title' => 'required',
            'content' => 'nullable',
            'content_id' => 'nullable',
            'status' => 'nullable',
            'picture' => 'nullable|image|mimes:png,jpg,jpeg|max:2048'
        ];

        $request_data = parent::validate($request, $rules);


        $data = [];
        if (isset($request_data['invalid'])) {
            return parent::handleRespond($request_data, [], 500, 'Error');
        }

        if ($request->picture) {
            $data['picture'] = parent::uploadImage($request->picture);
        }

        if ($request->title) {
            $data['title'] = $request->title;
        }

        if ($request->content_text) {
            $data['content_text'] = $request->content_text;
        }

        $data['status'] = 1;

        if ($request->status || $request->status == 0) {
            $data['status'] = $request->status ?? 0;
        }

        if ($request->date) {
            $data['date'] = $request->date;
        }

        if ($request->content_id) {
            $data['content_id'] = $request->content_id;
        }


        $organization_content = OrganizationContent::where('organization_id', $this->organization->id)->where('id' , $id)->first();
        if ($organization_content) {
            $organization_content->update($data);
            return parent::handleRespond($organization_content);
        }

        return  parent::handleNotFound($request->all());

    }

    public function destroy(Request $request) {

        if ($request->id) {
            $id = OrganizationContent::find($request->id);
            $delete = OrganizationContent::where('id', $id->id)->delete();
            return parent::handleRespond($delete);
        }

        return  parent::handleNotFound($request->id);
    }

    private function find_supperadmin($id) {
        $user = User::find($id);
        if ($user) {
            return $user->is_admin;
        }
        return 0;
    }


}
