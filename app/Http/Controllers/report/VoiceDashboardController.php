<?php

namespace App\Http\Controllers\report;

use App\Models\Organization;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Classification;
use App\Models\Keyword;
use Error;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VoiceDashboardController extends Controller
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

        if (!$request->campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->campaign_id = $request->campaign_id;
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

    public function PercentageOfMessage(Request $request)
    {
        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);
        $data = null;
        $raw = $this->raw_message($keywords, $this->start_date, $this->end_date);
        $raw_previous = $this->raw_message($keywords, $this->start_date_previous, $this->end_date_previous);

        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($raw, $keywords, $this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($raw_previous, $keywords, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }

    private function percentageOfMessages($raw, $keywords, $start_date, $end_date)
    {

        $items = $raw->get();
        $message_total = 0;
        $message_keywords = [];
        $data = [];
        foreach ($items as $item) {
            $message_total += 1;

            if (isset($message_keywords[$item->keyword_id])) {
                $message_keywords[$item->keyword_id]['total'] += 1;
            } else {
                $message_keywords[$item->keyword_id] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                    /*'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                ];
                $message_keywords[$item->keyword_id]['total'] = 1;
            }
        }

        foreach ($message_keywords as $keyword_id => $message_keyword) {

            $data[$keyword_id]['keyword_id'] = $message_keyword['keyword_id'];
            $data[$keyword_id]['keyword_name'] = $message_keyword['keyword_name'];
            /*$data[$keyword_id]['campaign_id'] = $message_keyword['campaign_id'];
            $data[$keyword_id]['campaign_name'] = $message_keyword['campaign_name'];*/
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => self::point_two_digits(($message_keyword['total'] / $message_total ?? 1) * 100),
                'total' => self::point_two_digits($message_total, 0)

            ];
        }

        if ($data) {
            $data = array_values($data);
        }

        return $data;
    }

    public function DailyMessage(Request $request)
    {
        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);
        $raw = $this->raw_message($keywords, $this->start_date, $this->end_date);
        $sources = $this->getAllSource();

        $data = [];

        $items = $raw->get();

        foreach ($items as $item) {
            $keyword_id = $item->keyword_id;
            $date_m = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$keyword_id])) {
                if (isset($data[$keyword_id]['value'][$date_m])) {
                    $data[$keyword_id]['value'][$date_m]['total_at_date'] += 1;
                } else {
                    $data[$keyword_id]['value'][$date_m] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => self::matchKeywordName($keywords, $item->keyword_id),
                        "date" => $date_m,
                        'total_at_date' => 1
                    ];
                }
            } else {

                $data[$keyword_id] = [
                    'source_id' => $item->source_id,
                    'source_name' => self::matchSourceName($sources, $item->source_id),
                    // 'date' => $date_m,
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/

                ];
                $data[$keyword_id]['value'][$date_m] = [
                    "keyword_id" => $item->keyword_id,
                    "keyword_name" => self::matchKeywordName($keywords, $item->keyword_id),
                    'date' => $date_m,
                    'total_at_date' => 1
                ];

                // $data[$keyword_id]['value'][$date_m] = $nestData;
            }

        }

        foreach ($data as $k => $value) {
            if ($value['value']) {
                $data[$k]['value'] = array_values($value['value']);
            }
        }

        if ($data) {
            $data = array_values($data);
        }

        return parent::handleRespond($data);
    }

    /**
     * prepare function for group
     * @param Request $request
     * @return void
     */
    public function messageBy(Request $request)
    {
        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);
        $sources = $this->getAllSource();
        $raw = $this->raw_message($keywords, $this->start_date, $this->end_date);
        //error_log($raw->toSql());
        $data = [];
        $items = $raw->get();

        $data['messageByDay'] = $this->messageByDay($items, $keywords);
        $data['messageByTime'] = $this->messageByTime($items, $keywords);
        $data['messageByDevice'] = $this->messageByDevice($items, $keywords, $sources);
        $data['messageByAccount'] = $this->messageByAccount($items, $keywords);
        $data['messageByChannel'] = $this->MessageByChannel($items, $keywords, $sources);
        $data['messageBySentiment'] = $this->messageBySentiment($keywords);
        $data['messageByLevel'] = $this->messageByLevel($keywords);
        $data['messageByType'] = $this->messageByType($keywords);

        return parent::handleRespond($data);

    }

    private function messageByDay($items, $keywords)
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

        $data['value'] = null;

        if ($items) {
            foreach ($items as $item) {
                $day_name = Carbon::parse($item->date_m)->format('D');
                $index_label = array_search($day_name, $data['labels']);

                if (!isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id] = [
                        "id" => $item->keyword_id,
                        "keyword_name" => $this->matchKeywordName($keywords, $item->keyword_id),
                        /*"campaign_id" => $item->campaign_id,
                        "campaign_name" => $item->campaign_name,*/
                        'data' => [0, 0, 0, 0, 0, 0, 0]
                    ];

                }
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }


    private function messageByTime($items, $keywords)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $data['value'] = null;

        if ($items) {
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

                if (!isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                        /*'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,*/
                        'data' => [0, 0, 0, 0]
                    ];

                }
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function messageByDevice($items, $keywords, $sources)
    {
        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        if ($items) {
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

                    if (!isset($data['value'][$item->keyword_id])) {
                        $data['value'][$item->keyword_id] = [
                            'id' => $item->keyword_id,
                            'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                            /*'campaign_id' => $item->campaign_id,
                            'campaign_name' => $item->campaign_name,*/
                            'source_id' => $item->source_id,
                            'source_name' => $this->matchSourceName($sources, $item->source_id),
                            'data' => [0, 0, 0]
                        ];

                    }
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }
        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function messageByAccount($items, $keywords)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        if ($items) {

            foreach ($items as $item) {

                if (!isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                        /*'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,*/
                        'data' => [0, 0]
                    ];

                }
                if (!$item->reference_message_id) {
                    $data['value'][$item->keyword_id]['data'][0] += 1;
                } else {
                    $data['value'][$item->keyword_id]['data'][1] += 1;
                }
            }
        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;

    }

    private function messageByChannel($items, $keywords, $sources)
    {

        $data = parent::listSource();

        if ($items) {
            foreach ($items as $item) {
                $sourceName = self::matchSourceName($sources, $item->source_id);
                $keywordName = $this->matchKeywordName($keywords, $item->keyword_id);
                $index_label = array_search($sourceName, $data['labels']);
                if (!isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'name' => $keywordName,
                        'keyword_name' => $keywordName,
                        /*'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name*/
                    ];

                    for ($i = 0; $i <= count($data['labels']); $i++) {
                        $data['value'][$item->keyword_id]['data'][] = 0;
                    }

                }
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function messageBySentiment($keywords)
    {
        $raw = $this->raw_messageJoinMessageResult($keywords, $this->start_date, $this->end_date);
        $raw = $raw->whereIn('message_results_2.classification_sentiment_id', [1, 2, 3]);
        $items = $raw->get();
        $classifications = Classification::where('classification_type_id', 1)->get();

        $labels = [
            "Positive",
            "Neutral",
            "Negative"
        ];

        $data['labels'] = $labels;

        if ($items) {
            foreach ($items as $item) {
                $classificationName = self::matchClassificationName($classifications, $item->classification_sentiment_id);
                $index_label = array_search($classificationName, $labels);
                if (!isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                        'data' => [0, 0, 0]
                    ];
                }

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function messageByLevel($keywords)
    {

        $data = [];
        $raw = $this->raw_messageJoinMessageResult($keywords, $this->start_date, $this->end_date);
        $raw = $raw->whereIn('message_results_2.classification_level_id', [9, 10, 11, 12]);
        $items = $raw->get();

        $classifications = Classification::where('classification_type_id', 3)->get();

        foreach ($classifications as $classification) {
            $data['labels'][] = $classification->name;
        }

        foreach ($items as $item) {
            $classificationName = self::matchClassificationName($classifications, $item->classification_level_id);
            $index_label = array_search($classificationName, $data['labels']);
            if (!isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                    /*'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][] = 0;
                }

            }
            $data['value'][$item->keyword_id]['data'][$index_label] += 1;
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;

    }

    private function messageByType($keywords)
    {

        $data = [];

        $raw = $this->raw_messageJoinMessageResult($keywords, $this->start_date, $this->end_date);
        $raw = $raw->whereIn('message_results_2.classification_type_id', [4, 5, 6, 7, 8]);
        $items = $raw->get();

        $classifications = Classification::where('classification_type_id', 2)->get();
        foreach ($classifications as $classification) {
            $data['labels'][] = $classification->name;
        }

        foreach ($items as $item) {
            $classificationName = self::matchClassificationName($classifications, $item->classification_type_id);
            $index_label = array_search($classificationName, $data['labels']);
            if (!isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                    /*'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][] = 0;
                }

            }
            $data['value'][$item->keyword_id]['data'][$index_label] += 1;
        }

        if (isset($data['value'])) {
            // dd($data['value']);
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }


    public function NumberOfAccountPeriodOverPeriod(Request $request)
    {
        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);
        $sources = $this->getAllSource();

        $raw = $this->raw_message(
            $keywords,
            $this->start_date,
            $this->end_date,
        )
        // ->groupBy('author')->get();
        ->get();

        $data['numberOfAccount'] = $this->factoryNumberOfAccountPeriodOverPeriod('numberOfAccount', $raw, $keywords, $sources);
        $data['PeriodOverPeriod'] = $this->factoryNumberOfAccountPeriodOverPeriod('PeriodOverPeriod', $raw, $keywords, $sources);

        return parent::handleRespond($data);
    }

    private function factoryNumberOfAccountPeriodOverPeriod($type, $raw, $keywords, $sources)
    {

        // if ($type === 'numberOfAccount') {
        //     $data = null;
        //     foreach ($raw as $item) {
        //         $keyword_id = $item->keyword_id;
        //         $date_m = Carbon::parse($item->date_m)->format('Y-m-d');

        //         if (isset($data[$keyword_id])) {
        //             if (isset($data[$keyword_id]['value'][$date_m])) {
        //                 $data[$keyword_id]['value'][$date_m]['total_at_date'] += 1;
        //             } else {
        //                 $data[$keyword_id]['value'][$date_m] = [
        //                     "keyword_id" => $item->keyword_id,
        //                     "keyword_name" => self::matchKeywordName($keywords, $item->keyword_id),
        //                     "date" => $date_m,
        //                     'total_at_date' => 1
        //                 ];
        //             }
        //         } else {

        //             $data[$keyword_id] = [
        //                 // 'date' => $date_m,
        //                 /*"campaign_id" => $item->campaign_id,
        //                 "campaign_name" => $item->campaign_name,*/
        //                 "source_id" => $item->source_id,
        //                 "source_name" => self::matchSourceName($sources, $item->source_id),

        //             ];
        //             $data[$keyword_id]['value'][$date_m] = [
        //                 "keyword_id" => $item->keyword_id,
        //                 "keyword_name" => self::matchKeywordName($keywords, $item->keyword_id),
        //                 'date' => $date_m,
        //                 'total_at_date' => 1
        //             ];
        //         }
        //     }


        //     if ($data) {

        //         foreach ($data as $k => $value) {
        //             if ($value['value']) {
        //                 $data[$k]['value'] = array_values($value['value']);
        //             }
        //         }

        //         $data = array_values($data);
        //     }

        //     return $data;
        // }

        if ($type === 'numberOfAccount') {
            $data = [];
            $authorTracker = [];
    
            foreach ($raw as $item) {
                $keyword_id = $item->keyword_id;
                $date_m = Carbon::parse($item->date_m)->format('Y-m-d');
                $source_id = $item->source_id;
                $author = $item->author;
    
                // ตรวจสอบว่าเคยเจอ author นี้ใน keyword/date/source เดิมหรือยัง
                $trackKey = "{$keyword_id}|{$date_m}|{$source_id}|{$author}";
                if (isset($authorTracker[$trackKey])) {
                    continue; // ถ้าเจอแล้ว ข้ามไป
                }
                $authorTracker[$trackKey] = true;
    
                // สร้างโครงสร้างข้อมูลถ้ายังไม่มี
                if (!isset($data[$keyword_id])) {
                    $data[$keyword_id] = [
                        "source_id" => $source_id,
                        "source_name" => self::matchSourceName($sources, $source_id),
                        "value" => []
                    ];
                }
    
                if (isset($data[$keyword_id]['value'][$date_m])) {
                    $data[$keyword_id]['value'][$date_m]['total_at_date'] += 1;
                } else {
                    $data[$keyword_id]['value'][$date_m] = [
                        "keyword_id" => $keyword_id,
                        "keyword_name" => self::matchKeywordName($keywords, $keyword_id),
                        "date" => $date_m,
                        "total_at_date" => 1
                    ];
                }
            }
    
            // แปลง value ให้เป็น array
            foreach ($data as $k => $value) {
                $data[$k]['value'] = array_values($value['value']);
            }
    
            return array_values($data);
        }

        if ($type === 'PeriodOverPeriod') {


            $raw_previous = $this->raw_message(
                $keywords,
                $this->start_date_previous,
                $this->end_date_previous,
            );

            $total_message_current = count($raw);
            $total_message_previous = $raw_previous->count();

            $items_current = $raw;
            // $items_previous = $raw_previous->groupBy('author')->get();
            $items_previous = $raw_previous->get();

            // $total_account_current = [];
            // $total_account_previous = [];

            // foreach ($items_current as $current) {
            //     // $total_message_current += 1;
            //     if (isset($total_account_current[$current->author])) {
            //         $total_account_current[$current->author] += 1;
            //     } else {
            //         $total_account_current[$current->author] = 1;
            //     }

            // }


            // foreach ($items_previous as $previous) {
            //     // $total_message_previous += 1;
            //     if (isset($total_account_previous[$previous->author])) {
            //         $total_account_previous[$previous->author] += 1;
            //     } else {
            //         $total_account_previous[$previous->author] = 1;
            //     }

            // }

            $total_account_current = $total_message_current ?? 0;
            $total_account_previous = $items_previous->count() ?? 0;

            // นับ author ไม่ซ้ำ แยกตาม keyword_id + source_id แล้วค่อยรวม
            $total_account_current = collect($items_current)
                ->groupBy(function ($item) {
                    return $item->keyword_id . '-' . $item->source_id;
                })
                ->map(function ($group) {
                    return $group->pluck('author')->unique()->count();
                })
                ->sum();

            $total_account_previous = collect($items_previous)
                ->groupBy(function ($item) {
                    return $item->keyword_id . '-' . $item->source_id;
                })
                ->map(function ($group) {
                    return $group->pluck('author')->unique()->count();
                })
                ->sum();

            $data['total_messages'] = [
                "total_message" => $total_message_current,
                "percentage" => parent::point_two_digits($total_message_previous !== 0 ? ($total_message_current - $total_message_previous) / $total_message_previous * 100 : 0),
                "type" => $total_message_current - $total_message_previous > 0 ? "plus" : "minus",
            ];
//
            $data['total_account'] = [
                "total_message" => $total_account_current,
                "percentage" => parent::point_two_digits($total_account_previous !== 0 ? ($total_account_current - $total_account_previous) / $total_account_previous * 100 : 0),
                "type" => $total_account_current - $total_account_previous > 0 ? "plus" : "minus",
            ];

            return $data;
        }
        return null;
    }

    public function NumberOfAccount(Request $request)
    {
        return parent::handleRespond($this->factoryNumberOfAccountPeriodOverPeriod('numberOfAccount'));
    }


    public function PeriodOverPeriod(Request $request)
    {
        return parent::handleRespond($this->factoryNumberOfAccountPeriodOverPeriod('PeriodOverPeriod'));
    }

    public function DayTimeComparison($raw, $sources, $only_Data = false)
    {
        $data = [];

        // Initialize data structure
        foreach ($raw as $item) {
            $date_h = (int)Carbon::parse($item->date_m)->format('H');
            $date_d = Carbon::parse($item->date_m)->format('D');

            if (!isset($data[$date_d])) {
                $data[$date_d] = [
                    "name" => $date_d,
                    "data" => array_fill(0, 24, 0) // Initialize array with 24 elements, all set to 0
                ];
            }

            // Update data
            $data[$date_d]["data"][$date_h] += 1;
        }

        // Convert arrays to indexed arrays
        foreach ($data as &$value) {
            $value["data"] = array_values($value["data"]);
        }

        $data = array_values($data);

        if ($only_Data) {
            return $data;
        }

        return parent::handleRespond($data);
    }


    public function DayTimeSentiment( $keywords, $only_Data = false)
    {
        $data = null;
        $items = $this->raw_messageJoinMessageResult($keywords, $this->start_date, $this->end_date)
            ->whereIn('message_results_2.classification_sentiment_id', [1, 2, 3])
            ->get();
        $classifications = Classification::where('classification_type_id', 1)->get();
        foreach ($classifications as $classification) {
            $data['day_value'][$classification->name] = [
                "name" => $classification->name,
                "data" => array_fill(0, 7, 0) // Initialize array with 7 elements, all set to 0
            ];

            $data['time_value'][$classification->name] = [
                "name" => $classification->name,
                "data" => array_fill(0, 24, 0) // Initialize array with 24 elements, all set to 0
            ];
        }
        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $date_h = (int)Carbon::parse($item->date_m)->format('H');
            $classificationName = self::matchClassificationName($classifications, $item->classification_sentiment_id);
            if (!isset($data['day_value'][$classificationName])) {
                $data['day_value'][$classificationName] = [
                    "name" => $classificationName,
                ];

                $data['time_value'][$classificationName] = [
                    "name" => $classificationName,
                ];

                /* for ($i = 0; $i < 7; $i++) {
                     $data['day_value'][$classificationName]["data"][$i] = 0;
                 }

                 for ($i = 0; $i < 24; $i++) {
                     $data['time_value'][$classificationName]["data"][$i] = 0;
                 }*/


            }
            if ($date_d == "Mon") {
                $data['day_value'][$classificationName]["data"][0] += 1;
            } else if ($date_d == "Tue") {
                $data['day_value'][$classificationName]["data"][1] += 1;
            } else if ($date_d == "Wed") {
                $data['day_value'][$classificationName]["data"][2] += 1;
            } else if ($date_d == "Thu") {
                $data['day_value'][$classificationName]["data"][3] += 1;
            } else if ($date_d == "Fri") {
                $data['day_value'][$classificationName]["data"][4] += 1;
            } else if ($date_d == "Sat") {
                $data['day_value'][$classificationName]["data"][5] += 1;
            } else if ($date_d == "Sun") {
                $data['day_value'][$classificationName]["data"][6] += 1;
            }
            if (isset($data['time_value'][$classificationName]["data"][$date_h])) {
                $data['time_value'][$classificationName]["data"][$date_h] += 1;
            } else {
                $data['time_value'][$classificationName]["data"][$date_h] = 1;
            }
        }


        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key] = array_values($value);
            }

            foreach ($data['day_value'] as $key => $value) {
                $data['day_value'][$key]["data"] = array_values($value["data"]);
            }

            foreach ($data['time_value'] as $key => $value) {
                $data['time_value'][$key]["data"] = array_values($value["data"]);
            }
        }
        if ($only_Data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function DayTimeLevel( $keywords, $only_Data = false)
    {
        $data = null;
        $items = $this->raw_messageJoinMessageResult($keywords, $this->start_date, $this->end_date)
            ->whereIn('message_results_2.classification_level_id', [9, 10, 11, 12])
            ->get();
        $classifications = Classification::where('classification_type_id', 3)->get();
        // Initialize data structure
        foreach ($classifications as $classification) {
            $data['day_value'][$classification->name] = [
                "name" => $classification->name,
                "data" => array_fill(0, 7, 0) // Initialize array with 7 elements, all set to 0
            ];

            $data['time_value'][$classification->name] = [
                "name" => $classification->name,
                "data" => array_fill(0, 24, 0) // Initialize array with 24 elements, all set to 0
            ];
        }

        // Process items
        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $date_h = (int)Carbon::parse($item->date_m)->format('H');
            $classificationName = self::matchClassificationName($classifications, $item->classification_level_id);

            // // Update day_value data
            // $data['day_value'][$classificationName]["data"][date('N', strtotime($date_d)) - 1] += 1;

            // // Update time_value data
            // $data['time_value'][$classificationName]["data"][$date_h] += 1;

            if (!empty($classificationName) && isset($data['day_value'][$classificationName])) {
                $data['day_value'][$classificationName]["data"][date('N', strtotime($date_d)) - 1] += 1;
                $data['time_value'][$classificationName]["data"][$date_h] += 1;
            }
            
        }

        // Convert arrays to indexed arrays
        $data['day_value'] = array_values($data['day_value']);
        $data['time_value'] = array_values($data['time_value']);

        foreach ($data['day_value'] as &$value) {
            $value['data'] = array_values($value['data']);
        }

        foreach ($data['time_value'] as &$value) {
            $value['data'] = array_values($value['data']);
        }

        if ($only_Data) {
            return $data;
        }

        return parent::handleRespond($data);
    }


    public function DayTimeType( $keywords, $only_Data = false)
    {
        $data = null;
        $items = $this->raw_messageJoinMessageResult($keywords, $this->start_date, $this->end_date)
            ->whereIn('message_results_2.classification_type_id', [4, 5, 6, 7, 8])
            ->get();
        $classifications = Classification::where('classification_type_id', 2)->get();

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $date_h = (int)Carbon::parse($item->date_m)->format('H');
            $classificationName = self::matchClassificationName($classifications, $item->classification_type_id);
            if (!isset($data['day_value'][$classificationName])) {
                $data['day_value'][$classificationName] = [
                    "name" => $classificationName,
                ];

                $data['time_value'][$classificationName] = [
                    "name" => $classificationName,
                ];

                for ($i = 0; $i < 7; $i++) {
                    $data['day_value'][$classificationName]["data"][$i] = 0;
                }

                for ($i = 0; $i < 24; $i++) {
                    $data['time_value'][$classificationName]["data"][$i] = 0;
                }


            }
            if ($date_d == "Mon") {
                $data['day_value'][$classificationName]["data"][0] += 1;
            } else if ($date_d == "Tue") {
                $data['day_value'][$classificationName]["data"][1] += 1;
            } else if ($date_d == "Wed") {
                $data['day_value'][$classificationName]["data"][2] += 1;
            } else if ($date_d == "Thu") {
                $data['day_value'][$classificationName]["data"][3] += 1;
            } else if ($date_d == "Fri") {
                $data['day_value'][$classificationName]["data"][4] += 1;
            } else if ($date_d == "Sat") {
                $data['day_value'][$classificationName]["data"][5] += 1;
            } else if ($date_d == "Sun") {
                $data['day_value'][$classificationName]["data"][6] += 1;
            }
            if (isset($data['time_value'][$classificationName]["data"][$date_h])) {
                $data['time_value'][$classificationName]["data"][$date_h] += 1;
            } else {
                $data['time_value'][$classificationName]["data"][$date_h] = 1;
            }
        }


        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key] = array_values($value);
            }

            foreach ($data['day_value'] as $key => $value) {
                $data['day_value'][$key]["data"] = array_values($value["data"]);
            }

            foreach ($data['time_value'] as $key => $value) {
                $data['time_value'][$key]["data"] = array_values($value["data"]);
            }
        }

        if ($only_Data) {
            return $data;
        }


        return parent::handleRespond($data);
    }

    public function DayTimeBy(Request $request)
    {

        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);
        $sources = $this->getAllSource();
        //$raw_classification = $this->raw_message_classification($keywords, $this->start_date, $this->end_date)->get();
        /* $items1 = [];
         $items2 = [];
         $items3 = [];
         foreach ($raw_classification as $item) {
             if ($item->classification_id < 4) {
                 $items1[] = $item;
             } else if ($item->classification_id > 9) {
                 $items3[] = $item;
             } else {
                 $items2[] = $item;
             }
         }*/

        $classifications = self::getClassificationMaster();
        $raw = $this->raw_message($keywords, $this->start_date, $this->end_date)->get();

        $data = [
            'DayTimeComparison' => $this->DayTimeComparison($raw, $sources, true),
            'DayTimeSentiment' => $this->DayTimeSentiment( $keywords, true),
            'DayTimeLevel' => $this->DayTimeLevel( $keywords, true),
            'DayTimeType' => $this->DayTimeType( $keywords, true),
        ];

        return parent::handleRespond($data);
    }


    public function channelPlatformChannelDevice(Request $request)
    {
        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);

        $raw = $this->raw_message(
            $keywords,
            $this->start_date,
            $this->end_date,
        // $classification_tree
        )->get();

        $raw_previous = $this->raw_message(
            $keywords,
            $this->start_date_previous,
            $this->end_date_previous,
        // $classification_tree
        )->get();
        $sources = $this->getAllSource();
        $data['channelPlatform'] = $this->getChannelPlatform($raw, $raw_previous, $sources);
        $data['device'] = $this->getDevice($raw, $raw_previous);
        $data['channelDevice'] = $this->getChannelDevice($raw, $sources);

        return parent::handleRespond($data);
    }

    private function getChannelPlatform($raw, $raw_previous, $sources)
    {
        $data = null;
        $labels = parent::listSource();

        $items = $raw;
        $items_previous = $raw_previous;

        $data['current_period']['label'] = $labels['labels'];
        $data['previous_period']['label'] = $labels['labels'];
        $data['current_period']['total'] = 0;
        $data['previous_period']['total'] = 0;

        for ($i = 0; $i < count($labels['labels']); $i++) {
            $data['current_period']['data'][] = 0;
            $data['previous_period']['data'][] = 0;
        }

        if ($items) {
            foreach ($items as $item) {
                $index_label = array_search(self::matchSourceName($sources, $item->source_id), $data['current_period']['label']);
                $data['current_period']['data'][$index_label] += 1;
                $data['current_period']['total'] += 1;
            }
        }

        if ($items_previous) {
            foreach ($items_previous as $item) {
                $index_label = array_search(self::matchSourceName($sources, $item->source_id), $data['previous_period']['label']);
                $data['previous_period']['data'][$index_label] += 1;
                $data['previous_period']['total'] += 1;
            }
        }

        return $data;
    }

    private function getDevice($raw, $raw_previous)
    {
        $data = null;
        $labels = ["labels" => ['Andriod', 'Iphone', 'Web App']];

        $items = $raw;
        $items_previous = $raw_previous;

        $data['current_period']['label'] = $labels['labels'];
        $data['previous_period']['label'] = $labels['labels'];
        $data['current_period']['total'] = 0;
        $data['previous_period']['total'] = 0;

        for ($i = 0; $i < count($labels['labels']); $i++) {
            $data['current_period']['data'][] = 0;
            $data['previous_period']['data'][] = 0;
        }

        if ($items) {
            foreach ($items as $item) {


                if ($item->device === "android") {
                    $data['current_period']['data'][0] += 1;
                    $data['current_period']['total'] += 1;
                }
                if ($item->device === "iphone") {
                    $data['current_period']['data'][1] += 1;
                    $data['current_period']['total'] += 1;
                }
                if ($item->device === "webapp" || $item->device == 'website') {
                    $data['current_period']['data'][2] += 1;
                    $data['current_period']['total'] += 1;
                }

            }
        }

        if ($items_previous) {
            foreach ($items_previous as $item) {
                if ($item->device === "android") {

                    $data['previous_period']['data'][0] += 1;
                    $data['previous_period']['total'] += 1;
                }
                if ($item->device === "iphone") {
                    $data['previous_period']['data'][1] += 1;
                    $data['previous_period']['total'] += 1;
                }
                if ($item->device === "webapp" || $item->device == 'website') {
                    $data['previous_period']['data'][2] += 1;
                    $data['previous_period']['total'] += 1;
                }

            }
        }

        return $data;
    }

    private function getChannelDevice($raw, $sources)
    {

        $data = null;
        $labels = parent::listSource();
        $devices = ['Android', 'Iphone', 'Web app'];

        //$raw = $this->raw_message($this->campaign_id, $this->start_date, $this->end_date);
        $items = $raw;//->get();


        foreach ($labels['labels'] as $label) {
            foreach ($devices as $device) {
                $data['labels'][$label . '-' . $device] = [$label, $device];
                $data['data'][$label . '-' . $device] = 0;
            }
        }


        foreach ($items as $item) {
            $device = 'empty';

            if ($item->device === 'android') {
                $device = 'Android';
            } else if ($item->device === 'iphone') {
                $device = 'Iphone';
            } else if ($item->device === 'webapp' || $item->device === 'website') {
                $device = 'Web app';
            }

            if ($device !== 'empty') {
                $sourceName = self::matchSourceName($sources, $item->source_id);
                if (isset($data['data'][$sourceName . '-' . $device])) {
                    $data['data'][$sourceName . '-' . $device] += 1;
                } else {
                    $data['data'][$item->source_name . '-' . $device] = 1;
                }
            }
        }

        if (isset($data['data'])) {
            $data['data'] = array_values($data['data']);
        }

        if (isset($data['labels'])) {
            $data['labels'] = array_values($data['labels']);
        }

        return $data;
    }

    public function keywordBy(Request $request)
    {


        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);
        $sources = $this->getAllSource();
        $raw_classification = $this->raw_message_classification($keywords, $this->start_date, $this->end_date)->get();
        $items1 = [];
        $items2 = [];
        $items3 = [];

        foreach ($raw_classification as $item) {
            if ($item->classification_sentiment_id >= 1 && $item->classification_sentiment_id <= 3) {
                $items1[] = $item;
            }

            if ($item->classification_type_id >= 4 && $item->classification_type_id <= 8) {
                $items2[] = $item;
            }

            if ($item->classification_level_id >= 9 && $item->classification_level_id <= 12) {
                $items3[] = $item;
            }
        }

        $data['keywordChannel'] = $this->getKeywordChannel($raw_classification, $sources,$keywords);
        $data['keywordSentiment'] = $this->getKeywordSentiment($items1, $keywords);
        $data['keywordBullyLevel'] = $this->getKeywordBullyLevel($items3, $keywords);
        $data['keywordBullyType'] = $this->getKeywordBullyType($items2, $keywords);

        return parent::handleRespond($data);

    }

    private function getKeywordChannel($items, $sources, $keywords)
    {

        $data = parent::listSource();
        // $raw = $conditions['raw'] ?? null;
        //$message_total = 0;


        if ($items) {
            foreach ($items as $item) {
                $SourceName = self::matchSourceName($sources, $item->source_id);
                $index_label = array_search($SourceName, $data['labels']);
                //$message_total += 1;
                if (!isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_id' => $item->keyword_id,
                        'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id)
                    ];

                    for ($i = 0; $i < count($data['labels']); $i++) {
                        $data['value'][$item->keyword_id]['data'][] = 0;
                        $data['value'][$item->keyword_id]['total'] = 0;
                    }

                }
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                $data['value'][$item->keyword_id]['total'] += 1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
            foreach ($data['value'] as $keyword => $item_keyword) {
                foreach ($item_keyword['data'] as $key => $item) {
                    $data['value'][$keyword]['data'][$key] = Self::point_two_digits(($item / $item_keyword['total']) * 100, 2);
                }
            }
        }

        return $data;
    }

    private function getKeywordSentiment($raw, $keywords)
    {
        $data = null;

        $sentiments = Classification::where('classification_type_id', 1)->get();
        $message_total = 0;

        foreach ($sentiments as $item) {
            $data['labels'][] = $item->name;
        }

        foreach ($raw as $item) {
            $classificationName = self::matchClassificationName($sentiments, $item->classification_sentiment_id);
            $index_label = array_search($classificationName, $data['labels']);
            $message_total += 1;
            if (!isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                    'data' => [0, 0, 0],
                    'total' => 0
                ];

            }
            $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            $data['value'][$item->keyword_id]['total'] += 1;
        }


        if (isset($data['value'])) {
            // $data['value'] = array_values($data['value']);
            $data['value'] = array_values($data['value']);
            foreach ($data['value'] as $keyword => $item_keyword) {
                foreach ($item_keyword['data'] as $key => $item) {
                    $data['value'][$keyword]['data'][$key] = Self::point_two_digits(($item / $item_keyword['total']) * 100, 2);
                }
            }
        }

        return $data;
    }

    private function getKeywordBullyLevel($raw, $keywords)
    {
        $data = null;
        $levels = Classification::where('classification_type_id', 3)->get();
        $message_total = 0;

        foreach ($levels as $item) {
            $data['labels'][] = $item->name;
        }


        foreach ($raw as $item) {
            $classificationName = self::matchClassificationName($levels, $item->classification_level_id);
            $index_label = array_search($classificationName, $data['labels']);
            $message_total += 1;
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                $data['value'][$item->keyword_id]['total'] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                    'data' => [0, 0, 0, 0],
                    'total' => 0
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                $data['value'][$item->keyword_id]['total'] += 1;
            }
        }

        if (isset($data['value'])) {
            // $data['value'] = array_values($data['value']);
            $data['value'] = array_values($data['value']);
            foreach ($data['value'] as $keyword => $item_keyword) {
                foreach ($item_keyword['data'] as $key => $item) {
                    $data['value'][$keyword]['data'][$key] = Self::point_two_digits(($item / $item_keyword['total']) * 100, 2);
                }
            }
        }

        return $data;
    }

    private function getKeywordBullyType($raw, $keywords)
    {

        $levels = Classification::where('classification_type_id', 2)->get();
        $message_total = 0;

        foreach ($levels as $item) {
            $data['labels'][] = $item->name;
        }

        foreach ($raw as $item) {
            $classificationName = self::matchClassificationName($levels, $item->classification_type_id);
            $index_label = array_search($classificationName, $data['labels']);
            $message_total += 1;
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                $data['value'][$item->keyword_id]['total'] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][] = 0;
                    $data['value'][$item->keyword_id]['total'] = 0;
                }

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                $data['value'][$item->keyword_id]['total'] += 1;
            }
        }

        if (isset($data['value'])) {
            // $data['value'] = array_values($data['value']);
            $data['value'] = array_values($data['value']);
            foreach ($data['value'] as $keyword => $item_keyword) {
                foreach ($item_keyword['data'] as $key => $item) {
                    $data['value'][$keyword]['data'][$key] = Self::point_two_digits(($item / $item_keyword['total']) * 100, 2);
                }
            }
        }

        return $data;
    }

    private function raw_message($keywords, $start_date, $end_date)
    {
        $keywordIds = $keywords->pluck('id')->all();
        $data = DB::table('messages')
            ->select([
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',

                'sources.name as source_name',*/
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.reference_message_id as reference_message_id',
                'messages.author as author',
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        // if ($this->source_id) {
        //     $data->where('source_id', $this->source_id);
        // }

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

    private function raw_messageJoinMessageResult($keywords, $start_date, $end_date)
    {
        $keywordIds = $keywords->pluck('id')->all();
        $data = DB::table('messages')
            ->select([
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',

                'sources.name as source_name',*/
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.reference_message_id as reference_message_id',
                'messages.author as author',
                // 'message_results.classification_id',
                'message_results_2.media_type',
                'message_results_2.classification_sentiment_id',
                'message_results_2.classification_type_id',
                'message_results_2.classification_level_id',
                'messages.created_at as created_at'
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
            ->where('message_results_2.media_type', 1)
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        // if ($this->source_id) {
        //     $data->where('source_id', $this->source_id);
        // }
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

    private function raw_message_classification($keywords, $start_date, $end_date)
    {

        $keywordIds = $keywords->pluck('id')->all();

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
                // 'message_results.classification_id',
                'message_results_2.media_type',
                'message_results_2.classification_sentiment_id',
                'message_results_2.classification_type_id',
                'message_results_2.classification_level_id',
                'messages.created_at as created_at'
                /*'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'keywords.name as keyword_name',
                'classifications.classification_type_id',

                'classifications.name as classification_name',
                'classifications.color as classification_color',
                'sources.name as source_name',*/

            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')

            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')*/
            ->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
            ->where('message_results_2.media_type', 1)
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);
        //->whereIn('message_results.classification_type_id', $classification_type_ids);

        // if ($this->source_id) {
        //     $data->where('source_id', $this->source_id);
        // }

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
