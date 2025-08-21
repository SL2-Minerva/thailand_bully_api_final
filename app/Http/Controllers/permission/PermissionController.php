<?php

namespace App\Http\Controllers\permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\role_permission\PermissionCreateRequest;
use App\Http\Requests\role_permission\PermissionDeleteRequest;
use App\Http\Requests\role_permission\PermissionUpdateRequest;
use App\Models\BaseModel;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Mockery\Exception;

class PermissionController extends Controller
{

    public function store(PermissionCreateRequest $request)
    {


        $role_id = $request->role_id;
        if (!parent::is_existed($role_id, UserRole::class)) return parent::handleNotFound();

        $data = [
            BaseModel::ROLE_ID => $request->role_id,
            UserPermission::AUTHORIZED_CREATE => (boolean)$request->can_create,
            UserPermission::AUTHORIZED_EDIT => (boolean)$request->can_edit,
            UserPermission::AUTHORIZED_DELETE => (boolean)$request->can_delete,
            UserPermission::AUTHORIZED_VIEW => (boolean)$request->can_view,
            UserPermission::AUTHORIZED_EXPORT => (boolean)$request->can_export,
        ];


        try {
            $permission = UserPermission::create($data);
            return parent::handleRespond($permission);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function update(PermissionUpdateRequest $request, $action = null)
    {
        $permission_id = $request->id;
        try {
            $res = $this->find($permission_id);
            if ($res[BaseModel::STATUS] !== 200) {
                $permission = $res[BaseModel::DATA_TEXT];

                if ($action === BaseModel::UPDATE_TEXT) {
                    $permission->update($request->all());
                    return parent::handleRespond($permission);
                }
                $permission->update([BaseModel::STATUS => false]);
                return parent::handleRespond(null);
            }

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

        if ($id && $permision = UserPermission::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $permision;
            return $res;
        }
        return $res;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $permission_id = $request->id;

        $res = $this->find($permission_id);
        if ($res[BaseModel::STATUS] !== 200) return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        return parent::handleRespond($res);
    }

    public function destroy(PermissionDeleteRequest $request)
    {
        return $this->update($request, BaseModel::DELETE_TEXT);
    }

    public function report_chart_list(Request $request)
    {
        $data = [
            [
                "groupName" => "Overall Dashboard",
                "title" => "Percentage of Messages",
                "id" => "1"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Daily Message",
                "id" => "2"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Total Message",
                "id" => "3"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Total Engagement",
                "id" => "4"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Total Account",
                "id" => "5"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Keywords",
                "id" => "6"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Main Keyword",
                "id" => "7"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Top Sites",
                "id" => "8"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Top Hashtag",
                "id" => "9"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Sentiment Score",
                "id" => "10"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Comment Sentiment",
                "id" => "11"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Sentiment Level",
                "id" => "12"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Word Clouds",
                "id" => "13"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Total Message(Word clouds)",
                "id" => "14"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Word Clouds(Platforms)",
                "id" => "15"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Accounts(Platforms)",
                "id" => "17"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Word Clouds(Sentiment)",
                "id" => "18"
            ],
            [
                "groupName" => "Overall Dashboard",
                "title" => "Accounts(Sentiment)",
                "id" => "19"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Percentage of Message",
                "id" => "20"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Daily Messages",
                "id" => "21"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Day",
                "id" => "22"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Time",
                "id" => "23"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Devices",
                "id" => "24"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Account",
                "id" => "25"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Channel",
                "id" => "26"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Sentiment",
                "id" => "27"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Bully Level",
                "id" => "28"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Message by Bully Type",
                "id" => "29"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Number of Accounts",
                "id" => "30"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Period over Period comparison(Messages)",
                "id" => "31"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Period over Period comparison(Accounts)",
                "id" => "32"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Day&Time Comparison",
                "id" => "33"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Day&Time by Sentiment",
                "id" => "34"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Day&Time by Bully Level",
                "id" => "35"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Day&Time by Bully Type",
                "id" => "36"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Period over Period comparison(Channel/Platforms)",
                "id" => "37"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Period over Period comparison(Devices)",
                "id" => "38"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Period over Period comparison(Channel vs Devices)",
                "id" => "39"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Percentage of Keyword Comparison By Channel",
                "id" => "40"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Percentage of Keyword Comparison By Sentiment",
                "id" => "41"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Percentage of Keyword Comparison By Bully Level",
                "id" => "42"
            ],
            [
                "groupName" => "Voice Dashboard",
                "title" => "Percentage of Keyword Comparison By Bully Type",
                "id" => "43"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Percentage of Channel",
                "id" => "44"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Daily Channel",
                "id" => "45"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Day",
                "id" => "46"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Time",
                "id" => "47"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Devices",
                "id" => "48"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Account",
                "id" => "49"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Sentiment",
                "id" => "50"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Bully Level",
                "id" => "51"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Bully Type",
                "id" => "52"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Period over Period Comparison",
                "id" => "53"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Engagement Rate",
                "id" => "54"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Sentiment Score",
                "id" => "55"
            ],
            [
                "groupName" => "Channel Dashboard",
                "title" => "Channel by Sentiment & Sentiment Level",
                "id" => "56"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Percentage of Engagement Trans",
                "id" => "57"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Daily Engagement",
                "id" => "58"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement by Day",
                "id" => "59"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement by Time",
                "id" => "60"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement by Devices",
                "id" => "61"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement by Account",
                "id" => "62"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement by Channel",
                "id" => "63"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Percentage of Engagement Type ",
                "id" => "64"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Daily Engagement Type",
                "id" => "65"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engangement Type By Day",
                "id" => "66"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement Type By Time",
                "id" => "67"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement Type By Devices",
                "id" => "68"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement Type By Account",
                "id" => "69"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement Type By Channel",
                "id" => "70"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Total Engagement(Period over Period Comparison)",
                "id" => "71"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement Comparison by Channels",
                "id" => "72"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement Comparison by Sentiment",
                "id" => "73"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Engagement Type Comparison",
                "id" => "74"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title" => "Summary Engagement by Account",
                "id" => "75"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Daily Sentiment",
                "id" => "76"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Percentage of Sentiment",
                "id" => "77"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment by Day",
                "id" => "78"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment by Time",
                "id" => "79"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment by Devices",
                "id" => "80"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment by Account",
                "id" => "81"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment by Channel",
                "id" => "82"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment by Bully Level",
                "id" => "83"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment by Bully Type",
                "id" => "84"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Total Message",
                "id" => "85"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Engagement Comparison by Channel",
                "id" => "86"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Engagement Comparison by Engagement Type",
                "id" => "87"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment Score",
                "id" => "88"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Engagement Type Comparison",
                "id" => "89"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Summary Sentiment Score by Account",
                "id" => "90"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Summary Sentiment Score by Channel",
                "id" => "91"
            ],
            [
                "groupName" => "Sentiment Dashboard",
                "title" => "Sentiment Type by Keywords",
                "id" => "92"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Percentage of Bully Level",
                "id" => "93"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Daily Message of Bully Level",
                "id" => "94"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Level By Day",
                "id" => "95"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Level By Time",
                "id" => "96"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Level By Devices",
                "id" => "97"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Level by Account",
                "id" => "98"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Level by Channel",
                "id" => "99"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Level by Sentiment",
                "id" => "100"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Percentage of Bully Type",
                "id" => "101"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Daily Message of Bully Type",
                "id" => "102"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Type By Day",
                "id" => "103"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Type By Time",
                "id" => "104"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Type By Devices",
                "id" => "105"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Type by Account",
                "id" => "106"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Type by Channel",
                "id" => "107"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Bully Type by Sentiment",
                "id" => "108"
            ],
            [
                "groupName" => "Bully Dashboard",
                "title" => "Share of Channel",
                "id" => "109"
            ],
            [
                "groupName" => "Engagement Dashboard",
                "title"  =>  "Engagement Trans by Engagement Type",
                "id"  =>  "110"
            ],
            [
                "groupName" => "Monitoring Dashboard",
                "title" => "Monitoring",
                "id" => "111"
            ]
        ];

        return parent::handleRespond($data);
    }
}
