<?php

namespace App\Http\Controllers\report;

use App\Exports\MonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Message;
use App\Models\MessageDeleteLog;
use App\Models\Organization;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LevelFourTableController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;

    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;
    private $source_id;
    private $keyword_id;

    public function __construct(Request $request)
    {
        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();

            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        // $this->start_date = $this->date_carbon($request->start_date) ?? null;
        // $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        //$this->source_id = $request->source === "all" ? "" : $request->source;
        $this->source_id = $request->source === "all" ? $this->getAllSource()->pluck('id')->toArray() : $request->source;
        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }

        if ($request->period === 'customrange') {
            $this->start_date_previous = $this->date_carbon($request->start_date_period);
            $this->end_date_previous = $this->date_carbon($request->end_date_period);
        }

    }

    public function messageLevelFour(Request $request)
    {

        $bullytype = [
            1 => 'Positive', 2 => 'Negative', 3 => 'Neutral',
            4 => 'NoBully', 5 => 'Physical Bully', 6 => 'Verbal Bullying',
            7 => 'Social Bullying', 8 => 'Cyber Bullying', 9 => 'Level 0',
            10 => 'Level 1', 11 => 'Level 2', 12 => 'Level 3',
        ];

        $media_type = [
            1 => 'Text', 2 => 'Image', 3 => 'Voice', 4 => 'Video',
        ];        

        $id = $request->input('id');

        $item = DB::table('message_results_2')
            ->join('messages', 'message_results_2.message_id', '=', 'messages.id')
            ->where('messages.id', $id)
            ->orderBy('message_results_2.media_type', 'asc')
            ->select([
                'messages.id AS id',
                'messages.message_id',
                'messages.full_message',
                'messages.link_message',
                'message_results_2.media_type',
                'message_results_2.process_message',
                'message_results_2.classification_sentiment_id',
                'message_results_2.classification_type_id',
                'message_results_2.classification_level_id',
            ])
            ->get();

        // $types = $this->getClassificationName($id);
        // error_log('types: '.json_encode($types));
        $media_type_data = [];
        $data_push = [];
        foreach ($item as $media_type_id) {
            // $group = $types->where('media_type', $media_type_id->media_type);
            // error_log('group: '.json_encode($group));
            $types = $this->getClassificationName($id, $media_type_id->media_type);
            $media_type_key = $media_type[$media_type_id->media_type] ?? "Unknown";
            foreach ($types as $groups) {
            $process_message = "";
                if ($groups->process_message != "") {
                    $process_message = $groups->process_message;
                } else {
                    $process_message = $item[0]->full_message;
                }
        
            $media_type_data[$groups->id] = [
                'media_type' => $media_type_key,
                'process_message' => $process_message,
                'sentiment' => $bullytype[$groups?->classification_sentiment_id] ?? null,
                'bully_level' => $bullytype[$groups?->classification_level_id] ?? null,
                'bully_type' => $bullytype[$groups?->classification_type_id] ?? null,
            ];
            }
        }
        
        // error_log(json_encode($media_type_data));
        // error_log('item: '.json_encode($item));
        $data_push = [
            "id" => $item[0]->id,
            "message_id" => $item[0]->message_id,
            "message_detail" => $item[0]->full_message,
            "link" => $item[0]->link_message,
            "media_type" => $media_type_data
        ];
        // error_log('data_push: '.json_encode($data_push));

        return parent::handleRespondPage($data_push);
    }

    private function getClassificationName($message_id, $media_type)
    {
        return DB::table('message_results_2')
            ->select([
                'id',
                'media_type',
                'process_message',
                'classification_sentiment_id',
                'classification_type_id',
                'classification_level_id',
            ])
            ->where('message_id', $message_id)
            ->where('media_type', $media_type)
            ->get();
    }
}