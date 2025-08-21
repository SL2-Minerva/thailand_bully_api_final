<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Campaign;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {

        $campaigns = Campaign::all();


        // if ($request->organization_id || !$this->user_login->is_admin) {

        //     if ($request->organization_id) {
        //         $campaigns = $campaigns->where('organization_id', $request->organization_id);
        //     } else {
        //         $campaigns = $campaigns->where('organization_id', $this->user_login->organization_id);
        //     }
        // }

        if (isset($request->status)) {
            if ($request->status != 'all') {
                $campaigns = $campaigns->where('status', $request->status);
            }
        }
        

        if ($request->name) {
            $campaigns = $campaigns->where('name', $request->name);
        }

        $data = [];
        foreach ($campaigns as $campaign) {
            $campaign->keyword = Keyword::where('campaign_id', $campaign->id)->get();

            if ($campaign->keyword) {
                foreach ($campaign->keyword as $item) {
                    $item->keyword_or = explode(",", $item->keyword_or);
                    $item->keyword_and = explode(",", $item->keyword_and);
                    $item->keyword_exclude = explode(",", $item->keyword_exclude);
                }
//                $item->keyword_or = explode(",",$item->keyword_or)();
//                $item->keyword_and = json_decode($item->keyword_and);
//                $item->keyword_exclude = json_decode($item->keyword_exclude);
            }
            $campaign->organization = Organization::find($campaign->organization_id)->name;
            // $data[] = $campaign;
            $privacy_campaign = $campaign->privacy_campaign ?? null;
            $data[] = $this->privacy($campaign, $privacy_campaign);
            
            $data = array_filter($data, fn ($value) => !is_null($value));

        }
        return parent::handleRespond($data);
    }

    public function show(Request $request)
    {
        $id = $request->id;

        $res = $this->find($id);
        if ($res[BaseModel::STATUS] !== 200) return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        return parent::handleRespond($res);
    }

    public function store(Request $request)
    {
        $data_submit = [
            BaseModel::NAME => $request->name ?? '',
            Campaign::DESCRIPTION => $request->description,
            BaseModel::ORGANIZATION_ID => $request->organization_id ?? 1,
            Campaign::DOMAIN_ID => $request->domain_id ?? 1,
            BaseModel::STATUS => $request->status ?? 1,
            Campaign::EXCLUDE_CAMPAIGN => collect($request->exclude_campaign)->implode(','),
            Campaign::START_AT => $request->start_at,
            Campaign::END_AT => $request->end_at,
            Campaign::FREQUENCY => (int)$request->frequency ?? 120,
            Campaign::PRIVACY_CAMPAIGN => $request->privacy_campaign,
            Campaign::PLATFORM => $request->platform ?? null
            // Campaign::MSG_TRANSACTION => $request->msg_transaction
        ];

        $campaign = Campaign::create($data_submit);

        if ($request->keywords) {

            foreach ($request->keywords as $index => $keyword) {

                $keyword_and = collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(',');
                $name = $keyword[BaseModel::NAME];

                $data_submit_keyword = [
                    Keyword::CAMPAIGN_ID => $campaign->id,
                    BaseModel::NAME => $name,
                    Keyword::KEYWORD_OR => collect($keyword[Keyword::KEYWORD_OR] ?? [])->implode(','),
                    Keyword::KEYWORD_AND => $keyword_and,
                    Keyword::KEYWORD_EXCLUDE => collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                    BaseModel::STATUS => 1,
                    BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                    BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                    "color" => $keyword["colors"] ?? "",
                    "color_and" => $keyword["color_and"] ?? "#",
                    "label" => $keyword["name"]
                ];

                $parent_keyword = Keyword::create($data_submit_keyword);

                if ($keyword_and) {
                    $name .= "," . $keyword_and;

                    $data_submit_keyword = [
                        Keyword::CAMPAIGN_ID => $campaign->id,
                        BaseModel::NAME => $name,
                        Keyword::PARENT_ID => $parent_keyword->id,
                        Keyword::KEYWORD_OR => collect($keyword[Keyword::KEYWORD_OR] ?? [])->implode(','),
                        Keyword::KEYWORD_AND => $keyword_and,
                        Keyword::KEYWORD_EXCLUDE => collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                        BaseModel::STATUS => 1,
                        BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                        BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                        "color" => $keyword["colors"] ?? "",
                        "color_and" => $keyword["color_and"] ?? "#",
                        "label" => $keyword["name"]
                    ];
    
                    $parent_keyword_and = Keyword::create($data_submit_keyword);

                }

                $this->extra_keyword(
                    $campaign->id,
                    $keyword['name'],
                    $parent_keyword->id,
                    collect($keyword[Keyword::KEYWORD_OR] ?? []),
                    collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(','),
                    collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                    $keyword
                );
            }
        }

        return parent::handleRespond($campaign);
    }

    private function extra_keyword($campaign_id, $parent_name, $parent_id, $keyword_ors, $keyword_and, $keyword_exclude, $keyword, $is_update = false)
    {
        $name = $parent_name;
        if ($keyword_and) {
            $name = $name . "," . $keyword_and;
        }

        // loop for keyword or
        foreach ($keyword_ors as $index => $keyword_or) {

            $data_submit_keyword = [
                Keyword::CAMPAIGN_ID => $campaign_id,
                Keyword::PARENT_ID => $parent_id,
                BaseModel::NAME =>  $name. "," . $keyword_or,
                Keyword::KEYWORD_OR => collect($keyword_or ?? [])->implode(','),
                Keyword::KEYWORD_AND => collect($keyword_and ?? [])->implode(','),
                Keyword::KEYWORD_EXCLUDE => collect($keyword_exclude ?? [])->implode(','),
                BaseModel::STATUS => 1,
                BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                "color" => $keyword["keyword_or_color"][$index] ?? "#000000",
                "color_and" => $keyword["color_and"] ?? "#000000",
            ];


            if ($is_update) {
                /// todo
                if (isset($keyword["delete_keyword_or"]) && count($keyword["delete_keyword_or"]) > 0) {
                    foreach ($keyword["delete_keyword_or"] as $delete_keyword_or) {
                        Keyword::where("keyword_or" ,$delete_keyword_or)->delete();
                    }
                }

                if ($keyword_or) {
                    $items = Keyword::where('parent_id', $parent_id)->pluck('keyword_or')->toArray();
                    $check_or = in_array($keyword_or, $items);

                    if (!$check_or) {
                        Keyword::create([
                            Keyword::CAMPAIGN_ID => $campaign_id,
                            BaseModel::NAME => $name. "," . $keyword_or,
                            Keyword::PARENT_ID => $parent_id,
                            Keyword::KEYWORD_OR => collect($keyword_or ?? [])->implode(','),
                            Keyword::KEYWORD_AND => collect($keyword_and ?? [])->implode(','),
                            Keyword::KEYWORD_EXCLUDE => collect($keyword_exclude ?? [])->implode(','),
                            "color" => $keyword["keyword_or_color"][$index] ?? "#000000",
                            BaseModel::STATUS => 1,
                            BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                            BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                        ]);
                    }

                    if ($check_or) {
                        $items = Keyword::where('parent_id', $parent_id)
                            ->where('keyword_or', $keyword_or)
                            ->update([
                                Keyword::CAMPAIGN_ID => $campaign_id,
                                BaseModel::NAME => $name. "," . $keyword_or,
                                Keyword::PARENT_ID => $parent_id,
                                Keyword::KEYWORD_OR => collect($keyword_or ?? [])->implode(','),
                                Keyword::KEYWORD_AND => collect($keyword_and ?? [])->implode(','),
                                Keyword::KEYWORD_EXCLUDE => collect($keyword_exclude ?? [])->implode(','),
                                "color" => $keyword["keyword_or_color"][$index] ?? "#000000",
                                BaseModel::STATUS => 1,
                                BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                                BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                            ]);
                    }
                }

            } else {

                if ($keyword_or) {
                    Keyword::create($data_submit_keyword);
                }

            }
        }


    }

    public function update(Request $request)
    {
        $id = $request->id;

        try {
            $campaign = $this->find($id);

            if ($campaign[BaseModel::STATUS] !== 200) {


//                return
//                if ($action === BaseModel::UPDATE_TEXT) {
//                    $data->update($request->all());
//                    return parent::handleRespond($data);
//                }


                return parent::handleErrorRespond($request->all());
            }

            $data = $campaign[BaseModel::DATA_TEXT];

            $data_submit = [
//                BaseModel::NAME => $request->name ?? '',
//                Campaign::DESCRIPTION => $request->description,
//                BaseModel::ORGANIZATION_ID => $request->organization_id ?? 1,
//                Campaign::DOMAIN_ID => $request->domain_id ?? 1,
//                BaseModel::STATUS => $request->status ?? 1,
//                Campaign::EXCLUDE_CAMPAIGN => collect($request->exclude_campaign)->implode(','),
//                Campaign::START_AT => $request->start_at,
//                Campaign::END_AT => $request->end_at,
            ];

            if ($request->name) {
                $data_submit[BaseModel::NAME] = $request->name;
            }

            if ($request->description) {
                $data_submit[Campaign::DESCRIPTION] = $request->description;
            }

            if (!$this->user_login->is_admin) {
                $data_submit[BaseModel::ORGANIZATION_ID] = $request->organization_id ?? 1;
            }

            if ($request->domain_id) {
                $data_submit[Campaign::DOMAIN_ID] = $request->domain_id;
            }
            
            if ($request->status || !$request->status) {
                $data_submit[BaseModel::STATUS] = $request->status;
            }

            if ($request->exclude_campaign) {
                $data_submit[Campaign::EXCLUDE_CAMPAIGN] = collect($request->exclude_campaign)->implode(',');
            }

            if ($request->start_at) {
                $data_submit[Campaign::START_AT] = $request->start_at;
            }

            if ($request->end_at) {
                $data_submit[Campaign::END_AT] = $request->end_at;
            }

            if ($request->frequency) {
                $data_submit[Campaign::FREQUENCY] = $request->frequency;
            }

            if ($request->privacy_campaign) {
                $data_submit[Campaign::PRIVACY_CAMPAIGN] = $request->privacy_campaign;
            }

            if ($request->platform) {
                $data_submit[Campaign::PLATFORM] = $request->platform;
            }

            // if ($request->msg_transaction) {
            //     $data_submit[Campaign::MSG_TRANSACTION] = $request->msg_transaction;
            // }

            $data->update($data_submit);

            if (isset($data_submit[BaseModel::STATUS])) {
                Keyword::where('campaign_id', $data->id)
                    ->update([
                        BaseModel::STATUS => $data_submit[BaseModel::STATUS]
                    ]);
            }

            if ($request->keywords) {
                foreach ($request->keywords as $index => $keywordData) {

                    $keywordAnd = collect($keywordData[Keyword::KEYWORD_AND] ?? [])->implode(',');
                    $name = $keywordData[BaseModel::NAME];

                    $dataSubmitKeyword = [
                        Keyword::CAMPAIGN_ID => $data->id,
                        BaseModel::NAME => $name,
                        Keyword::KEYWORD_OR => collect($keywordData[Keyword::KEYWORD_OR] ?? [])->implode(','),
                        Keyword::KEYWORD_AND => $keywordAnd,
                        Keyword::KEYWORD_EXCLUDE => collect($keywordData[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                        BaseModel::STATUS => 1,
                        BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                        BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                        "color" => $keywordData["colors"] ?? "",
                        "color_and" => $keywordData["color_and"] ?? "",
                        "label" => $keywordData["name"]
                    ];

                    $parentKeywordMain = Keyword::updateOrCreate(
                        ['id' => $keywordData['id']],
                        $dataSubmitKeyword
                    );

                    if ($keywordAnd) {
                        $name .= "," . $keywordAnd;
                        $dataSubmitKeyword = [

                            Keyword::CAMPAIGN_ID => $data->id,
                            BaseModel::NAME => $name,
                            Keyword::PARENT_ID => $parentKeywordMain->id,
                            Keyword::KEYWORD_OR => collect($keyword[Keyword::KEYWORD_OR] ?? [])->implode(','),
                            Keyword::KEYWORD_AND => $keywordAnd,
                            Keyword::KEYWORD_EXCLUDE => collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                            BaseModel::STATUS => 1,
                            BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                            BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                            "color" => $keywordData["colors"] ?? "",
                            "color_and" => $keywordData["color_and"] ?? "#",
                            "label" => $keywordData["name"]
                        ];

                        $parentKeyword = Keyword::updateOrCreate(
                            ['parent_id' => $keywordData['id']],
                            $dataSubmitKeyword
                        );

                    }

                    $this->extra_keyword(
                        $data->id,
                        $keywordData['name'],
                        $parentKeywordMain->id ?? $parentKeyword->id,
                        collect($keywordData[Keyword::KEYWORD_OR] ?? []),
                        $keywordAnd,
                        collect($keywordData[Keyword::KEYWORD_EXCLUDE] ?? []),
                        $keywordData,
                        true
                    );
                }
            }

            if (isset($request->delete_keyword)) {
                foreach ($request->delete_keyword as $delete_keyword) {
                    Keyword::where('id', $delete_keyword)->delete();
                    Keyword::where('parent_id', $delete_keyword)->delete();
                }
            }


            return parent::handleRespond($this->find($id));

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function destroy(Request $request)
    {
        if ($request->id) {
            $res = Campaign::where('id', $request->id)->first();
            $res->update([
                'status' => 0,
                'deleted_at' => Carbon::now()
            ]);
        }

        return parent::handleRespond($res);
    }

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];
        $campaign = Campaign::find($id);
        if ($id && $campaign) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $campaign;
            return $res;
        }
        return $res;
    }

    public function search(Request $request)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;

        $campaigns = Campaign::query()->offset($start)->limit($limit);


        if ($request->name) {
            $campaigns = $campaigns->where('name', 'like', "%$request->name%");
        }

        if ($request->status || $request->status === '0') {
            $campaigns = $campaigns->where('status', $request->status);
        }


        if ($request->organization_id) {
            $campaigns = $campaigns->where('organization_id', $request->organization_id);
        }

        if ($request->start_at) {
            $campaigns = $campaigns->where('start_at', '<=', $request->start_at);
        }

        if ($request->end_at) {
            $campaigns = $campaigns->where('end_at', '>=', $request->end_at);
        }

        // check organization
        if (!$this->user_login->is_admin) {
            $campaigns->where('organization_id', $this->user_login->organization_id);
        }


        $data = [];



        foreach ($campaigns->get() as $campaign) {
            $campaign->keyword = Keyword::where('campaign_id', $campaign->id)->whereNull('parent_id')->get();

            if ($campaign->keyword) {

                foreach ($campaign->keyword as $item) {

                    $item->name = $item->label ?? $item->name;
                    $keyword_colors = Keyword::where('campaign_id', $campaign->id)->where('parent_id', $item->id)->get('color');
                    $keyword_colors_or = Keyword::where('campaign_id', $campaign->id)
                        ->where('parent_id', $item->id)
                        ->orderBy('id')
                        ->get('color');

                    if ($keyword_colors) {
                        $item->keyword_or_color = explode(',', $keyword_colors_or->implode('color', ','));
                        $item->keyword_and_color = $item->color;

                    } else {
                        $item->keyword_and_color = $item->color;
                    }

                    $item->keyword_or = explode(",", $item->keyword_or);
                    $item->keyword_and = explode(",", $item->keyword_and);
                    $item->keyword_exclude = explode(",", $item->keyword_exclude);
                }
            }


            $organization_id = null;
            if ($request->organization_id) {
                $organization_id = $request->organization_id;
            } else {
                $organization_id = $campaign->organization_id;
            }

            $campaign->organization = Organization::find($organization_id)->name;
            if (isset($campaign->keyword[0]->created_by)) {
                $campaign->created_by = $this->find_created_by($campaign->keyword[0]->created_by);
            }
            // $data['list'][] = $campaign;
            $privacy_campaign = $campaign->privacy_campaign ?? null;
            $data['list'][] = $this->privacy($campaign, $privacy_campaign);
            $data['list'] = array_filter($data['list'], fn ($value) => !is_null($value));
            
            $data['keyword_limit'] = $this->organization_group->total_keyword;
            $data['frequency_default'] = $this->organization_group->frequency ?? 0;

        }


        $data['keyword_limit'] = $this->organization_group->total_keyword;
        $data['frequency_default'] = $this->organization_group->frequency ?? 0;

        return parent::handleRespond($data);
    }

    private function find_created_by($created_by) 
    {
        $created_by = User::where('id', $created_by)->first();
        return $created_by->name ?? null;

    }

    private function privacy($campaign, $privacy_campaign) {

        if ($campaign->deleted_at === null) {
            if ($this->user_login->is_admin) {
                return $campaign;
            }
    
            if (!$privacy_campaign || $privacy_campaign === 'share_organize' || $privacy_campaign === 'share_all') {
                if ($privacy_campaign === 'share_organize') {
                    if ($this->user_login->organization_id === $campaign->organization_id) {
                        return $campaign;
                    }
                } else if ($privacy_campaign === 'share_all') {
                    return $campaign;
                } else {
                    if ($this->user_login->organization_id === $campaign->organization_id) {
                        return $campaign;
                    }
                }
    
            } else if ($privacy_campaign === 'private') {
                if ($campaign->keyword[0]['created_by']) {
                    if ($this->user_login->id === $campaign->keyword[0]['created_by']) {
                        return $campaign ;
                    }
                }
            }            
        }

    }
    
}