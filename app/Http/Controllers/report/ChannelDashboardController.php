<?php

namespace App\Http\Controllers\report;

use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Classification;
use Illuminate\Support\Carbon;
use App\Models\Sources;
use Illuminate\Support\Facades\DB;
use App\Models\Keyword;
use Illuminate\Support\Facades\Log;

class ChannelDashboardController extends Controller
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

        // $this->request = $request;

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

    public function dailyBy(Request $request)
    {
        $data = null;

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);

        $raw = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
            ])
            ->whereIn('keyword_id', $keywords->pluck('id')->all())
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        // if ($this->source_id) {
        //     $raw->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $raw->whereIn('source_id', $this->source_id);
            } else {
                $raw->where('source_id', $this->source_id);
            }
        }

        $data['daily_message'] = self::DailyChannelGroup($raw, $keywords);
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($keywords, $this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($keywords, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);

    }

    public function DailyChannelGroup($raw, $keywords)
    {
        $sources = self::getAllSource();
        $items = $raw->get();
        $data = [];

        // ตรวจสอบว่ามี keyword เดียวหรือหลาย keyword
        $isSingleKeyword = $keywords->count() === 1;

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (!isset($data[$item->source_id])) {
                $data[$item->source_id] = [
                    "source_id" => $item->source_id,
                    "source_name" => self::matchSourceName($sources, $item->source_id),
                    "value" => []
                ];
            }

            // Find or create the value entry for the date
            $valueIndex = array_search($date_format, array_column($data[$item->source_id]['value'], 'date_m'));

            if ($valueIndex === false) {
                $entry = [
                    'date_m' => $date_format,
                    'total_at_date' => 1
                ];

                // เพิ่ม keyword_id และ keyword_name เฉพาะกรณีมี keyword เดียว
                if ($isSingleKeyword) {
                    $entry['keyword_id'] = $item->keyword_id;
                    $entry['keyword_name'] = self::matchKeywordName($keywords, $item->keyword_id);
                }

                $data[$item->source_id]['value'][] = $entry;
            } else {
                $data[$item->source_id]['value'][$valueIndex]['total_at_date']++;
            }
        }

        // Resetting keys to numeric
        $data = array_values($data);

        return self::fetchSourceOrder($sources, $data);
    }



    private function PercentageToCal($keyword, $start_date, $end_date)
    {
        $sources = self::getAllSource();
        $keywordIds = $keyword->pluck('id')->all();
        $data = null;
        $percentage_of_channel = $this->raw_messageTotal($keywordIds, $start_date, $end_date)->groupBy('source_id');
        $channel_message_total = $this->countChannelTable($keywordIds, $start_date, $end_date);

        foreach ($percentage_of_channel->get() as $channel) {
            $source_id = $channel->source_id;
            $data[$source_id]['keyword_id'] = $channel->keyword_id;
            $data[$source_id]['keyword_name'] = self::matchKeywordName($keyword, $channel->keyword_id);
            $data[$source_id]['source_id'] = $channel->source_id;
            $data[$source_id]['source_name'] = self::matchSourceName($sources, $channel->source_id);
            $data[$source_id]['total'] = self::point_two_digits($channel_message_total, 0);
            $data[$source_id]['count'] = $channel->total_messages;

            $nestData = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $channel_message_total ? $this->point_two_digits(($channel->total_messages / $channel_message_total) * 100) : 0,
            ];

            $data[$source_id]['value'][] = $nestData;
        }

        return self::fetchSourceOrder($sources, $data);
    }

    function fetchSourceOrder($sources,$data)
    {
        $result = [];
        Log::info('fetchSourceOrder received data', ['data' => $data]);

        if (!is_array($data) || empty($data)) {
            Log::error('fetchSourceOrder received null or non-array data', ['data' => $data]);
            return [];
        }
        $data = array_values($data);
        foreach ($sources as $source) {
            foreach ($data as $item) {
                if ($item['source_id'] == $source->id) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }

    public function channelBy()
    {
        $data = null;
        $sources = self::getAllSource();
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $raw = $this->raw_message($keywords->pluck('id')->all(), $this->start_date, $this->end_date);
        $result = $raw->get();

        $data['channel_by_day'] = $this->ChannelByDayGroup($result, $sources, $keywords);
        $data['channel_by_time'] = $this->ChannelByTimeGroup($result, $sources, $keywords);
        $data['channel_by_device'] = $this->ChannelByDeviceGroup($result, $sources, $keywords);
        $data['channel_by_account'] = $this->ChannelByAccountGroup($result, $sources, $keywords);

        $raw2 = $this->raw_message2($keywords->pluck('id')->all(), $this->start_date, $this->end_date);
        $result2 = $raw2->get();
        $classification = self::getClassificationMaster();
        $data['channel_by_sentiment'] = $this->ChannelBySentimentGroup($result2, $sources, $keywords, $classification);
        $data['channel_by_level'] = $this->ChannelBullyLevelGroup($result2, $sources, $keywords, $classification);
        $data['channel_by_bully_type'] = $this->ChannelBullyTypeGroup($result2, $sources, $keywords, $classification);

        return parent::handleRespond($data);
    }


    // function fetchChannelBySourceOrder($sources, $data)
    // {
    //     $result = [];
    //     foreach ($sources as $source) {
    //         foreach ($data['value'] as $item) {
    //             if ($item['source_id']== $source->id) {
    //                 $result[] = $item;
    //             }
    //         }
    //     }
    //     return $result;
    // }

    function fetchChannelBySourceOrder($sources, $data)
    {
        $result = [];
        $values = $data['value'] ?? []; 

        foreach ($sources as $source) {
            foreach ($values as $item) {
                if ($item['source_id'] == $source->id) {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    public function ChannelBullyTypeGroup($result2, $sources, $keywords, $classification)
    {
        $sentiment = Classification::where('classification_type_id', 2)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $data['value'] = [];
        foreach ($result2 as $item) {
            if ($item->classification_type_id > 3 && $item->classification_type_id < 8) {
                $classification_name = self::matchClassificationName($classification, $item->classification_type_id);
                $index_label = array_search($classification_name, $data['labels']);
                if (!isset($data['value'][$item->source_id])) {
                    $data['value'][$item->source_id] = [
                        'id' => $item->source_id,
                        'source_name' => self::matchSourceName($sources, $item->source_id),
                        'classification_name' => $classification_name,
                        'classification_id' => $item->classification_type_id,
                        'source_id' => $item->source_id,
                        'data' => [0, 0, 0, 0, 0, 0]
                    ];
                }
                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }
        }
        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        $data['value'] = self::fetchChannelBySourceOrder($sources, $data);
        return $data;
    }


    public function ChannelBullyLevelGroup($result2, $sources, $keywords, $classification)
    {

        $sentiment = Classification::where('classification_type_id', 3)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $data['value'] = [];
        foreach ($result2 as $item) {
            if ($item->classification_level_id > 7) {
                $classification_name = self::matchClassificationName($classification, $item->classification_level_id);
                $index_label = array_search($classification_name, $data['labels']);
                if (!isset($data['value'][$item->source_id])) {
                    $data['value'][$item->source_id] = [
                        'id' => $item->source_id,
                        'classification_name' => $classification_name,
                        'classification_id' => $item->classification_level_id,
                        'source_name' => self::matchSourceName($sources, $item->source_id),
                        'source_id' => $item->source_id,
                        'data' => [0, 0, 0, 0]
                    ];
                }
                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        $data['value'] = self::fetchChannelBySourceOrder($sources, $data);
        return $data;
    }

    public function ChannelBySentimentGroup($raw, $sources, $keywords, $classification)
    {

        // $sentiment = Classification::where('classification_type_id', 1)->get();
        // $data['labels'] = [];

        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'] = [];
        foreach ($raw as $item) {
            if ($item->classification_sentiment_id < 4) {
                $classification_name = self::matchClassificationName($classification, $item->classification_sentiment_id);
                $index_label = array_search($classification_name, $data['labels']);
                if (!isset($data['value'][$item->source_id])) {
                    $data['value'][$item->source_id] = [
                        'id' => $item->source_id,
                        'source_name' => self::matchSourceName($sources, $item->source_id),
                        'classification_name' => $classification_name,
                        'classification_id' => $item->classification_sentiment_id,
                        'source_id' => $item->source_id,
                        'data' => [0, 0, 0]
                    ];

                }
                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        $data['value'] = self::fetchChannelBySourceOrder($sources, $data);
        return $data;
    }

    private function ChannelByDayGroup($raw, $sources, $keywords)
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

        $data['value'] = [];
        foreach ($raw as $item) {

            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);

            if (!isset($data['value'][$item->source_id])) {

                $data['value'][$item->source_id] = [
                    'id' => $item->source_id,
                    'name' => self::matchSourceName($sources, $item->source_id),
                    'source_id' => $item->source_id,
                    'source_name' => self::matchSourceName($sources, $item->source_id),
                    'keyword_name' => self::matchSourceName($sources, $item->source_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];
            }
            $data['value'][$item->source_id]['data'][$index_label] += 1;
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        $data['value'] = self::fetchChannelBySourceOrder($sources, $data);
        return $data;
    }


    public function ChannelByTimeGroup($raw, $sources, $keywords)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $data['value'] = [];

        foreach ($raw as $item) {
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

            if (isset($data['value'][$item->source_id])) {
                $data['value'][$item->source_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->source_id] = [
                    'id' => $item->source_id,
                    'source_name' => self::matchSourceName($sources, $item->source_id),
                    'source_id' => $item->source_id,
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        $data['value'] = self::fetchChannelBySourceOrder($sources, $data);

        return $data;
    }


    public function ChannelByDeviceGroup($raw, $sources, $keywords)
    {
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $data['value'] = null;

        foreach ($raw as $item) {
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

                if (isset($data['value'][$item->source_id])) {
                    $data['value'][$item->source_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->source_id] = [
                        'id' => $item->keyword_id,
                        'source_id' => $item->source_id,
                        'source_name' => self::matchSourceName($sources, $item->source_id),
                     //   'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->source_id]['data'][$index_label] += 1;
                }
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        $data['value'] = self::fetchChannelBySourceOrder($sources, $data);
        return $data;

    }

    public function ChannelByAccountGroup($result, $sources, $keywords)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $soures = parent::listSource();
        // error_log(json_encode($sources));

        for ($i = 0; $i < count($soures['labels']); $i++) {
            // error_log($sources[$i]['id']);
            $data['value'][$soures['labels'][$i]] = [
                "id" => $sources[$i]['id'],
                "source_id" => $sources[$i]['id'],
                "keyword_name" => $soures['labels'][$i],
                'data' => [0, 0]
            ];
        }

        // foreach ($result as $key => $item) {
        //     //error_log($item->reference_message_id);
        //     $sources_name = self::matchSourceName($sources, $item->source_id);
        //     if ($item->reference_message_id == "") {
        //         $data['value'][$sources_name]['data'][0] += 1;
        //     } else {
        //         $data['value'][$sources_name]['data'][1] += 1;
        //     }
        // }

        // if ($data['value']) {
        //     $value = array_values($data['value']);
        //     $data['value'] = $this->filteredData($value);
        // }
        foreach ($result as $key => $item) {
            //error_log($item->reference_message_id);
            $sources_name = self::matchSourceName($sources, $item->source_id);
            if (empty($sources_name)) {
                continue; 
            }
        
            if ($item->reference_message_id == "") {
                if (!isset($data['value'][$sources_name]['data'][0])) {
                    $data['value'][$sources_name]['data'][0] = 0; 
                }
                $data['value'][$sources_name]['data'][0] += 1;
            } else {
                if (!isset($data['value'][$sources_name]['data'][1])) {
                    $data['value'][$sources_name]['data'][1] = 0; 
                }
                $data['value'][$sources_name]['data'][1] += 1;
            }
        }

        if ($data['value']) {
            $value = array_values($data['value']);
            $data['value'] = $this->filteredData($value);
        }
        

        return $data;
    }

    public function filteredData($data)
    {
        $filteredData = array_filter($data, function ($item) {
            return $item["data"] !== [0, 0];
        });

        $filteredData = array_values($filteredData);
        return $filteredData;
    }

    private function total_message_by_source_id($start_date, $end_date, $source_id_id)
    {
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }
        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

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

        if ($source_id_id === "all") {
            return $data->get()->count();
        }

        $data = $data->where('source_id', $source_id_id);

        return $data->get()->count();
    }

    public function engagementBy()
    {

        $sources = self::getAllSource();
        $keyword = self::findKeywords($this->campaign_id, $this->keyword_id);

        $data = null;
        $data['period_over_period'] = $this->PeriodOverPeriodGroup($keyword, $sources);
        $data['engagement_rate'] = $this->totalFromEngagementRate($keyword, $sources, $this->start_date, $this->end_date, 'current_period');
        $data['engagement_rate_previous'] = $this->totalFromEngagementRate($keyword, $sources, $this->start_date_previous, $this->end_date_previous, 'previous_period');
        
        return parent::handleRespond($data);
    }

    public function PeriodOverPeriodGroup($keywords, $sources)
    {

        //$sourceIds = $sources->pluck('id')->all();
        $keywordIds = $keywords->pluck('id')->all();
        $sources = $sources->where('status', 1);
        $channel_message_current = $this->total_message_by_source($keywordIds, $this->start_date, $this->end_date);
        $channel_message_previous = $this->total_message_by_source($keywordIds, $this->start_date_previous, $this->end_date_previous);
        $data = array();
        foreach ($sources as $item) {
            $message_current = 1;
            $message_previous = 1;
            foreach ($channel_message_current as $current) {
                if ($current->source_id == $item->id) {
                    $message_current = $current->total_messages;
                    break;
                }
            }
            foreach ($channel_message_previous as $current) {
                if ($current->source_id == $item->id) {
                    $message_previous = $current->total_messages;
                    break;
                }
            }

            $comparison = $message_current - $message_previous;
            $percentage = ($message_current - $message_previous) / ($message_previous === 0 ? 1 : $message_previous) * 100;

            $data[$item->name] = [
                "comparison_value" => $this->point_two_digits($comparison, 0),
                "percentage" => $this->point_two_digits($percentage, 0),
                "type" => ($comparison >= 0 ? "plus" : "minus"),
                'source_image' => $item->image,
            ];
        }
        return $data;
    }

    // private function total_message_by_source($keywordIds, $start_date, $end_date)
    // {

    //     $result = DB::table('messages');
    //     if ($this->source_id) {
    //         $result->where('source_id', $this->source_id);
    //     }

    //     $result->whereIn('keyword_id', $keywordIds)
    //         ->whereBetween('messages.created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
    //         ->groupBy('source_id')
    //         ->select('source_id', DB::raw('COUNT(*) as total_messages'));

    //     return $result->get();
    // }

    private function total_message_by_source($keywordIds, $start_date, $end_date)
    {
        $result = DB::table('messages');

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $result->whereIn('source_id', $this->source_id);
            } else {
                $result->where('source_id', $this->source_id);
            }
        }

        $result->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->groupBy('source_id')
            ->select('source_id', DB::raw('COUNT(*) as total_messages'));

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $result->whereIn('source_id', $this->source_id);
            } else {
                $result->where('source_id', $this->source_id);
            }
        }

        return $result->get();
    }

    private function totalFromEngagementRate($keywords, $sources, $start_date, $end_date, $value_name)
    {
        $labels = parent::listSource();
        $keywordIds = $keywords->pluck('id')->all();
        $engagement = DB::table('messages')
            ->select([
                'messages.source_id as source_id',
                DB::raw('SUM(tbl_messages.number_of_comments + tbl_messages.number_of_shares + tbl_messages.number_of_reactions + tbl_messages.number_of_views ) as engagement_count')
            ])
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        // if ($this->source_id) {
        //     $engagement->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $engagement->whereIn('source_id', $this->source_id);
            } else {
                $engagement->where('source_id', $this->source_id);
            }
        }        

        foreach ($sources as $source) {
            if (!isset($data[$source->id])) {
                $data[$source->id] = [
                    "source_id" => $source->id,
                    "source_name" => $source->name,
                    "value" => []
                ];
            }
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $engagement->whereIn('source_id', $source_ids);
        }

        $engagementResults = $engagement->groupBy('messages.source_id')->get();

        $data = [
            'labels' => $labels['labels'],
            'value' => [$value_name => ['data' => array_fill(0, count($labels['labels']), 0)]]
        ];

        foreach ($engagementResults as $item) {
            $source_name = self::matchSourceName($sources, $item->source_id);
            $index_label = array_search($source_name, $labels['labels']);

            $data['value'][$value_name]['data'][$index_label] = $item->engagement_count;
        }

        return $data;
    }

    public function sentimentBy(Request $request)
    {
        $sources = self::getAllSource();
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);

        $keywordIds = $keywords->pluck('id')->all();
        $data = null;

        $data['sentiment_score'] = $this->totalFromMessageResultSemetic($keywordIds, $this->start_date, $this->end_date, "current period", "current_period");
        $data['sentiment_score_previous'] = $this->totalFromMessageResultSemetic($keywordIds, $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");
        $data['channel_by_sentiment'] = $this->ChannelBySentiment2Group($keywordIds, $sources, $this->start_date, $this->end_date);
        $data['sentiment_by_level'] = $this->SentimentLevelGroup($keywordIds, $sources, $this->start_date, $this->end_date);

        return parent::handleRespond($data);
    }


    private function ChannelBySentiment2Group($keywordIds, $sources, $start_date, $end_date)
    {

        $channel_message_all = $this->total_message_by_source($keywordIds, $start_date, $end_date);

        $data = [];
        $totalValue = 0;

        foreach ($channel_message_all as $item) {
            $totalValue += $item->total_messages;
            $source = self::matchSource($sources, $item->source_id);
            if ($source != null) {
                $data[] = [
                    'keyword_name' => $source->name,
                    'total_value' => $item->total_messages,
                    'source_color' => $source->color ?? null
                ];
            }
        }
        $data = array_merge([['keyword_name' => 'all', 'total_value' => $totalValue]], $data);
        return $data;
    }

    public function SentimentLevelGroup($keywordIds, $sources, $start_date, $end_date)
    {
        // if ($this->source_id !== 'all') {
        //     $sourcesIds = $this->source_id;
        // }

        // if ($this->source_id == "") {
        //     $sourcesIds = $sources->pluck('id')->implode(',');
        // }

        $sourceIdsInput = is_array($this->source_id) ? $this->source_id : explode(',', $this->source_id);

        if ($this->source_id === 'all' || empty($this->source_id)) {
            $sourcesIds = $sources->pluck('id')->toArray();
        } else {
            $sourcesIds = $sourceIdsInput;
        }

        $sourcesIdsString = implode(',', $sourcesIds);
        $keywordIdsString = implode(',', $keywordIds);

        $query = "SELECT
            m.source_id  as source_id,

            SUM(CASE WHEN mr.classification_sentiment_id = 1 THEN 1 ELSE 0 END) AS positive,
            SUM(CASE WHEN mr.classification_sentiment_id = 2 THEN 1 ELSE 0 END) AS negative,
            SUM(CASE WHEN mr.classification_sentiment_id = 3 THEN 1 ELSE 0 END) AS neutral
        FROM
            tbl_messages m
            LEFT JOIN tbl_message_results_2 mr ON m.id = mr.message_id
        WHERE m.source_id IN ($sourcesIdsString)
            AND m.keyword_id IN ($keywordIdsString)
            AND m.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        GROUP BY m.source_id;";

        $data = DB::select($query);

        $items = array();
        $result = array();
        $totals = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'total' => 0,
            'source_id' => 0,
            'source_name' => "All"
        ];

        foreach ($data as $item) {
            $positive = intval($item->positive);
            $negative = intval($item->negative);
            $neutral = intval($item->neutral);
            $total = $positive + $negative + $neutral;
            $i['source_id'] = $item->source_id;
            $i['source_name'] = self::matchSourceName($sources, $item->source_id);
            $i['total'] = $total;
            $i['positive_total'] = $positive;
            $i['negative_total'] = $negative;
            $i['neutral_total'] = $neutral;
            // $i['positive'] = self::point_two_digits(($positive / $total) * 100);
            // $i['negative'] = self::point_two_digits(($negative / $total) * 100);
            // $i['neutral'] = self::point_two_digits(($neutral / $total) * 100);

            $i['positive'] = $total ? self::point_two_digits(($positive / $total) * 100) : 0;
            $i['negative'] = $total ? self::point_two_digits(($negative / $total) * 100) : 0;
            $i['neutral'] = $total ? self::point_two_digits(($neutral / $total) * 100) : 0;

            $totals['positive'] += intval($item->positive);
            $totals['negative'] += intval($item->negative);
            $totals['neutral'] += intval($item->neutral);
            $totals['total'] += $total;
            $items[] = $i;
        }
        $positive = $totals['total'] ? self::point_two_digits(($totals['positive'] / $totals['total']) * 100) : 0;
        $negative = $totals['total'] ? self::point_two_digits(($totals['negative'] / $totals['total']) * 100) : 0;
        $neutral = $totals['total'] ? self::point_two_digits(($totals['neutral'] / $totals['total']) * 100) : 0;
        $totals['positive'] = $positive;
        $totals['negative'] = $negative;
        $totals['neutral'] = $neutral;
        $result[] = $totals;
        foreach ($items as $item) {
            $result[] = $item;
        }
        return $result;
    }

    private function totalFromMessageResultSemetic($keywordIds, $start_date, $end_date, $keyword_name, $value_name)
    {
        $labels = parent::listSource();
        $sources = self::getAllSource();

        $data = [
            'labels' => $labels['labels'],
            'value' => [$value_name => ['data' => array_fill(0, count($labels['labels']), 0)]]
        ];

        $engagement = DB::table('messages')
            ->whereIn('messages.keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->select([
                'messages.source_id',
                /*'sources.name as source_name',*/
                DB::raw('COUNT(*) as count')
            ])->groupBy('messages.source_id');

        // if ($this->source_id) {
        //     $engagement->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $engagement->whereIn('source_id', $this->source_id);
            } else {
                $engagement->where('source_id', $this->source_id);
            }
        }
        
        $engagement = $engagement->pluck('count', 'source_id');

        $data['value'][$value_name]['keyword_name'] = $keyword_name;
        foreach ($engagement as $source_id => $count) {
            $source_name = self::matchSourceName($sources, $source_id);
            $index_label = array_search($source_name, $data['labels']);
            $data['value'][$value_name]['data'][$index_label] = $count;
        }
        return $data;
    }


    private function countChannelTable($keywordIds, $start_date, $end_date, $source_id = null)
    {
        $data = DB::table('messages')
            ->select([
                'messages.source_id as source_id',
                'messages.keyword_id as keyword_id',
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.reference_message_id as reference_message_id',
                'messages.author as author',
            ])
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        if ($source_id) {
            $data->where('source_id', $source_id);
        }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $data->whereIn('source_id', $this->source_id);
            } else {
                $data->where('source_id', $this->source_id);
            }
        }

        // if ($this->source_id) {
        //     $data->where('source_id', $this->source_id);
        // }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        return $data->count();
    }

    private function source_name($source_id_id)
    {
        $source_id = Sources::where('id', $source_id_id)->first();
        return $source_id->name;
    }

    private function raw_messageTotal($keywordIds, $start_date, $end_date)
    {
        $data = DB::table('messages')
            ->select([
                'messages.id as id',
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.reference_message_id as reference_message_id',
                DB::raw('count(id) as total_messages'),
            ])
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

    private function raw_message($keywordIds, $start_date, $end_date)
    {
        $data = DB::table('messages')
            ->select([
                'messages.id as id',
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.reference_message_id as reference_message_id',
            ])
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

    private function raw_message2($keywordIds, $start_date, $end_date)
    {
        $data = DB::table('messages')
            ->select([
                'messages.id as id',
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.reference_message_id as reference_message_id',
                'message_results_2.media_type',
                'message_results_2.classification_sentiment_id',
                'message_results_2.classification_type_id',
                'message_results_2.classification_level_id',
            ])
            ->whereIn('keyword_id', $keywordIds)
            ->join('message_results_2', 'messages.id', '=', 'message_results_2.message_id')
            ->where('message_results_2.media_type', 1)
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
}
