<?php

namespace App\Http\Controllers\report;

use App\Models\Classification;
use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Sources;
use App\Models\Keyword; 

class SentimentDashboardController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;
    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;

    private $keyword_id;
    private $source_id;


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
        $this->source_id = $request->source === "all" ? $this->getAllSource()->pluck('id')->toArray() : $request->source;
        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }

        if ($request->source !== 'all') {
            $this->source_id = $request->source;
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

    public function SentimentBy(Request $request)
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $data = null;
        $classifications = self::getClassificationMaster();
        $sources = $this->getAllSource();

        $resultCurrent = $this->raw_message_classification($keywords)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])->get();

        $resultPrevious = $this->raw_message_classification($keywords)
            ->whereBetween('messages.created_at', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"])->get();
        //error_log(json_encode($resultCurrent));
        return parent::handleRespond([
            "SentimentByDay" => $this->SentimentByDay($resultCurrent, $classifications, true),
            "SentimentByTime" => $this->SentimentByTime($resultCurrent, $classifications, true),
            "SentimentByDevice" => $this->SentimentByDevice($resultCurrent, $classifications, $sources, true),
            "SentimentByAccount" => $this->SentimentByAccount($resultCurrent, $classifications, true),
            "SentimentByChannel" => $this->SentimentByChannel($resultCurrent, $classifications, $sources, true),
            "SentimentBullyType" => $this->SentimentBullyType($resultCurrent, $classifications, $keywords),
            "SentimentBullyLevel" => $this->SentimentBullyLevel($resultCurrent, $classifications, $keywords),
            "SentimentScore" => $this->SentimentScore($resultCurrent, $resultPrevious, $keywords, true),
            "SentimentComparison" => $this->SentimentComparisonData($resultCurrent, $resultPrevious, $keywords),
        ]);
    }

    public function DailySentiment(Request $request)
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $data = null;
        $classifications = self::getClassificationMaster();
        //error_log(json_encode($classifications));
        $resultCurrent = $this->raw_message_classification($keywords, 1,2,3, null)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])->get();
        $resultPrevious = $this->raw_message_classification($keywords, 1,2,3, null)
            ->whereBetween('messages.created_at', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"])->get();

        //error_log(count($resultCurrent));
        //error_log(count($resultPrevious));
        $data['sentiment'] = $this->sentiment($resultCurrent, $classifications);
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($resultCurrent, $classifications, $this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($resultPrevious, $classifications, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }


    private function percentageOfMessages($raw_current, $classifications, $start_date, $end_date)
    {
        $data = null;
        if ($raw_current->count() <= 0) return null;
        //error_log('countL: '.$raw_current->count());
        $message_keyword = [];
        $message_total = 0;


        foreach ($raw_current as $item) {
            //$item = (array)$object;

            if (!isset($data[$item->classification_sentiment_id])) {

                $data[$item->classification_sentiment_id] = [
                    'keyword_id' => $item->classification_sentiment_id,
                    'keyword_name' => self::matchClassificationName($classifications, $item->classification_sentiment_id)/*,
                    'campaign_id' => $item['campaign_id'],
                    'campaign_name' => $item['campaign_name'],*/
                ];
            }

            if (isset($message_keyword[$item->classification_sentiment_id])) {

                $message_keyword[$item->classification_sentiment_id] += 1;
            } else {

                $message_keyword[$item->classification_sentiment_id] = 1;
            }

            $message_total += 1;
        }


        foreach ($message_keyword as $classification_sentiment_id => $value) {
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = self::point_two_digits(($value / $message_total) * 100);
            }

            $data[$classification_sentiment_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
                'total' => self::point_two_digits($message_total, 0)
            ];
        }


        if ($data) {
            $data = array_values($data);
        }


        return $data;

    }

    private function sentiment($raw, $classifications)
    {
        $labels = [
            "Positive",
            "Neutral",
            "Negative"
        ];

        $data = null;
        if (count($raw) <= 0) return null;

        foreach ($raw as $item) {
            $date_m = Carbon::parse($item->date_m)->format('Y-m-d');
            $classificationName = self::matchClassificationName($classifications, $item->classification_sentiment_id);
            $index_label = array_search($classificationName, $labels);

            if (isset($data[$index_label])) {
                if (isset($data[$index_label]['value'][$date_m])) {
                    $data[$index_label]['value'][$date_m]['total_at_date'] += 1;
                } else {
                    $data[$index_label]['value'][$date_m] = [
                        'date' => $date_m,
                        'source_id' => $item->source_id,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$index_label] = [
                    'keyword_id' => $item->classification_sentiment_id,
                    'keyword_name' => $classificationName,
                    'value' => []
                ];

                $data[$index_label]['value'][$date_m] = [
                    'date' => $date_m,
                    'source_id' => $item->source_id,
                    'total_at_date' => 1
                ];
            }
        }

        if ($data) {
            $data = array_values($data);

            foreach ($data as $key => $item) {
                $data[$key]['value'] = array_values($item['value']);
            }
        }
        return $data;
    }

    public function SentimentByDay($items, $classifications, $only_data = false)
    {

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

            if (isset($data['value'][$item->classification_sentiment_id])) {
                $data['value'][$item->classification_sentiment_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->classification_sentiment_id] = [
                    'id' => $item->classification_sentiment_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_sentiment_id),
                    /*'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];
                $data['value'][$item->classification_sentiment_id]['data'][$index_label] = 1;

            }

        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SentimentByTime($items, $classifications, $only_data = false)
    {

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


            if (!isset($data['value'][$item->classification_sentiment_id])) {
                $data['value'][$item->classification_sentiment_id] = [
                    'id' => $item->classification_sentiment_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_sentiment_id),
                    /*'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0]
                ];

            }
            $data['value'][$item->classification_sentiment_id]['data'][$index_label] += 1;

        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SentimentByDevice($items, $classifications, $sources, $only_data = false)
    {

        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];


        $check = [];

        foreach ($items as $item) {

            $index_label = null;

            if (!in_array($item->device, $check)) {
                $check[] = $item->device;
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

                if (isset($data['value'][$item->classification_sentiment_id])) {
                    $data['value'][$item->classification_sentiment_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->classification_sentiment_id] = [
                        'id' => $item->classification_sentiment_id,
                        'keyword_name' => $this->matchClassificationName($classifications, $item->classification_sentiment_id),
                        /*'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,*/
                        'source_id' => $item->source_id,
                        'source_name' => $this->matchSourceName($sources, $item->source_id),
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->classification_sentiment_id]['data'][$index_label] += 1;
                }
            }
        }


        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);

    }

    public function SentimentByAccount($influencers, $classifications, $only_data = false)
    {
        $data['labels'] = ["Influencer", "Follower"];
        $data['value'] = [];

        foreach ($influencers as $influencer) {
            $classification_sentiment_id = $influencer->classification_sentiment_id;
            $keyword_name = $this->matchClassificationName($classifications, $classification_sentiment_id);
            $index = ($influencer->reference_message_id == "") ? 0 : 1;

            if (!isset($data['value'][$classification_sentiment_id])) {
                $data['value'][$classification_sentiment_id] = [
                    'id' => $classification_sentiment_id,
                    'keyword_name' => $keyword_name,
                    'data' => [0, 0]
                ];
            }

            $data['value'][$classification_sentiment_id]['data'][$index]++;
        }

        $data['value'] = array_values($data['value']);

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }


    public function SentimentByChannel($items, $classifications, $sources, $only_data = false)
    {
        $data = parent::listSource();

        foreach ($items as $item) {
            $sourceName = $this->matchSourceName($sources, $item->source_id);
            $index_label = array_search($sourceName, $data['labels']);
            if (!isset($data['value'][$item->classification_sentiment_id])) {
                $data['value'][$item->classification_sentiment_id] = [
                    'id' => $item->classification_sentiment_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_sentiment_id),
                ];

                for ($i = 0; $i <= count($data['labels']); $i++) {
                    $data['value'][$item->classification_sentiment_id]['data'][] = 0;
                }

            }
            $data['value'][$item->classification_sentiment_id]['data'][$index_label] += 1;
        }

        if (isset($data['value'])) {

            foreach ($data['value'] as $key => $value) {
                $data['value'][$key]['data'] = array_values($value['data']);
            }

            $data['value'] = array_values($data['value']);
        }


        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SentimentBullyLevel($items, $classification, $keywords)
    {
        $data['labels'] = [
            "Level 0",
            "Level 1",
            "Level 2",
            "Level 3",
        ];

        $data['value'] = [
            ['keyword_name' => 'Negative', 'data' => [0, 0, 0, 0]],
            ['keyword_name' => 'Neutral',  'data' => [0, 0, 0, 0]],
            ['keyword_name' => 'Positive', 'data' => [0, 0, 0, 0]],
        ];

        $group = $this->raw_message_classification($keywords)
                ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
                ->get();

        foreach ($group as $item) {
        // $count = count($group);
        // error_log("จำนวนข้อมูล: " . $count);            
            $sentiment = self::matchClassificationName($classification, $item->classification_sentiment_id);
            $level = self::matchClassificationName($classification, $item->classification_level_id);

            $indexSentiment = match ($sentiment) {
                'Negative' => 0,
                'Neutral' => 1,
                'Positive' => 2,
                default => null,
            };

            $indexLevel = array_search($level, $data['labels']);

            if ($indexSentiment !== null && $indexLevel !== false) {
                $data['value'][$indexSentiment]['data'][$indexLevel] += 1;
            }
        }

        //error_log(json_encode($data));
        // $count = count($data);
        // error_log("จำนวนข้อมูล: " . $count);      
        return $data;
    }



    public function SentimentBullyType($items, $classification, $keywords)
    {
        $sentiments = Classification::where('classification_type_id', 2)->pluck('name')->toArray();
        $data['labels'] = $sentiments;

        $data['value'] = [
            ['id' => 1, 'keyword_name' => 'Negative', 'data' => array_fill(0, count($sentiments), 0)],
            ['id' => 2, 'keyword_name' => 'Neutral',  'data' => array_fill(0, count($sentiments), 0)],
            ['id' => 3, 'keyword_name' => 'Positive', 'data' => array_fill(0, count($sentiments), 0)],
        ];

        $group = $this->raw_message_classification($keywords)
                ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
                ->get();

        foreach ($group as $item) {
            $sentimentName = self::matchClassificationName($classification, $item->classification_sentiment_id);
            $bullyTypeName = self::matchClassificationName($classification, $item->classification_type_id); 

            $sentimentIndex = match ($sentimentName) {
                'Negative' => 0,
                'Neutral' => 1,
                'Positive' => 2,
                default => null,
            };

            $bullyTypeIndex = array_search($bullyTypeName, $data['labels']);

            if ($sentimentIndex !== null && $bullyTypeIndex !== false) {
                $data['value'][$sentimentIndex]['data'][$bullyTypeIndex] += 1;
            }
        }

        //error_log(json_encode($data));
        return $data;
    }

    public function periodAndComparison(Request $request)
    {

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $sources = $this->getAllSource();

        $resultCurrent = $this->raw_message_classification($keywords, 1, null)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])->get();
        $resultPrevious = $this->raw_message_classification($keywords, 1, null)
            ->whereBetween('messages.created_at', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"])->get();


        return parent::handleRespond([
            "PeriodOverPeriod" => $this->PeriodOverPeriod($resultCurrent, $resultPrevious, true),
            "ComparisonByChannel" => $this->ComparisonByChannel($resultCurrent, $resultPrevious, $sources, true),
            "ComparisonByEngagementType" => $this->ComparisonByEngagementType($resultCurrent, $resultPrevious, true),
        ]);
    }

    public function PeriodOverPeriod($raw_current, $raw_previous, $only_data = false)
    {

        $data = null;

        // $raw_current = $raw_current->whereIn('classifications.classification_sentiment_id', [1]);
        // $raw_previous = $raw_previous->whereIn('classifications.classification_sentiment_id', [1]);

        $total_share_current = 0;
        $total_comment_current = 0;
        $total_reactions_current = 0;

        foreach ($raw_current as $item) {
            if ($item->classification_sentiment_id == 1) {
                $total_share_current += 1;
            }

            if ($item->classification_sentiment_id == 3) {
                $total_comment_current += 1;
            }

            if ($item->classification_sentiment_id == 2) {
                $total_reactions_current += 1;
            }
        }

        $total_share_previous = 0;
        $total_comment_previous = 0;
        $total_reactions_previous = 0;

        foreach ($raw_previous as $item) {
            if ($item->classification_sentiment_id == 1) {
                $total_share_previous += 1;
            }

            if ($item->classification_sentiment_id == 3) {
                $total_comment_previous += 1;
            }

            if ($item->classification_sentiment_id == 2) {
                $total_reactions_previous += 1;
            }
        }

        $totalEngagement_current = $total_share_current + $total_comment_current + $total_reactions_current;
        $totalEngagement_previous = $total_share_previous + $total_comment_previous + $total_reactions_previous;

        $data['totalSentiment'] = [
            "totalValue" => $this->custom_number_format((int)$totalEngagement_current),
            "comparison" => parent::point_two_digits($totalEngagement_current - $totalEngagement_previous !== 0 ? $this->overPeriodComparison($totalEngagement_current, $totalEngagement_previous) : 0),
            "type" => $totalEngagement_current - $totalEngagement_previous > 0 ? "plus" : "minus",
        ];

        $data['neutral'] = [
            "totalValue" => $this->custom_number_format((int)$total_comment_current),
            // "comparison" => parent::point_two_digits($total_comment_current - $total_comment_previous !== 0 ? (($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0),
            "comparison" => $total_comment_previous != 0
            ? parent::point_two_digits((($total_comment_current - $total_comment_previous) / $total_comment_previous) * 100)
            : 0,
            "type" => $total_comment_current - $total_comment_previous > 0 ? "plus" : "minus",
        ];

        $data['positive'] = [
            "totalValue" => $this->custom_number_format((int)$total_share_current),
            // "comparison" => parent::point_two_digits($total_share_current - $total_share_previous !== 0 ? (($total_share_current - $total_share_previous) / $total_share_previous * 100) : 0),
            "comparison" => $total_share_previous != 0
            ? parent::point_two_digits((($total_share_current - $total_share_previous) / $total_share_previous) * 100)
            : 0,
            "type" => $total_share_current - $total_share_previous > 0 ? "plus" : "minus",
        ];

        $data['negative'] = [
            "totalValue" => $this->custom_number_format((int)$total_reactions_current),
            "comparison" => parent::point_two_digits($this->overPeriodComparison($total_reactions_current, $total_reactions_previous)),
            "type" => $total_reactions_current - $total_reactions_previous > 0 ? "plus" : "minus",
        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function ComparisonByChannel($resultCurrent, $resultPrevious, $sources, $only_data = false)
    {

        $data = parent::listSource();

        $analysis_current = $this->factoryComparisonByChangel($resultCurrent, $sources);
        $analysis_previous = $this->factoryComparisonByChangel($resultPrevious, $sources);


        $data['value'][0] = [
            "id" => 1,
            "keyword_name" => "Current",
        ];

        $data['value'][1] = [
            "id" => 2,
            "keyword_name" => "Previous",
        ];

        for ($i = 0; $i < count($data['labels']); $i++) { // <=
            $data['value'][0]['data'][$i] = 0;
            $data['value'][1]['data'][$i] = 0;
            $data['positive'][$i] = 0;
            $data['neutral'][$i] = 0;
            $data['negative'][$i] = 0;


        }

        foreach ($analysis_current as $index => $value) {
            $analysis_previous_position = 0;
            $analysis_previous_neutral = 0;
            $analysis_previous_negative = 0;

            if (isset($analysis_previous[$index])) {
                $analysis_previous_position = $analysis_previous[$index]['positive'];
                $analysis_previous_neutral = $analysis_previous[$index]['neutral'];
                $analysis_previous_negative = $analysis_previous[$index]['negative'];
            }

            $index_label = array_search($value['source_name'], $data['labels']);
            $data['value'][0]['data'][$index_label] = $value['total'];

            $data['positive'][$index_label] = self::overPeriodComparison($value['positive'], $analysis_previous_position);
            $data['neutral'][$index_label] = self::overPeriodComparison($value['neutral'], $analysis_previous_neutral);
            $data['negative'][$index_label] = self::overPeriodComparison($value['negative'], $analysis_previous_negative);
        }


        foreach ($analysis_previous as $value) {
            $index_label = array_search($value['source_name'], $data['labels']);
            $data['value'][1]['data'][$index_label] = $value['total'];
        }


        if ($data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }
        return parent::handleRespond($data);
    }

    private function factoryComparisonByChangel($items, $sources)
    {
        $analysis = [];
        foreach ($items as $item) {


            if (!isset($analysis[$item->source_id])) {
                $analysis[$item->source_id] = [
                    "source_name" => $this->matchSourceName($sources, $item->source_id),
                    "total" => 0,
                    "positive" => 0,
                    "neutral" => 0,
                    "negative" => 0,
                ];

            }
            $analysis[$item->source_id]['total'] += 1;
            if ($item->classification_sentiment_id == 1) {
                $analysis[$item->source_id]['positive'] += 1;
            } else if ($item->classification_sentiment_id == 3) {
                $analysis[$item->source_id]['neutral'] += 1;
            } else if ($item->classification_sentiment_id == 2) {
                $analysis[$item->source_id]['negative'] += 1;
            }
        }

        return $analysis;

    }


    public function ComparisonByEngagementType($resultCurrent, $resultPrevious, $only_data = false)
    {
        $analysis_current = $this->factoryComparisnByEngagementType($resultCurrent);
        $analysis_previous = $this->factoryComparisnByEngagementType($resultPrevious);

        $data['labels'] = [
            "Share",
            "Comment",
            "Reaction",
            "Views"
        ];

        $data['value'][0] = [
            "id" => 1,
            "keyword_name" => "Current",
            "data" => [
                0,
                0,
                0,
            ]
        ];

        $data['value'][1] = [
            "id" => 2,
            "keyword_name" => "Previous",
            "data" => [
                0,
                0,
                0,
            ]
        ];


        $data['value'][0]['data'][0] = $analysis_current['share'];
        $data['value'][0]['data'][1] = $analysis_current['comment'];
        $data['value'][0]['data'][2] = $analysis_current['reaction'];
        $data['value'][0]['data'][3] = $analysis_current['views'];

        $data['value'][1]['data'][0] = $analysis_previous['share'];
        $data['value'][1]['data'][1] = $analysis_previous['comment'];
        $data['value'][1]['data'][2] = $analysis_previous['reaction'];
        $data['value'][1]['data'][3] = $analysis_previous['views'];

        $data['positive'][0] = self:: overPeriodComparison($analysis_current['share_data']['positive'], $analysis_previous['share_data']['positive']);
        $data['positive'][1] = self:: overPeriodComparison($analysis_current['comment_data']['positive'], $analysis_previous['comment_data']['positive']);
        $data['positive'][2] = self:: overPeriodComparison($analysis_current['reaction_data']['positive'], $analysis_previous['reaction_data']['positive']);
        $data['positive'][3] = self:: overPeriodComparison($analysis_current['views_data']['positive'], $analysis_previous['views_data']['positive']);
//
        $data['neutral'][0] = self:: overPeriodComparison($analysis_current['share_data']['neutral'], $analysis_previous['share_data']['neutral']);
        $data['neutral'][1] = self:: overPeriodComparison($analysis_current['comment_data']['neutral'], $analysis_previous['comment_data']['neutral']);
        $data['neutral'][2] = self:: overPeriodComparison($analysis_current['reaction_data']['neutral'], $analysis_previous['reaction_data']['neutral']);
        $data['neutral'][3] = self:: overPeriodComparison($analysis_current['views_data']['neutral'], $analysis_previous['views_data']['neutral']);

        $data['negative'][0] = self:: overPeriodComparison($analysis_current['share_data']['negative'], $analysis_previous['share_data']['negative']);
        $data['negative'][1] = self:: overPeriodComparison($analysis_current['comment_data']['negative'], $analysis_previous['comment_data']['negative']);
        $data['negative'][2] = self:: overPeriodComparison($analysis_current['reaction_data']['negative'], $analysis_previous['reaction_data']['negative']);
        $data['negative'][3] = self:: overPeriodComparison($analysis_current['views_data']['negative'], $analysis_previous['views_data']['negative']);

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    private function factoryComparisnByEngagementType($items)
    {


        $analysis = [
            "share" => 0,
            "comment" => 0,
            "reaction" => 0,
            "views" => 0,
            "total" => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'share_data' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ],
            'comment_data' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ],
            'reaction_data' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ],
            'views_data' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ],
        ];


        foreach ($items as $item) {


            if ($item->classification_sentiment_id === 1) {
                $analysis['positive'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
            } else if ($item->classification_sentiment_id === 2) {
                $analysis['negative'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
            } else if ($item->classification_sentiment_id === 3) {
                $analysis['neutral'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
            }

            if ($item->number_of_shares) {

                if ($item->classification_sentiment_id === 1) {
                    $analysis['share_data']['positive'] += $item->number_of_shares;
                } else if ($item->classification_sentiment_id === 2) {
                    $analysis['share_data']['negative'] += $item->number_of_shares;
                } else if ($item->classification_sentiment_id === 3) {
                    $analysis['share_data']['neutral'] += $item->number_of_shares;
                }
            }


            if ($item->number_of_comments) {
                if ($item->classification_sentiment_id === 1) {
                    $analysis['comment_data']['positive'] += $item->number_of_comments;
                } else if ($item->classification_sentiment_id === 2) {
                    $analysis['comment_data']['negative'] += $item->number_of_comments;
                } else if ($item->classification_sentiment_id === 3) {
                    $analysis['comment_data']['neutral'] += $item->number_of_comments;
                }
            }


            if ($item->number_of_reactions) {
                if ($item->classification_sentiment_id === 1) {
                    $analysis['reaction_data']['positive'] += $item->number_of_reactions;
                } else if ($item->classification_sentiment_id === 2) {
                    $analysis['reaction_data']['negative'] += $item->number_of_reactions;
                } else if ($item->classification_sentiment_id === 3) {
                    $analysis['reaction_data']['neutral'] += $item->number_of_reactions;
                }
            }

            if ($item->number_of_views) {
                if ($item->classification_sentiment_id === 1) {
                    $analysis['views_data']['positive'] += $item->number_of_views;
                } else if ($item->classification_sentiment_id === 2) {
                    $analysis['views_data']['negative'] += $item->number_of_views;
                } else if ($item->classification_sentiment_id === 3) {
                    $analysis['views_data']['neutral'] += $item->number_of_views;
                }
            }


            $analysis['share'] += $item->number_of_shares;
            $analysis['comment'] += $item->number_of_comments;
            $analysis['reaction'] += $item->number_of_reactions;
            $analysis['views'] += $item->number_of_views;
            $analysis['total'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
        }

        return $analysis;
    }


    private function factorySentimentScore($items, $keywords)
    {
        // ->whereIn('classifications.name', ['Positive', 'Negative', 'Neutral']);

        $analysis = [];
        $message_total = 0;
        $max = ['value' => 0, 'hightlightColor' => ''];
        $check = [];
        foreach ($items as $item) {
            $message_total += 1;

            if (!in_array($item->keyword_id, $check)) {
                $check[] = $item->keyword_id;
            }

            if (isset($analysis[$item->keyword_id])) {
                $analysis[$item->keyword_id]['total'] += 1;
            } else {
                $analysis[$item->keyword_id] = [
                    'total' => 1,
                    'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ];
            }
            if ($item->classification_sentiment_id === 1) {
                $analysis[$item->keyword_id]['positive'] += 1;
            } else if ($item->classification_sentiment_id === 2) {
                $analysis[$item->keyword_id]['negative'] += 1;
            } else if ($item->classification_sentiment_id === 3) {
                $analysis[$item->keyword_id]['neutral'] += 1;
            }

        }


        $results = [];

        foreach ($analysis as $keyword_id => $item) {

            $max = max($item['positive'], $item['neutral'], $item['negative']);
            $index_max = array_search($max, $item);
            $results[$keyword_id] = [
                'total' => $item['total'],
                'keyword_name' => $item['keyword_name'],
                'positive' => $item['positive'],
                'neutral' => $item['neutral'],
                'negative' => $item['negative'],
                'hightlightColor' => $index_max,
                'message_total' => $message_total,
            ];

        }

        return $results;
    }

    public function SentimentScore($resultCurrent, $resultPrevious, $keywords, $only_data = false)
    {
        $data = ['senitment_score_data' => [], 'senitment_score_percentage' => []];

        $analysis_current = $this->factorySentimentScore($resultCurrent, $keywords);
        $analysis_previous = $this->factorySentimentScore($resultPrevious, $keywords);


        foreach ($analysis_current as $keyword_id => $item) {

            $positive = $item['positive'];
            $neutral = $item['neutral'];
            $negative = $item['negative'];
            $total = $item['total'];

            $p_positive = 0;
            $p_negative = 0;
            $p_neutral = 0;
            $total_positive_negative_neutral = 0;

            if (isset($analysis_previous[$keyword_id])) {
                $p_positive = $analysis_previous[$keyword_id]['positive'];
                $p_negative = $analysis_previous[$keyword_id]['negative'];
                $p_neutral = $analysis_previous[$keyword_id]['neutral'];
                $total_positive_negative_neutral = $p_positive + $p_negative + $p_neutral;
            }


            $check_sentiment_score = (((1 * $positive) + (-1 * $negative)) / ($positive + $negative + $neutral)) * 5;

            $sentimentScore = 0;
            $sentimentScore_previous = 0;
            $check_sentiment_score_previous = 0;


//            if ($check_sentiment_score > 5) {
//                $sentimentScore = 5;
//            } else if ($check_sentiment_score < -5) {
//                $sentimentScore = -5;
//            } else {
//                $sentimentScore = round($check_sentiment_score, 2);
//            }
            $sentimentScore = round($check_sentiment_score, 2);

            if ($sentimentScore == -0) {
                $sentimentScore = 0;
            }


            if ($total_positive_negative_neutral) {
                $check_sentiment_score_previous = (((1 * $p_positive) + (-1 * $p_negative)) / ($p_positive + $p_negative + $p_neutral)) * 5;

//                if ($check_sentiment_score_previous > 5) {
//                    $sentimentScore_previous = 5;
//                } else if ($check_sentiment_score_previous < -5) {
//                    $sentimentScore_previous = -5;
//                } else {
//
//                }

                $sentimentScore_previous = round($check_sentiment_score_previous, 2);

                if ($sentimentScore_previous == -0) {
                    $sentimentScore_previous = 0;
                }
            }

            $data['senitment_score_data'][$keyword_id] = [
                'keyword_name' => $item['keyword_name'],
                "sentimentScore" => $sentimentScore,
                "previous_period" => $sentimentScore_previous,
                "positive" => $positive,
                "neutral" => $neutral,
                "negative" => $negative,
                "p_positive" => $p_positive,
                "p_neutral" => $p_neutral,
                "p_negative" => $p_negative,
                "hightlightColor" => $item['hightlightColor']
            ];

            $data['senitment_score_percentage'][$keyword_id] = [
                'keyword_id' => $keyword_id,
                'keyword_name' => $item['keyword_name'],
                "positive" => self::point_two_digits(($positive / $total) * 100),
                "neutral" => self::point_two_digits(($neutral / $total) * 100),
                "negative" => self::point_two_digits(($negative / $total) * 100),

            ];
        }

        if ($data['senitment_score_data']) {
            $data['senitment_score_data'] = array_values($data['senitment_score_data']);
        }

        if ($data['senitment_score_percentage']) {
            $data['senitment_score_percentage'] = array_values($data['senitment_score_percentage']);
        }

//
        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    private function factorySentimentComparison($result, $keywords)
    {


        $analysis = [];
        foreach ($result as $item) {
            if (isset($analysis[$item->keyword_id])) {
                $analysis[$item->keyword_id]['total'] += 1;
            } else {
                $analysis[$item->keyword_id] = [
                    "keyword_id" => $item->keyword_id,
                    "keyword_name" => self::matchKeywordName($keywords, $item->keyword_id),
                    "total" => 1,
                    "positive" => 0,
                    "negative" => 0,
                    "neutral" => 0,
                ];
            }

            if ($item->classification_sentiment_id == 1) {
                $analysis[$item->keyword_id]['positive'] += 1;
            }

            if ($item->classification_sentiment_id == 2) {
                $analysis[$item->keyword_id]['negative'] += 1;
            }

            if ($item->classification_sentiment_id == 3) {
                $analysis[$item->keyword_id]['neutral'] += 1;
            }
        }

        return $analysis;
    }

    public function SentimentComparison()
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);

        $resultCurrent = $this->raw_message_classification($keywords, 1, null)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])->get();
        $resultPrevious = $this->raw_message_classification($keywords, 1, null)
            ->whereBetween('messages.created_at', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"])->get();
        $data = self::SentimentComparisonData($resultCurrent, $resultPrevious, $keywords);

        return parent::handleRespond($data);
    }

    public function SentimentComparisonData($resultCurrent, $resultPrevious, $keywords)
    {

        $analysis_current = $this->factorySentimentComparison($resultCurrent, $keywords);
        $analysis_previous = $this->factorySentimentComparison($resultPrevious, $keywords);

        $data = [];

        foreach ($analysis_current as $key => $item) {

            $analysis_previous_key = 0;
            $analysis_previous_positive = 0;
            $analysis_previous_neutral = 0;
            $analysis_previous_negative = 0;

            if (isset($analysis_previous[$key])) {
                $analysis_previous_key = $analysis_previous[$key]['total'];
                $analysis_previous_positive = $analysis_previous[$key]['positive'];
                $analysis_previous_neutral = $analysis_previous[$key]['neutral'];
                $analysis_previous_negative = $analysis_previous[$key]['negative'];
            }

            $data[$key] = [
                "keyword_id" => $item['keyword_id'],
                "keyword_name" => $item['keyword_name'],
                "total" => $item['total'],
                "comparison" => [
                    "value" => $item['total'] - $analysis_previous_key,
                    "percentage" => $analysis_previous_key ? self::point_two_digits((($item['total'] - $analysis_previous_key) / $analysis_previous_key) * 100) : 0,
                    "type" => $item['total'] - $analysis_previous_key > 0 ? "plus" : "minus"
                ],

                "positive" => [
                    "value" => $item['positive'] - $analysis_previous_positive,
                    "percentage" => $analysis_previous_positive ? self::point_two_digits((($item['positive'] - $analysis_previous_positive) / $analysis_previous_positive) * 100) : 0,
                    "type" => $item['positive'] - $analysis_previous_positive > 0 ? "plus" : "minus"
                ],

                "negative" => [
                    "value" => $item['negative'] - $analysis_previous_negative,
                    "percentage" => $analysis_previous_negative ? self::point_two_digits((($item['negative'] - $analysis_previous_negative) / $analysis_previous_negative) * 100) : 0,
                    "type" => $item['negative'] - $analysis_previous_negative > 0 ? "plus" : "minus"
                ],

                "neutral" => [
                    "value" => $item['neutral'] - $analysis_previous_neutral,
                    "percentage" => $analysis_previous_neutral ? self::point_two_digits((($item['neutral'] - $analysis_previous_neutral) / $analysis_previous_neutral) * 100) : 0,
                    "type" => $item['neutral'] - $analysis_previous_neutral > 0 ? "plus" : "minus"
                ],

            ];
        }

        if ($data) {
            $data = array_values($data);

            usort($data, function ($a, $b) {
                return $b['total'] - $a['total'];
            });
        }

        return $data;

    }

    public function SummaryBy(Request $request)
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $sources = $this->getAllSource();
        $resultCurrent = $this->raw_message_classification($keywords, 1, null)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])->get();

        return parent::handleRespond([
            "SummaryScoreAccount" => $this->SummaryScoreAccount($resultCurrent, true),
            "SummaryScoreChannel" => $this->SummaryScoreChannel($resultCurrent, $sources, true),
            "SummaryKeyword" => $this->SummaryKeyword($resultCurrent, $keywords, true),
        ]);
    }

    public function SummaryScoreAccount($items, $only_data = false)
    {

        $analysis = [];
        $message_total = 0;
        $max = ['value' => 0, 'hightlightColor' => ''];
        foreach ($items as $item) {
            $message_total += 1;
            if (isset($analysis[$item->author])) {
                $analysis[$item->author]['total'] += 1;
            } else {
                $analysis[$item->author] = [
                    'total' => 1,
                    'author' => $item->author,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ];
            }
            if ($item->classification_sentiment_id === 1) {
                $analysis[$item->author]['positive'] += 1;
            } else if ($item->classification_sentiment_id === 2) {
                $analysis[$item->author]['negative'] += 1;
            } else if ($item->classification_sentiment_id === 3) {
                $analysis[$item->author]['neutral'] += 1;
            }

        }
        $data = [];
        // foreach ($analysis as $item) {
        //     $data[] = [
        //         "infulencer" => $item['author'],
        //         "total" => $item['total'],
        //         "positive" => $item['positive'],
        //         "neutral" => $item['neutral'],
        //         "negative" => $item['negative'],
        //         "sentiment_score" => round((((1 * $item['positive']) + (-1 * $item['negative'])) / ($item['positive'] + $item['negative'] + $item['neutral'])) * 5),

        //     ];
        // }

        foreach ($analysis as $item) {
            $total = $item['total'];
            $positive_percent = $total > 0 ? round(($item['positive'] / $total) * 100, 2) : 0;
            $neutral_percent  = $total > 0 ? round(($item['neutral']  / $total) * 100, 2) : 0;
            $negative_percent = $total > 0 ? round(($item['negative'] / $total) * 100, 2) : 0;

            $data[] = [
                "infulencer" => $item['author'],
                "total"      => $total,
                "positive"   => $positive_percent,
                "neutral"    => $neutral_percent,
                "negative"   => $negative_percent,
                "sentiment_score" => $total > 0 
                    ? round((((1 * $item['positive']) + (-1 * $item['negative'])) / $total) * 5, 2)
                    : 0,
            ];
        }

        if ($data) {
            usort($data, function ($a, $b) {
                return $b['total'] - $a['total'];
            });
        }

//        $data[] = [
//            "infulencer" => "User 1",
//            "sentiment_score" => 3.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SummaryScoreChannel($items, $sources, $only_data = false)
    {
        $sources = Sources::where('status', 1)->get();

        $message_total = 0;
        $total = [];
        $analysis = [];
        $total['positive'] = 0;
        $total['negative'] = 0;
        $total['neutral'] = 0;
        foreach ($items as $item) {
            $message_total += 1;
            if (isset($analysis[$item->source_id])) {
                $analysis[$item->source_id]['total'] += 1;
            } else {
                $analysis[$item->source_id] = [
                    'total' => 1,
                    'source_id' => $item->source_id,
                    'source_name' => self::matchSourceName($sources, $item->source_id),
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ];
            }
            if ($item->classification_sentiment_id === 1) {
                $analysis[$item->source_id]['positive'] += 1;
                $total['positive'] += 1;
            } else if ($item->classification_sentiment_id === 2) {
                $analysis[$item->source_id]['negative'] += 1;
                $total['negative'] += 1;
            } else if ($item->classification_sentiment_id === 3) {
                $analysis[$item->source_id]['neutral'] += 1;
                $total['neutral'] += 1;
            }

        }

        $data = [];

        foreach ($sources as $source) {
            if (isset($analysis[$source->id])) {
                $data[] = [
                    "channel" => $source->name,
                    "total" => $analysis[$source->id]['total'],
                    // "positive" => $analysis[$source->id]['total'] ? self::point_two_digits(($analysis[$source->id]['positive'] / $total['positive']) * 100) : 0,
                    // "neutral" => $analysis[$source->id]['total'] ? self::point_two_digits(($analysis[$source->id]['neutral'] / $total['neutral']) * 100) : 0,
                    // "negative" => $analysis[$source->id]['total'] ? self::point_two_digits(($analysis[$source->id]['negative'] / $total['negative']) * 100) : 0,
                    // "positive" => ($analysis[$source->id]['total'] && $total['positive']) 
                    //     ? self::point_two_digits(($analysis[$source->id]['positive'] / $total['positive']) * 100) 
                    //     : 0,

                    // "neutral" => ($analysis[$source->id]['total'] && $total['neutral']) 
                    //     ? self::point_two_digits(($analysis[$source->id]['neutral'] / $total['neutral']) * 100) 
                    //     : 0,

                    // "negative" => ($analysis[$source->id]['total'] && $total['negative']) 
                    //     ? self::point_two_digits(($analysis[$source->id]['negative'] / $total['negative']) * 100) 
                    //     : 0,
                    "positive" => ($analysis[$source->id]['total']) 
                        ? self::point_two_digits(($analysis[$source->id]['positive'] / $analysis[$source->id]['total']) * 100) 
                        : 0,

                    "neutral" => ($analysis[$source->id]['total']) 
                        ? self::point_two_digits(($analysis[$source->id]['neutral'] / $analysis[$source->id]['total']) * 100) 
                        : 0,

                    "negative" => ($analysis[$source->id]['total']) 
                        ? self::point_two_digits(($analysis[$source->id]['negative'] / $analysis[$source->id]['total']) * 100) 
                        : 0,

                    "sentiment_score" => round((((1 * $analysis[$source->id]['positive']) + (-1 * $analysis[$source->id]['negative'])) / ($analysis[$source->id]['positive'] + $analysis[$source->id]['negative'] + $analysis[$source->id]['neutral'])) * 5),
                ];
            } else {
                $data[] = [
                    "channel" => $source->name,
                    "total" => 0,
                    "positive" => 0,
                    "neutral" => 0,
                    "negative" => 0,
                    "sentiment_score" => 0,
                ];
            }
        }


//        $data[] = [
//            "channel" => "Facebook",
//            "sentiment_score" => 3.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];

        if ($data) {
            usort($data, function ($a, $b) {
                return $b['total'] - $a['total'];
            });
        }

        if ($only_data) {
            return $data;
        }
        return parent::handleRespond($data);
    }

    public function SummaryKeyword($items, $keywords, $only_data = false)
    {

        $analysis_current = $this->factorySentimentScore($items, $keywords);

        $data = [];

        foreach ($analysis_current as $keyword_id => $item) {

            $positive = $item['positive'];
            $neutral = $item['neutral'];
            $negative = $item['negative'];
            $total = $item['total'];


            $data[$keyword_id] = [
                'keyword_id' => $keyword_id,
                'keyword_name' => $item['keyword_name'],
                'total_messages' => $total,
                'percentage' => $total / $item['message_total'] * 100,
                "positive" => self::point_two_digits(($positive / $total) * 100),
                "neutral" => self::point_two_digits(($neutral / $total) * 100),
                "negative" => self::point_two_digits(($negative / $total) * 100),

            ];
        }

        if ($data) {
            $data = array_values($data);

            if ($data) {
                usort($data, function ($a, $b) {
                    return $b['total_messages'] - $a['total_messages'];
                });
            }
        }

//        dd($analysis_current);

//        $data[] = [
//            "keyword" => "Keyword 1",
//            "total_messages" => 212,
//            "percentage" => 61,
//            "positive" => 30,
//            "neutral" => 45,
//            "negative" => 5,
//        ];
//
//        $data[] = [
//            "keyword" => "Keyword 2",
//            "total_messages" => 75,
//            "percentage" => 22,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];


        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }


    private function overPeriodComparison($current, $previous)
    {

        if ($current - $previous === 0 || $previous === 0) {
            return 0;
        }

        return (float)self::point_two_digits((($current - $previous) / $previous) * 100);
    }

    private function custom_number_format($n, $precision = 3)
    {
        if ($n < 1000000) {
            // Anything less than a million
            $n_format = number_format($n);
        } else if ($n < 1000000000) {
            // Anything less than a billion
            $n_format = number_format($n / 1000000, $precision) . 'M';
        } else {
            // At least a billion
            $n_format = number_format($n / 1000000000, $precision) . 'B';
        }

        return $n_format;
    }

    private function raw_message_classification($keywords) 
    {
        $keywordIds = $keywords->pluck('id')->all();

        //error_log('keywordid: '.json_encode($keywordIds));
        $data = DB::table('messages')
            ->select([
                'messages.message_id as message_id',
                'messages.reference_message_id as reference_message_id',
                'messages.keyword_id as keyword_id',
                'messages.created_at as date_m',
                'messages.author as author',
                'messages.source_id as source_id',
                'messages.message_type',
                'messages.device as device',
                'message_results_2.message_id as result_message_id',
                'message_results_2.media_type',
                'message_results_2.classification_sentiment_id',
                'message_results_2.classification_type_id',
                'message_results_2.classification_level_id',
                'messages.number_of_views as number_of_views',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_reactions as number_of_reactions',
                'messages.created_at as created_at',
                DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) +
                    COALESCE(number_of_views, 0) AS total_engagement'),
            ])
            ->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
            ->where('message_results_2.media_type', 1)
            ->whereIn('keyword_id', $keywordIds);

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $data->whereIn('source_id', $this->source_id);
            } else {
                $data->where('source_id', $this->source_id);
            }
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        return $data;
    }

}



