<?php

namespace App\Http\Controllers\report;

use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Sources;
use App\Models\Classification;
use App\Models\Keyword;

class BullyDashboardController extends Controller
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
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        //$this->source_id = $request->source === "all" ? "" : $request->source;
        $this->source_id = $request->source === "all" ? $this->getAllSource()->pluck('id')->toArray() : $request->source;
        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }

        // if (auth('api')->user()) {
        //     $this->user_login = auth('api')->user();


        //     $this->organization = Organization::find($this->user_login->organization_id);
        //     $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        // }

        if ($request->period === 'customrange') {
            $this->start_date_previous = $this->date_carbon($request->start_date_period);
            $this->end_date_previous = $this->date_carbon($request->end_date_period);
        }

    }

    public function dailyBy()
    {
        $data = null;
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();
        $current = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 3)->get();

        $previous = $this->raw_message_classification_name($keywords, $this->start_date_previous, $this->end_date_previous, 3)->get();

        $prcentage_of_messages_current['prcentage_of_messages_current'] = $this->PercentageToCal($current, $classifications, $this->start_date, $this->end_date);
        $prcentage_of_messages_current['prcentage_of_messages_previous'] = $this->PercentageToCal($previous, $classifications, $this->start_date_previous, $this->end_date_previous);


        $data['percentage_bully'] = $prcentage_of_messages_current;
        $data['daily_bully'] = $this->DailyBullyGroup($current, $keywords, $classifications, $sources);
        return parent::handleRespond($data);
    }

    public function bullyBy()
    {

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();
        $current = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 3)->get();

        $currentSentiment = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 1)->get();

        $data['bully_by_day'] = $this->BullyByDayGroup($current, $classifications);
        $data['bully_by_time'] = $this->BullyByTimeGroup($current, $classifications);
        $data['bully_by_device'] = $this->BullyByDeviceGroup($current, $classifications);
        $data['bully_by_account'] = $this->BullyByAccountGroup($current, $classifications);
        $data['bully_by_channel'] = $this->BullyByChannelGroup($current, $classifications, $sources);
        $data['bully_by_sentiment'] = $this->BullyBySentimentGroup($current, $currentSentiment, $classifications);

        return parent::handleRespond($data);
    }

    private function PercentageToCal($items, $classifications, $start_date, $end_date)
    {
        $data = [];
        $message_total = 0;

        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }

            $message_total += 1;
            //error_log('classification :'.$classification_id);
            if (isset($data[$classification_id])) {
                $data[$classification_id]['value']['total'] += 1;
            } else {
                $data[$classification_id]['bully_level'] = $this->matchClassificationName($classifications, $classification_id);
                /*$data[$item->classification_id]['campaign_id'] = $item->campaign_id;
                $data[$item->classification_id]['campaign_name'] = $item->campaign_name;*/
                $data[$classification_id]['value']['total'] = 1;
                $data[$classification_id]['value']['date'] = Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y');
            }
        }

        foreach ($data as $key => $value) {
            $data[$key]['value']['percentage'] = $this->point_two_digits(($data[$key]['value']['total'] / $message_total) * 100);
            $data[$key]['value']['total'] = self::point_two_digits($message_total, 0);
        }

        if ($data) {
            $data = array_values($data);
        }
        return $data;
    }

    private function DailyBullyGroup($items, $keywords, $classifications, $sources)
    {
        $data = null;
        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }

            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$classification_id])) {

                if (isset($data[$classification_id]['value'][$date_format])) {
                    $data[$classification_id]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$classification_id]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $this->matchKeywordName($keywords, $item->keyword_id),
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$classification_id] = [
                    "classification_id" => $classification_id,
                    "bully_level" => $this->matchClassificationName($classifications, $classification_id),
                    "source_id" => $item->source_id,
                    "source_name" => $this->matchSourceName($sources, $item->source_id),/*
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                ];
                $data[$classification_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    "keyword_name" => $this->matchKeywordName($keywords, $item->keyword_id),
                    // 'date_m' => $item->date_m,
                    'date_m' => $date_format,
                    'total_at_date' => 1
                ];
            }
        }

        if ($data) {

            foreach ($data as $key => $item) {
                if ($item) {
                    $data[$key]['value'] = array_values($item['value']);
                }
            }
        }

        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    private function BullyByDayGroup($items, $classifications)
    {
        $data = null;

        $data['labels'] = [
            "Mon",
            "Tue",
            "Wed",
            "Thu",
            "Fri",
            "Sat",
            "Sun"
        ];

        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }
            
            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);


            if (isset($data['value'][$classification_id])) {
                $data['value'][$classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$classification_id] = [
                    'id' => $classification_id,
                    'classification_id' => $classification_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $classification_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$classification_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyByTimeGroup($items, $classifications)
    {
        $data = null;
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        foreach ($items as $item) {

            $sixAM = Carbon::parse("06:00:00");
            $time = Carbon::parse($item->date_m)->format('H:i:s');
            $index_label = 3;

            if (Carbon::parse($time)->lt($sixAM)) {
                $index_label = 0;
            }

            if (Carbon::parse($time)->between($sixAM, Carbon::parse("12:00:00"))) {
                $index_label = 1;
            }

            if (Carbon::parse($time)->between(Carbon::parse("12:00:00"), Carbon::parse("18:00:00"))) {
                $index_label = 2;
            }

            if (Carbon::parse($time)->gt(Carbon::parse("18:00:00"))) {
                $index_label = 3;
            }


            if (isset($data['value'][$item->classification_level_id])) {
                $data['value'][$item->classification_level_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$item->classification_level_id] = [
                    'id' => $item->classification_level_id,
                    'classification_id' => $item->classification_level_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_level_id),
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->classification_level_id]['data'][$index_label] += 1;
            }
        }
        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyByDeviceGroup($items, $classifications)
    {
        $data = null;
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $data['value'] = null;

        foreach ($items as $item) {

            $index_label = null;

            if ($item->device == 'android') {
                $index_label = 0;
            }

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp' || $item->device == 'website') {
                $index_label = 2;
            }

            if ($index_label != null || $index_label != '') {
                if (isset($data['value'][$item->classification_level_id])) {
                    $data['value'][$item->classification_level_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->classification_level_id] = [
                        'id' => $item->classification_level_id,
                        'classification_id' => $item->classification_lecvel_id,
                        'keyword_name' => $this->matchClassificationName($classifications, $item->classification_level_id),
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->classification_level_id]['data'][$index_label] += 1;
                }
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyByAccountGroup($items, $classifications)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];


        foreach ($items as $infulencer) {
            if ($infulencer->reference_message_id == '') {
                if (!isset($data['value'][$infulencer->classification_level_id]['data'][0])) {
                    $data['value'][$infulencer->classification_level_id]['id'] = $infulencer->classification_level_id;
                    $data['value'][$infulencer->classification_level_id]['classification_id'] = $infulencer->classification_level_id;
                    $data['value'][$infulencer->classification_level_id]['keyword_name'] = $this->matchClassificationName($classifications, $infulencer->classification_level_id);
                    // $data['value'][$infulencer->classification_id]['data'][0] = 0;
                    $data['value'][$infulencer->classification_level_id]['data'] = [0, 0];


                }
                if (!$infulencer->reference_message_id) {
                    $data['value'][$infulencer->classification_level_id]['data'][0] += 1;
                }
            }
        }


        foreach ($items as $follower) {
            if ($follower->reference_message_id != '') {
                if (isset($data['value'][$follower->classification_level_id]['data'][1])) {
                    if ($follower->reference_message_id) {
                        $data['value'][$follower->classification_level_id]['data'][1] += 1;
                    }
                } else {
                    $data['value'][$follower->classification_level_id]['id'] = $follower->classification_level_id;
                    $data['value'][$follower->classification_level_id]['keyword_name'] = $this->matchClassificationName($classifications, $follower->classification_level_id);
                    // $data['value'][$follower->classification_id]['data'][1] = 0;
                    // $data['value'][$follower->classification_id]['data'] = [0 => 0, 1 => 0];
                    $data['value'][$follower->classification_level_id]['data'] = [0, 0];

                    if ($follower->reference_message_id) {
                        $data['value'][$follower->classification_level_id]['data'][0] += 1;
                    }
                }
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        return $data;
    }

    private function BullyByChannelGroup($items, $classifications, $sources)
    {
        $source_ids = parent::listSource();
        $data['labels'] = [];

        foreach ($source_ids as $source_id) {
            $data['labels'] = $source_id;
        }

        $data['value'] = null;

        foreach ($items as $item) {

            $source_name = $this->matchSourceName($sources, $item->source_id);

            $index_label = array_search($source_name, $data['labels']);

            if (!isset($data['value'][$item->classification_level_id])) {
                $data['value'][$item->classification_level_id] = [
                    'id' => $item->classification_level_id,
                    'classification_id' => $item->classification_level_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_level_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                ];

            }
            $data['value'][$item->classification_level_id]['data'][$index_label] += 1;

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyBySentimentGroup($current, $currentSentiment, $classifications)
    {
        $data['labels'] = ["Positive", "Neutral", "Negative"];

        $data['value'][9] = ["id" => 9, "classification_id" => 9, "keyword_name" => "Level 0", "data" => [0, 0, 0]];
        $data['value'][10] = ["id" => 10, "classification_id" => 10, "keyword_name" => "Level 1", "data" => [0, 0, 0]];
        $data['value'][11] = ["id" => 11, "classification_id" => 11, "keyword_name" => "Level 2", "data" => [0, 0, 0]];
        $data['value'][12] = ["id" => 12, "classification_id" => 12, "keyword_name" => "Level 3", "data" => [0, 0, 0]];

        $analysis = [];
        //error_log(json_encode($anylsys));
        foreach ($current as $item) {
            if (!isset($item->result_message_id) || !isset($item->classification_level_id)) {
                continue;
            }
            $analysis[$item->result_message_id]['bully_level'] = $this->matchClassificationName($classifications, $item->classification_level_id);
        }

        foreach ($currentSentiment as $item) {
            if (!isset($item->result_message_id) || !isset($item->classification_sentiment_id)) {
                continue;
            }
            $analysis[$item->result_message_id]['sentiment'] = $this->matchClassificationName($classifications, $item->classification_sentiment_id);
        }

        foreach ($analysis as $result_message_id => $info) {
            if (!isset($info['bully_level']) || !isset($info['sentiment'])) {
                continue;
            }

            $level = $info['bully_level'];
            $sentiment = $info['sentiment'];

            switch ($level) {
                case 'Level 1':
                    $index_data = 10;
                    break;
                case 'Level 2':
                    $index_data = 11;
                    break;
                case 'Level 3':
                    $index_data = 12;
                    break;
                default:
                    $index_data = 9;
            }

            $index_label = array_search($sentiment, $data['labels']);
            if ($index_label !== false) {
                $data['value'][$index_data]['data'][$index_label] += 1;
            }
        }

        $data['value'] = array_values($data['value']);
        return $data;
    }

    public function dailyTypeBy()
    {
        $data = null;

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();

        $current = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 2)->get();
        $currentSentiment = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 1)->get();

        $previous = $this->raw_message_classification_name($keywords, $this->start_date_previous, $this->end_date_previous, 2)->get();

        $prcentage_of_messages_current['prcentage_of_messages_current'] = $this->PercentageToCal($current, $classifications, $this->start_date, $this->end_date);
        $prcentage_of_messages_current['prcentage_of_messages_previous'] = $this->PercentageToCal($previous, $classifications, $this->start_date_previous, $this->end_date_previous);

        $data['bully_type_percentage'] = $prcentage_of_messages_current;
        $data['bully_type_daily'] = $this->BullyTypeDailyGroup($current, $classifications, $sources, $keywords);
        $data['bully_type_by_day'] = $this->BullyTypeByDayGroup($current, $classifications);
        $data['bully_type_by_time'] = $this->BullyTypeByTimeGroup($current, $classifications);
        $data['bully_type_by_device'] = $this->BullyTypeByDeviceGroup($current, $classifications);
        $data['bully_type_by_account'] = $this->BullyTypeByAccountGroup($current, $classifications);
        $data['bully_type_by_channel'] = $this->BullyTypeByChannelGroup($current, $classifications, $sources);
        $data['bully_type_by_sentiment'] = $this->BullyTypeBySentimentGroup($current, $currentSentiment, $classifications);
        return parent::handleRespond($data);
    }

    private function BullyTypeDailyGroup($items, $classifications, $sources, $keywords)
    {
        $data = null;

        foreach ($items as $item) {

            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }
            
            if (isset($data[$classification_id])) {

                if (isset($data[$classification_id]['value'][$date_format])) {
                    $data[$classification_id]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$classification_id]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $this->matchKeywordName($keywords, $item->keyword_id),
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$classification_id] = [
                    "classification_id" => $classification_id,
                    "bully_level" => $this->matchClassificationName($classifications, $classification_id),
                    "source_id" => $item->source_id,
                    "source_name" => $this->matchSourceName($sources, $item->source_id)/*,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                ];
                $data[$classification_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                    // 'date_m' => $item->date_m,
                    'date_m' => $date_format,
                    'total_at_date' => 1
                ];
            }
        }

        if ($data) {

            foreach ($data as $key => $item) {
                if ($item) {
                    $data[$key]['value'] = array_values($item['value']);
                }
            }
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    private function BullyTypeByDayGroup($items, $classification)
    {
        $data = null;

        $data['labels'] = [
            "Mon",
            "Tue",
            "Wed",
            "Thu",
            "Fri",
            "Sat",
            "Sun"
        ];

        foreach ($items as $item) {

            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);

            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }            

            if (isset($data['value'][$classification_id])) {
                $data['value'][$classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$classification_id] = [
                    'id' => $classification_id,
                    'classification_id' => $classification_id,
                    'keyword_name' => $this->matchClassificationName($classification, $classification_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$classification_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeByTimeGroup($items, $classification)
    {
        $data = null;
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        foreach ($items as $item) {

            $sixAM = Carbon::parse("06:00:00");
            $time = Carbon::parse($item->date_m)->format('H:i:s');
            $index_label = 3;

            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }

            if (Carbon::parse($time)->lt($sixAM)) {
                $index_label = 0;
            }

            if (Carbon::parse($time)->between($sixAM, Carbon::parse("12:00:00"))) {
                $index_label = 1;
            }

            if (Carbon::parse($time)->between(Carbon::parse("12:00:00"), Carbon::parse("18:00:00"))) {
                $index_label = 2;
            }

            if (Carbon::parse($time)->gt(Carbon::parse("18:00:00"))) {
                $index_label = 3;
            }


            if (isset($data['value'][$classification_id])) {
                $data['value'][$classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$classification_id] = [
                    'id' => $classification_id,
                    'classification_id' => $classification_id,
                    'keyword_name' => $this->matchClassificationName($classification, $classification_id),
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$classification_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeByDeviceGroup($items, $classification)
    {
        $data = null;
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $data['value'] = null;

        foreach ($items as $item) {
            
            $index_label = null;

            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }

            if ($item->device == 'android') {
                $index_label = 0;
            }

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp' || $item->device == 'website') {
                $index_label = 2;
            }

            if ($index_label != null || $index_label != '') {
                if (isset($data['value'][$classification_id])) {
                    $data['value'][$classification_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$classification_id] = [
                        'id' => $classification_id,
                        'classification_id' => $classification_id,
                        'keyword_name' => $this->matchClassificationName($classification, $classification_id),
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$classification_id]['data'][$index_label] += 1;
                }
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeByAccountGroup($items, $classifications)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];


        foreach ($items as $infulencer) {
            if (isset($infulencer->classification_level_id)){
                $classification_id = $infulencer->classification_level_id;
            }
            if (isset($infulencer->classification_type_id)){
                $classification_id = $infulencer->classification_type_id;
            }
            if (isset($infulencer->classification_sentiment_id)){
                $classification_id = $infulencer->classification_sentiment_id;
            }     
            $classificationName = $this->matchClassificationName($classifications, $classification_id);

            if ($infulencer->reference_message_id == '') {
                if (!isset($data['value'][$classification_id]['data'][0])) {
                    $data['value'][$classification_id]['id'] = $classification_id;
                    $data['value'][$classification_id]['classification_id'] = $classification_id;
                    $data['value'][$classification_id]['keyword_name'] = $classificationName;
                    $data['value'][$classification_id]['data'][0] = 0;

                }
                $data['value'][$classification_id]['data'][0] += 1;
            } else {
                if (!isset($data['value'][$classification_id]['data'][1])) {
                    $data['value'][$classification_id]['id'] = $classification_id;
                    $data['value'][$classification_id]['keyword_name'] = $classificationName;
                    $data['value'][$classification_id]['data'][1] = 0;
                }
                $data['value'][$classification_id]['data'][1] += 1;
            }
        }


        // if (isset($data['value'])) {
        //     $data['value'] = array_values($data['value']);
        // }
            if (isset($data['value'])) {
                foreach ($data['value'] as &$val) {
                    // บังคับให้ data เป็น array ที่มี index 0 และ 1 เสมอ
                    $val['data'][0] = $val['data'][0] ?? 0;
                    $val['data'][1] = $val['data'][1] ?? 0;
                    ksort($val['data']); // เรียงตาม index
                    $val['data'] = array_values($val['data']); // เปลี่ยนเป็น array ปกติ [0 => x, 1 => y]
                }
                $data['value'] = array_values($data['value']);
            }

        return $data;
    }

    private function BullyTypeByChannelGroup($items, $classifications, $sources)
    {
        $data['labels'] = [];

        foreach ($sources as $source_id) {
            $data['labels'][] = $source_id->name;
        }

        $data['value'] = null;

        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }

            $sourceName = $this->matchSourceName($sources, $item->source_id);
            $index_label = array_search($sourceName, $data['labels']);

            if (!isset($data['value'][$classification_id])) {
                $data['value'][$classification_id] = [
                    'id' => $classification_id,
                    'classification_id' => $classification_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_type_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                ];

            }
            $data['value'][$classification_id]['data'][$index_label] += 1;

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }
    
    private function BullyTypeBySentimentGroup($current, $currentSentiment, $classifications)
    {
        //error_log(json_encode($currentSentiment));
        $data['labels'] = ["Positive", "Neutral", "Negative"];
        $bully_types = Classification::where('classification_type_id', 2)->get();

        $data['value'] = [];
        foreach ($bully_types as $bully_type) {
            $data['value'][$bully_type->id] = [
                'id' => $bully_type->id,
                'classification_id' => $bully_type->id,
                'keyword_name' => $bully_type->name,
                'data' => [0, 0, 0]
            ];
        }

        $analysis = [];
        //error_log(json_encode($anylsys));
        foreach ($current as $item) {
            if (!isset($item->result_message_id) || !isset($item->classification_type_id)) {
                continue;
            }
            $analysis[$item->result_message_id]['bully_type'] = $item->classification_type_id;
        }

        foreach ($currentSentiment as $item) {
            if (!isset($item->result_message_id) || !isset($item->classification_sentiment_id)) {
                continue;
            }
            $analysis[$item->result_message_id]['sentiment'] = $this->matchClassificationName($classifications, $item->classification_sentiment_id);
        }

        foreach ($analysis as $item) {
            if (
                !isset($item['bully_type']) ||
                !isset($item['sentiment']) ||
                !array_key_exists($item['bully_type'], $data['value'])
            ) {
                continue;
            }

            $index_label = array_search($item['sentiment'], $data['labels']);
            if ($index_label !== false) {
                $data['value'][$item['bully_type']]['data'][$index_label]++;
            }
        }

        $data['value'] = array_values($data['value']);
        return $data;
    }

    public function bullyTypeBy()
    {
        $data = null;

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();
        $currentType = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 2)->get();

        $currentLevel = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 3)->get();

        $data['bully_type_by_level'] = $this->BullyChartLevelGroup($currentLevel, $classifications);
        $data['bully_chart_type'] = $this->BullyChartTypeGroup($currentType, $classifications);
        $data['bully_chart_level'] = $this->BullyLevelLevelGroup($currentLevel, $classifications, $sources);
        $data['bully_table_type'] = $this->BullyTableTypeGroup($currentType, $classifications, $sources);

        return parent::handleRespond($data);
    }

    private function BullyChartLevelGroup($items, $classifications)
    {
        $all = 0;
        $data = [];
        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }
            
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += 1
            ];

            if (isset($data[$classification_id])) {
                $data[$classification_id]['data'] += 1;
                $data["all"]['data'] += 1;


            } else {
                $data[$classification_id] = [
                    'keyword_name' => $this->matchClassificationName($classifications, $classification_id),
                    'classification_id' => $classification_id,
                    'data' => 0,
                ];
            }
        }

        if (!$data) {
            return $data;
        }

        return array_values($data);
    }

    private function BullyChartTypeGroup($items, $classifications)
    {

        $all = 0;

        $data = [];
        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += 1
            ];

            if (isset($data[$classification_id])) {
                $data[$classification_id]['data'] += 1;
                $data["all"]['data'] += 1;


            } else {
                $data[$classification_id] = [
                    'keyword_name' => $this->matchClassificationName($classifications, $classification_id),
                    'classification_id' => $classification_id,
                    'data' => 0
                ];
            }
        }

        if (!$data) {
            return $data;
        }

        return array_values($data);
    }

    private function BullyLevelLevelGroup($items, $classifications, $sourceArr)
    {
        $soures = parent::listSource();
        $sourceImages = DB::table('sources') ->select('name', 'image') ->whereIn('name', $soures['labels']) ->get() ->keyBy('name');
        $items = collect($items);

        $anylsys = [];
        $anylsys['all'] = [
            'id' => -1,
            'keyword_name' => "all",
            'total' => 0,
        ];

        for ($i = 0; $i < count($soures['labels']); $i++) {
            $label = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['id'] = $i;
            $anylsys['all']['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['percentage'] = 0;
            $anylsys['all']['value'][$soures['labels'][$i]]['total'] = 0;
            $anylsys['all']['value'][$label]['image'] = $sourceImages[$label]->image ?? null;
        }

        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }
            $sourceName = $this->matchSourceName($sourceArr, $item->source_id);
            if (!$sourceName) continue; 
            $classificationName = $this->matchClassificationName($classifications, $classification_id);
            // error_log(json_encode($classificationName));

            if (isset($anylsys['all'])) {
                /*$anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;*/
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$sourceName]['total'] += 1;
            }

            if (isset($anylsys[$classificationName])) {
                $anylsys[$classificationName]['value'][$sourceName]['total'] += 1;
                $anylsys[$classificationName]['total'] += 1;
            } else {
                $anylsys[$classificationName] = [
                    "id" => $classification_id,
                    "keyword_name" => $classificationName,
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "total" => 0,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $label = $soures['labels'][$i];
                    $anylsys[$classificationName]['value'][$soures['labels'][$i]]['id'] =  $i;
                    $anylsys[$classificationName]['value'][$soures['labels'][$i]]['channel'] =  $soures['labels'][$i];
                    $anylsys[$classificationName]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$classificationName]['value'][$soures['labels'][$i]]['percentage'] = 0;
                    $anylsys[$classificationName]['value'][$label]['image'] = $sourceImages[$label]->image ?? null;
                    
                }

                $anylsys[$classificationName]['value'][$sourceName]['total'] += 1;
                $anylsys[$classificationName]['total'] += 1;
            }
        }

        $data = [];

        foreach ($anylsys as $key => $item) {
            $total = $item['total'];
            $data[$key] = [
                'id' => $item['id'],
                'keyword_name' => $item['keyword_name'],
                // 'campaign_id' => $item['campaign_id'],
                // 'campaign_name' => $item['campaign_name'],
                'value' => $item['value'],
                'total' => $item['total'],
            ];

            foreach ($item['value'] as $index => $value) {
                $data[$key]['value'][$index]['percentage'] = $total ? ($value['total'] / $total) * 100 : 0;
            }

        }

        if ($data) {
            $data = array_values($data);
        }

        foreach ($data as $key => $item) {
            $data[$key]['value'] = array_values($item['value']);
        }
        return $data;
    }


    private function BullyTableTypeGroup($items, $classifications, $sourceArr)
    {
        $soures = parent::listSource();
        $sourceImages = DB::table('sources') ->select('name', 'image') ->whereIn('name', $soures['labels']) ->get() ->keyBy('name');
        $anylsys = [];

        $anylsys['all'] = [
            'id' => -1,
            'keyword_name' => "all",
            'total' => 0,
        ];

        for ($i = 0; $i < count($soures['labels']); $i++) {
            $label = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['id'] = $i;
            $anylsys['all']['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['percentage'] = 0;
            $anylsys['all']['value'][$soures['labels'][$i]]['total'] = 0;
            $anylsys['all']['value'][$label]['image'] = $sourceImages[$label]->image ?? null;
        }

        foreach ($items as $item) {
            if (isset($item->classification_level_id)){
                $classification_id = $item->classification_level_id;
            }
            if (isset($item->classification_type_id)){
                $classification_id = $item->classification_type_id;
            }
            if (isset($item->classification_sentiment_id)){
                $classification_id = $item->classification_sentiment_id;
            }
            $sourceName = $this->matchSourceName($sourceArr, $item->source_id);
            if (!$sourceName) continue; 
            if (isset($anylsys['all'])) {
                /*$anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;*/
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$sourceName]['total'] += 1;
            }
            $classification_name = $this->matchClassificationName($classifications, $classification_id);

            if (!isset($anylsys[$classification_name])) {
                $anylsys[$classification_name] = [
                    "id" => $classification_id,
                    "keyword_name" => $classification_name,
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "total" => 0,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $label = $soures['labels'][$i];
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['id'] = $i;
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
                    $anylsys[$classification_name]['value'][$label]['image'] = $sourceImages[$label]->image ?? null; 
                }

            }
            $anylsys[$classification_name]['value'][$sourceName]['total'] += 1;
            $anylsys[$classification_name]['total'] += 1;
        }

        $data = [];

        foreach ($anylsys as $key => $item) {
            $total = $item['total'];
            $data[$key] = [
                'id' => $item['id'],
                'keyword_name' => $item['keyword_name'],
                // 'campaign_id' => $item['campaign_id'],
                // 'campaign_name' => $item['campaign_name'],
                'value' => $item['value'],
                'total' => $item['total'],
            ];

            foreach ($item['value'] as $index => $value) {
                $data[$key]['value'][$index]['percentage'] = $total ? ($value['total'] / $total) * 100 : 0;
            }

        }

        if ($data) {
            $data = array_values($data);
        }

        foreach ($data as $key => $item) {
            $data[$key]['value'] = array_values($item['value']);
        }

        return $data;
    }

    private function raw_message_classification($campaign_id, $start_date, $end_date, $classification_type_id)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.message_id as message_id',
                'messages.reference_message_id as reference_message_id',
                'messages.keyword_id as keyword_id',
                'messages.created_at as date_m',
                'messages.author as author',
                'messages.source_id as source_id',
                'messages.full_message as full_message',
                'messages.message_type',
                'messages.device as device',
                'messages.number_of_views as number_of_views',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_reactions as number_of_reactions',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'keywords.name as keyword_name',
                'classifications.classification_type_id',
                'message_results.classification_id',
                'classifications.name as classification_name',
                'classifications.color as classification_color',
                'sources.name as source_name',
                'messages.created_at as created_at'
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classifications.classification_type_id', $classification_type_id);

        if ($this->source_id) {
           $data->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        return $data;
    }

    private function raw_message_classification_name($keywords, $start_date, $end_date, $classification_type)
    {

        // $classification_fixed = [];
        // if($classification_type == 3){
        //     $classification_fixed = [10,11,12,13];
        // }
        // if($classification_type == 2){
        //     $classification_fixed = [4,5,6,7,8,9];
        // }
        // if($classification_type == 1){
        //     $classification_fixed = [1,2,3];
        // }

        $select_result = '';
        if($classification_type == 3){
            $select_result = 'message_results_2.classification_level_id';
        }
        if($classification_type == 2){
            $select_result = 'message_results_2.classification_type_id';
        }
        if($classification_type == 1){
            $select_result = 'message_results_2.classification_sentiment_id';
        }
        //error_log('selectresult :'.$select_result);
        $keywordIds = $keywords->pluck('id')->all();

        if (empty($this->source_id) || $this->source_id == 0 || (is_array($this->source_id) && count($this->source_id) === 0)) {
            return collect([]);
        }
        $activeSourceIds = Sources::where('status', 1)->pluck('id')->toArray();
        if (is_array($this->source_id)) {
            $selected_source_ids = array_intersect($this->source_id, $activeSourceIds);
        } else {
            $selected_source_ids = in_array($this->source_id, $activeSourceIds) ? [$this->source_id] : [];
        }
        if (empty($selected_source_ids)) {
            return collect([]);
        }

        $data = DB::table('messages')
            ->select([
                'messages.message_id as message_id',
                'messages.reference_message_id as reference_message_id',
                'messages.keyword_id as keyword_id',
                'messages.created_at as date_m',
                'messages.author as author',
                'messages.source_id as source_id',
                /*'messages.full_message as full_message',*/
                'messages.message_type',
                'messages.device as device',
                'messages.number_of_views as number_of_views',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_reactions as number_of_reactions',
                'message_results_2.message_id as result_message_id',
                'message_results_2.media_type',
                // 'message_results_2.classification_sentiment_id',
                // 'message_results_2.classification_type_id',
                // 'message_results_2.classification_level_id',
                $select_result,
                // 'message_results.classification_id',
                // 'message_results.classification_type_id'
            ])
            ->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
            ->whereIn('keyword_id', $keywordIds)
            ->where('message_results_2.media_type', 1)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            //->where('message_results.classification_type_id', $classification_type)
            ->whereIn('messages.source_id', $selected_source_ids)
            ->orderBy($select_result, 'asc');

            // if (!empty($classification)) {
            //     $data->whereIn('message_results.classification_id', $classification_fixed);
            // }

        // if ($this->source_id) {
        //     $data->where('source_id', $this->source_id);
        // }

        // if (!$this->user_login->is_admin) {
        //     $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
        //     $data->whereIn('source_id', $source_ids);
        // }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->where('status', 1)->pluck('id')->toArray();
            $data->whereIn('messages.source_id', $source_ids);
        }

        return $data;
    }
}
