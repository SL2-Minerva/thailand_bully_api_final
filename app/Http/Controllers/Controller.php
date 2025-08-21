<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BaseModel;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $organization = null;
    protected $organization_group = null;
    protected $user_login;

    public function __construct(Request $request)
    {
        $this->request = $request;

        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();
            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        } else {
            return $this->handleRespond(null, [], 404, 'Unauthorized');
        }
    }


    /**
     * @param $data
     * @param $options
     * @param int $status
     * @param string $msg
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function handleRespond($data = null, $options = null, int $status = 200, string $msg = BaseModel::SUCCESS_TEXT)
    {
        $default = [
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ];

        return response()->json($options ? array_merge($options, $default) : $default, $status);
    }

    protected static function handleRespondPage($data = null, $meta = null, $options = null, int $status = 200, string $msg = BaseModel::SUCCESS_TEXT)
    {
        $default = [
            'status' => $status,
            'msg' => $msg,
            "meta" => $meta,
            'data' => $data
        ];

        return response()->json($options ? array_merge($options, $default) : $default, $status);
    }

    /**
     * function for return only notfound or something ele not excust
     * @param $data
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function handleNotFound($data, int $status = 404): \Illuminate\Http\JsonResponse
    {
        $default = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($data) {
            $default = $data;
        }
        return response()->json($default, $status);
    }

    /**
     * @param $exception
     * @param $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function handleErrorRespond($exception, $status = 500): \Illuminate\Http\JsonResponse
    {
        $default = [
            'status' => $status,
            'msg' => $exception->getMessage(),
            'data' => $exception->getTraceAsString()
        ];

        return response()->json($default, $status);
    }

    /**
     * @param $request
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return array
     */
    protected static function validate($request, array $rules, array $messages = [], array $customAttributes = [])
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->passes()) {
            return $request->all();
        }

        return [
            'invalid' => 'invalid',
            'msg' => $validator->errors()->all()
        ];
    }

    protected static function audi_log(
        $request = null,
        $transaction = 'CREATE',
        $primary_key = null,
        $user_id = null,
        $source = null,
        $original = null,
        $changed = null
    )
    {
        $datasubmit = [
            AuditLog::TRANSACTION => $transaction,
            AuditLog::PRIMARY_KEY => $primary_key,
            BaseModel::USER_ID => $user_id,
            BaseModel::SOURCE => $source,
            AuditLog::ORIGINAL => $original,
            AuditLog::CHANGED => $changed
        ];
        AuditLog::create($datasubmit);


    }

    public static function is_existed($id, $mode): bool
    {
        return (boolean)$mode::find($id);
    }

    public static function list($request, $model, $condition = null)
    {

        $page = $request->page ?? null;
        $limit = $request->limit ?? 5;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start - 1;

        return $model::offset($start)->limit($limit)->orderBy('created', 'desc');
    }

    public static function uploadImage($file, $path = 'content') // fix
    {
        if ($file) {

            return $file->store($path ?? "organization-content", 'public');
        }
    }

    public static function date_carbon($date)
    {
        return Carbon::parse($date)->format('Y-m-d');
    }

    public static function get_previous_date($date, $period)
    {
        $date = match ($period) {
            "daily" => Carbon::parse($date)->subDays(1)->format('Y-m-d'),
            "yesterday" => Carbon::parse($date)->subDays(1)->format('Y-m-d'),
            "last7days" => Carbon::parse($date)->subDays(7)->format('Y-m-d'),
            "last30days" => Carbon::parse($date)->subDays(30)->format('Y-m-d'),
            "thismonth" => Carbon::parse($date)->subMonths(1)->format('Y-m-d'),
            "lastmonth" => Carbon::parse($date)->subMonths(1)->format('Y-m-d'),
            "customrange" => Carbon::parse($date)->format('Y-m-d'),
            default => Carbon::parse($date)->subDays(1)->format('Y-m-d'),
        };

        return $date;
    }

    public static function diff_date($start_date, $end_date)
    {
        $start_date = Carbon::createFromFormat('Y-m-d H:s:i', $start_date . ' 00:00:00');
        $end_date = Carbon::createFromFormat('Y-m-d H:s:i', $end_date . ' 00:00:00');
        $length = $start_date->diffInDays($end_date->addDays(1));
        return $length != 0 ? $length : 1;
    }

    public static function point_two_digits($number, $digit = null)
    {
        if ($digit === null) {
            $digit = 2;
        }
        return $number !== null ? number_format($number, $digit) : null;
    }

    public static function listSource()
    {
        $sources = Sources::where('status', 1)->get();

        if (auth('api')->user()) {
            $user_login = auth('api')->user();
            $organization = Organization::find($user_login->organization_id);
            $organization_group = UserOrganizationGroup::find($organization->organization_group_id);

            if (!$user_login->is_admin) {
                $sources = Sources::where('status', 1)->whereIn('name', $organization_group->platform)->get();
            }
        }


        $data['labels'] = [];

        foreach ($sources as $source) {
            $data['labels'][] = $source->name;
        }

        return $data;
    }

    protected function matchSourceName($source, $sourceId)
    {
        foreach ($source as $item) {
            if ($item->id == $sourceId) {
                return $item->name;
            }
        }
        return "";
    }
    
    protected function matchSourceImage($source, $sourceId)
    {
    foreach ($source as $item) {
        if ($item->id == $sourceId) {
            return $item->image;
            }
        }   
        return "";
    }


    protected function matchSource($source, $sourceId)
    {
        foreach ($source as $item) {
            if ($item->id == $sourceId) {
                return $item;
            }
        }
        return null;
    }

    protected function matchSourceByName($source, $sourceId)
    {
        foreach ($source as $item) {
            if ($item->name == $sourceId) {
                return $item;
            }
        }
        return null;
    }


    protected function selectData($select)
    {
        return match ($select) {
            "all" => 0,
            "top20" => 20,
            "top50" => 50,
            "top100" => 100,
            default => 10
        };
    }

    protected function getCampaign($campaign_id)
    {
        return DB::table('campaigns')->where('id', $campaign_id)->first();
    }

    protected function getAllSource()
    {
        $source_group = $this->organization_group->platform;
        if ($this->user_login->is_admin) {
            return Sources::where('status', 1)->orderBy("id","asc")->get();
        } else {
            return Sources::where('status', 1)
                ->whereIn('name', $source_group)
                ->get();
        }
    }

    protected function findKeywords($campaign_id, $keyword_id)
    {

        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($keyword_id) {
            $keyword->whereIn('id', $keyword_id);
        }
        return $keyword->get();
    }

    protected function matchKeywordName($keyword, $keywordId)
    {
        foreach ($keyword as $item) {
            if ($item->id == $keywordId) {
                return $item->name;
            }
        }
        return "";
    }

    protected function matchCampaign($campaign, $campaignId)
    {
        foreach ($campaign as $item) {
            if ($item->id == $campaignId) {
                return $item->name;
            }
        }
        return "";
    }

    protected function matchClassificationName($classify, $classifyId)
    {
        foreach ($classify as $item) {
            if ($item->id == $classifyId) {
                return $item->name;
            }
        }
        return "";
    }

    protected function matchClassificationColor($classify, $classifyId)
    {
        foreach ($classify as $item) {
            if ($item->id == $classifyId) {
                return $item->color;
            }
        }
        return "";
    }

    protected function packClassificationTypeName($classificationTypes, $item, $message)
    {
        if ($item->classification_type_id) {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_sentiment_id) {
                    $message['sentiment'] = $classificationType->name;
                    break;
                }
            }
        } else if ($item->classification_type_id) {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_type_id) {
                    $message['bully_type'] = $classificationType->name;
                    break;
                }
            }
        } else {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_level_id) {
                    $message['bully_level'] = $classificationType->name;
                    break;
                }
            }
        }
        return $message;
    }

    protected function packObjectClassificationTypeName($classificationTypes, $item, $message)
    {
        if ($item->classification_type_id == 1) {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_sentiment_id) {
                    $message->sentiment = $classificationType->name;
                    break;
                }
            }
        } else if ($item->classification_type_id == 2) {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_type_id) {
                    $message->bully_type= $classificationType->name;
                    break;
                }
            }
        } else {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_level_id) {
                    $message->bully_level = $classificationType->name;
                    break;
                }
            }
        }
        return $message;
    }


    protected function getClassificationJoinTypeMaster()
    {
        return DB::table('classifications')->select("classifications.*", "classification_types.name as classification_type_name")
            ->leftJoin('classification_types', 'classifications.classification_type_id', '=', 'classification_types.id')
            ->get();
    }
    
    protected function getClassificationMaster()
    {
        return DB::table('classifications')
            ->get();
    }    
}




