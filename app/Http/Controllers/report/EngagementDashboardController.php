<?php

namespace App\Http\Controllers\report;

use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\DailyMessage;
use App\Models\Sources;
use App\Models\Classification;
use App\Models\Keyword;

class EngagementDashboardController extends Controller
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

    public function EngagementTrans(Request $request)
    {

        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();

        $data = [
            "engagement" => $this->engagement($keywordIds),
            "prcentage_of_engagement_current" => $this->percentageOfEngagement($keywordIds, $this->start_date, $this->end_date, $keyword),
            "prcentage_of_engagement_previous" => $this->percentageOfEngagement($keywordIds, $this->start_date_previous, $this->end_date_previous, $keyword)
        ];

        return parent::handleRespond($data);
    }


    public function EngagementBy(Request $request)
    {

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $sources = self::getAllSource();
        $raw = $this->raw_message($keywords, $this->start_date, $this->end_date);
        $result = $raw->get();
        return parent::handleRespond([
            "EngagementByDay" => $this->EngagementByDay($result, $keywords, true),
            "EngagementByTime" => $this->EngagementByTime($result, $keywords, true),
            "EngagementByDevice" => $this->EngagementByDevice($result, $keywords, $sources, true),
            "EngagementByAccount" => $this->EngagementByAccount($result, $keywords, true),
            "EngagementChannel" => $this->EngagementChannel($result, $keywords, $sources, true),
            "keywordByEngagementType" => $this->keywordByEngagementType($result, $keywords, $sources, true),
        ]);
    }

    public function EngagementByDay($items, $keywords, $only_data = false)
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

            if (!isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                    /*'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];
            }
            $data['value'][$item->keyword_id]['data'][$index_label] += $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions + $item->number_of_views;
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementByTime($items, $keywords, $only_data = false)
    {

        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        //$items = $raw->get();

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
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                    /*'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0]
                ];

            }
            $data['value'][$item->keyword_id]['data'][$index_label] += $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions + $item->number_of_views;
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);

    }

    public function EngagementByDevice($items, $keywords, $sources, $only_data = false)
    {
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];/*

        // $items = $raw->get();
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();
        $totalKeyword = DB::table('messages')
            ->select([
                DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as total_engagement'),
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.device AS device',
                'messages.message_datetime as date_m',
                'messages.source_id as source_id',
                'sources.name as source_name',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $totalKeyword->where('source_id', $this->source_id);
        }

        $totalKeyword = $totalKeyword->groupBy('keyword_id', 'device')->get();*/

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

                $count_all = $item->total_engagement;

                if (!isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        /*'keyword_name' => $item->keyword_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,*/
                        'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                        'source_id' => $item->source_id,
                        'source_name' => self::matchSourceName($sources, $item->source_id),
                        'data' => [0, 0, 0]
                    ];

                }
                $data['value'][$item->keyword_id]['data'][$index_label] += $count_all;
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementByAccount($result, $keywords, $only_data = false)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        /*$infulencers = $this->raw_message($this->campaign_id, $this->start_date, $this->end_date);
        $infulencers = $infulencers->where('reference_message_id', '')->get();*/

        foreach ($result as $infulencer) {

            // if ($infulencer) 
            if ($infulencer && empty($infulencer->reference_message_id)){

                if (!isset($data['value'][$infulencer->keyword_id])) {
                    $data['value'][$infulencer->keyword_id] = [
                        'id' => $infulencer->keyword_id,
                        'keyword_name' => self::matchKeywordName($keywords, $infulencer->keyword_id),
                        /*'campaign_id' => $infulencer->campaign_id,
                        'campaign_name' => $infulencer->campaign_name,*/
                        'data' => [0, 0]
                    ];

                }
                $data['value'][$infulencer->keyword_id]['data'][0] += $infulencer->number_of_comments + $infulencer->number_of_shares + $infulencer->number_of_reactions + $infulencer->number_of_views;
            }
        }

        /*$followers = $this->raw_message($this->campaign_id, $this->start_date, $this->end_date);
        $followers = $followers->where('reference_message_id', '!=', '')->get();*/
        foreach ($result as $follower) {
            if ($follower->reference_message_id != null && $follower->reference_message_id != '') {
                if (!isset($data['value'][$follower->keyword_id])) {
                    $data['value'][$follower->keyword_id] = [
                        'id' => $follower->keyword_id,
                        'keyword_name' => self::matchKeywordName($keywords, $follower->keyword_id),
                        /*'campaign_id' => $follower->campaign_id,
                        'campaign_name' => $follower->campaign_name,*/
                        'data' => [0, 0]
                    ];

                }
                $data['value'][$follower->keyword_id]['data'][1] += $follower->total_engagement;
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

    public function EngagementChannel($result, $keywords, $sources, $only_data = false)
    {
        $data = parent::listSource();
        /*    $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();
        $totalKeyword = DB::table('messages')
            ->select([
                DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as total_engagement'),
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.device AS device',
                'messages.message_datetime as date_m',
                'messages.source_id as source_id',
                'sources.name as source_name',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $totalKeyword->where('source_id', $this->source_id);
        }

        $totalKeyword = $totalKeyword->groupBy('keyword_id', 'source_id')->get();*/

        foreach ($result as $item) {
            $sourceName = self::matchSourceName($sources, $item->source_id);
            $index_label = array_search($sourceName, $data['labels']);

            if (!isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'name' => self::matchKeywordName($keywords, $item->keyword_id),
                    'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
                    /*'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,*/
                ];

                for ($i = 0; $i <= count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][$i] = 0;
                }

            }
            $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_engagement;
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function keywordByEngagementType($items, $keywords, $sources, $only_data = false)
    {

        $data['labels'] = ["Share", "Comments", "Reaction", "Views"];
        // $items = $raw->get();
        /*
                $keyword = Keyword::where('campaign_id', $this->campaign_id);

                if ($this->keyword_id) {
                    $keyword = $keyword->whereIn('id', $this->keyword_id);
                }

                $keyword = $keyword->get();

                $keywordIds = $keyword->pluck('id')->all();
                $totalKeyword = DB::table('messages')
                    ->select([
                        DB::raw('SUM(number_of_shares) as number_of_shares'),
                        DB::raw('SUM(number_of_comments) as number_of_comments'),
                        DB::raw('SUM(number_of_reactions) as number_of_reactions'),
                        DB::raw('SUM(number_of_views) as number_of_views'),
                        'messages.keyword_id as keyword_id',
                        'keywords.name as keyword_name',
                        'keywords.campaign_id AS campaign_id',
                        'campaigns.name AS campaign_name',
                        'messages.device AS device',
                        'messages.message_datetime as date_m',
                        'messages.source_id as source_id',
                        'sources.name as source_name',
                    ])
                    ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
                    ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
                    ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
                    ->whereIn('keyword_id', $keywordIds)
                    ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

                if ($this->source_id) {
                    $totalKeyword->where('source_id', $this->source_id);
                }

                $totalKeyword = $totalKeyword->groupBy('keyword_id')->get();*/

        // foreach ($items as $item) {
        //     // if (isset($data['value'][$item->keyword_id])) {
        //     //     $data['value'][$item->keyword_id]['data'][0] += $item->number_of_shares;
        //     //     $data['value'][$item->keyword_id]['data'][1] += $item->number_of_comments;
        //     //     $data['value'][$item->keyword_id]['data'][2] += $item->number_of_reactions;
        //     //     $data['value'][$item->keyword_id]['data'][3] += $item->number_of_views;
        //     // } else {
        //     $data['value'][$item->keyword_id] = [
        //         'id' => $item->keyword_id,
        //         'name' => self::matchKeywordName($keywords, $item->keyword_id),
        //         'keyword_name' => self::matchKeywordName($keywords, $item->keyword_id),
        //         /*'campaign_id' => $item->campaign_id,
        //         'campaign_name' => $item->campaign_name,*/
        //     ];

        //     for ($i = 0; $i < count($data['labels']); $i++) {
        //         $data['value'][$item->keyword_id]['data'][$i] = 0;
        //     }

        //     $data['value'][$item->keyword_id]['data'][0] += $item->number_of_shares;
        //     $data['value'][$item->keyword_id]['data'][1] += $item->number_of_comments;
        //     $data['value'][$item->keyword_id]['data'][2] += $item->number_of_reactions;
        //     $data['value'][$item->keyword_id]['data'][3] += $item->number_of_views;
        //     // }
        // }

        foreach ($items as $item) {
            $keywordId = $item->keyword_id;
        
            if (!isset($data['value'][$keywordId])) {
                $data['value'][$keywordId] = [
                    'id' => $keywordId,
                    'name' => self::matchKeywordName($keywords, $keywordId),
                    'keyword_name' => self::matchKeywordName($keywords, $keywordId),
                    'data' => [0, 0, 0, 0],
                ];
            }
        
            $data['value'][$keywordId]['data'][0] += (int) $item->number_of_shares;
            $data['value'][$keywordId]['data'][1] += (int) $item->number_of_comments;
            $data['value'][$keywordId]['data'][2] += (int) $item->number_of_reactions;
            $data['value'][$keywordId]['data'][3] += (int) $item->number_of_views;
        }
        

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }


        return parent::handleRespond($data);
    }


    public function EngagementTypeBy(Request $request)
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $raw = $this->raw_message($keywords, $this->start_date, $this->end_date)->get();
        $raw_previous = $this->raw_message($keywords, $this->start_date_previous, $this->end_date_previous)->get();
        // $raw_previous = $this->raw_message($this->campaign_id, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond([
            "EngagementType" => $this->EngagementType($raw, $raw_previous, true),
            "EngagementByDayKey" => $this->EngagementByDayKey($raw, true),
            "EngagementByTimeKey" => $this->EngagementByTimeKey($raw, true),
            "EngagementByDeviceKey" => $this->EngagementByDeviceKey($raw, true),
            "EngagementChannelKey" => $this->EngagementChannelKey($raw, true),
            "EngagementByAccountKey" => $this->EngagementByAccountKey($raw, true),
        ]);
    }


    //todo maybe percentage is wrong
    public function EngagementType($items, $items_previous, $only_data = false)
    {
        $data = null;

        $sources = self::getAllSource();

        // find percentage of engagement

        $total_engaement = 0;
        $total_engaement_previous = 0;
        $percentages_share_current = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y'),
        ];

        $percentages_comment_current = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y'),
        ];

        $percentages_reactions_current = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y'),
        ];

        $percentages_views_current = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y'),
        ];

        $percentages_share_previous = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y'),
        ];

        $percentages_comment_previous = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y'),
        ];

        $percentages_reactions_previous = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y'),
        ];

        $percentages_views_previous = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y'),
        ];

        foreach ($items_previous as $items_previou) {
            $total_engaement_previous += $items_previou->number_of_shares + $items_previou->number_of_comments + $items_previou->number_of_reactions + $items_previou->number_of_views;
            $percentages_share_previous['total'] += $items_previou->number_of_shares;
            $percentages_comment_previous['total'] += $items_previou->number_of_comments;
            $percentages_reactions_previous['total'] += $items_previou->number_of_reactions;
            $percentages_views_previous['total'] += $items_previou->number_of_views;
        }

        foreach ($items as $item) {
            $total_engaement += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
            $percentages_share_current['total'] += $item->number_of_shares;
            $percentages_comment_current['total'] += $item->number_of_comments;
            $percentages_reactions_current['total'] += $item->number_of_reactions;
            $percentages_views_current['total'] += $item->number_of_views;
        }


        foreach ($items as $item) {

            $date_m = \Illuminate\Support\Carbon::parse($item->date_m)->format('Y-m-d');

            $shared = [
                "source_id" => $item->source_id,
                "source_name" => self::matchSourceName($sources, $item->source_id),
                "date_m" => $date_m,
                "total_at_date" => $item->number_of_shares,
            ];

            $comment = [
                "source_id" => $item->source_id,
                "source_name" => self::matchSourceName($sources, $item->source_id),
                "date_m" => $date_m,
                "total_at_date" => $item->number_of_comments,
            ];

            $reactions = [
                "source_id" => $item->source_id,
                "source_name" => self::matchSourceName($sources, $item->source_id),
                "date_m" => $date_m,
                "total_at_date" => $item->number_of_reactions,
            ];

            $views = [
                "source_id" => $item->source_id,
                "source_name" => self::matchSourceName($sources, $item->source_id),
                "date_m" => $date_m,
                "total_at_date" => $item->number_of_views,
            ];


            if (isset($data['engagement'][1]) || isset($data['engagement'][2]) || isset($data['engagement'][3]) || isset($data['engagement'][4])) {
                // if ($item->number_of_shares > 0) {
                //     if (isset($data['engagement'][1]['value'][$date_m])) {
                //         $data['engagement'][1]['value'][$date_m]['total_at_date'] += $item->number_of_shares;
                //     } else {
                //         $data['engagement'][1]['value'][$date_m] = $shared;
                //     }

                // }

                // if ($item->number_of_comments > 0) {
                //     if (isset($data['engagement'][2]['value'][$date_m])) {
                //         $data['engagement'][2]['value'][$date_m]['total_at_date'] += $item->number_of_comments;
                //     } else {
                //         $data['engagement'][2]['value'][$date_m] = $comment;
                //     }

                // }

                // if ($item->number_of_reactions > 0) {
                //     if (isset($data['engagement'][3]['value'][$date_m])) {
                //         $data['engagement'][3]['value'][$date_m]['total_at_date'] += $item->number_of_reactions;
                //     } else {
                //         $data['engagement'][3]['value'][$date_m] = $reactions;
                //     }

                // }

                // if ($item->number_of_views > 0) {
                //     if (isset($data['engagement'][4]['value'][$date_m])) {
                //         $data['engagement'][4]['value'][$date_m]['total_at_date'] += $item->number_of_views;
                //     } else {
                //         $data['engagement'][4]['value'][$date_m] = $views;
                //     }

                // }
                // Share
                if (!isset($data['engagement'][1]['value'][$date_m])) {
                    $data['engagement'][1]['value'][$date_m] = $shared;
                } else {
                    $data['engagement'][1]['value'][$date_m]['total_at_date'] += $item->number_of_shares;
                }

                // Comment
                if (!isset($data['engagement'][2]['value'][$date_m])) {
                    $data['engagement'][2]['value'][$date_m] = $comment;
                } else {
                    $data['engagement'][2]['value'][$date_m]['total_at_date'] += $item->number_of_comments;
                }

                // Reactions
                if (!isset($data['engagement'][3]['value'][$date_m])) {
                    $data['engagement'][3]['value'][$date_m] = $reactions;
                } else {
                    $data['engagement'][3]['value'][$date_m]['total_at_date'] += $item->number_of_reactions;
                }

                // Views
                if (!isset($data['engagement'][4]['value'][$date_m])) {
                    $data['engagement'][4]['value'][$date_m] = $views;
                } else {
                    $data['engagement'][4]['value'][$date_m]['total_at_date'] += $item->number_of_views;
                }

                // $data['engagement'][2]['value'][$date_m][] = $comment;
                // $data['engagement'][3]['value'][$date_m][] = $reactions;
            } else {
                $data['engagement'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['engagement'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['engagement'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['engagement'][4] = [
                    "id" => 4,
                    "name" => 'Views',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                // engagement

                // $data['engagement'][1]['value'][] = $shared;
                // $data['engagement'][2]['value'][] = $comment;
                // $data['engagement'][3]['value'][] = $reactions;
                // $data['engagement'][4]['value'][] = $views;

                // prcentage_of_engagement_current

                $data['prcentage_of_engagement_current'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['prcentage_of_engagement_current'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['prcentage_of_engagement_current'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['prcentage_of_engagement_current'][4] = [
                    "id" => 4,
                    "name" => 'Views',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                    // prcentage_of_engagement_previous
                $data['prcentage_of_engagement_previous'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['prcentage_of_engagement_previous'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['prcentage_of_engagement_previous'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];

                $data['prcentage_of_engagement_previous'][4] = [
                    "id" => 4,
                    "name" => 'Views',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "value" => []
                ];
            }
        }


        $data['prcentage_of_engagement_current'][1]['value'] = [
            "percentage" => $total_engaement ? self::point_two_digits(($percentages_share_current['total'] / $total_engaement) * 100) : 0,
            "date" => $percentages_share_current['date'],
            'total' => self::point_two_digits($total_engaement, 0)
        ];

        $data['prcentage_of_engagement_current'][2]['value'] = [
            "percentage" => $total_engaement ? self::point_two_digits(($percentages_comment_current['total'] / $total_engaement) * 100) : 0,
            "date" => $percentages_share_current['date'],
            'total' => self::point_two_digits($total_engaement, 0)
        ];
        $data['prcentage_of_engagement_current'][3]['value'] = [
            "percentage" => $total_engaement ? self::point_two_digits(($percentages_reactions_current['total'] / $total_engaement) * 100) : 0,
            "date" => $percentages_share_current['date'],
            'total' => self::point_two_digits($total_engaement, 0)
        ];
        $data['prcentage_of_engagement_current'][4]['value'] = [
            "percentage" => $total_engaement ? self::point_two_digits(($percentages_views_current['total'] / $total_engaement) * 100) : 0,
            "date" => $percentages_share_current['date'],
            'total' => self::point_two_digits($total_engaement, 0)
        ];


        $data['prcentage_of_engagement_previous'][1]['value'] = [
            "percentage" => $total_engaement_previous ? self::point_two_digits(($percentages_share_previous['total'] / $total_engaement_previous) * 100) : 0,
            "date" => $percentages_share_previous['date'],
            'total' => self::point_two_digits($total_engaement_previous, 0)
        ];

        $data['prcentage_of_engagement_previous'][2]['value'] = [
            "percentage" => $total_engaement_previous ? self::point_two_digits(($percentages_comment_previous['total'] / $total_engaement_previous) * 100) : 0,
            "date" => $percentages_share_previous['date'],
            'total' => self::point_two_digits($total_engaement_previous, 0)
        ];
        $data['prcentage_of_engagement_previous'][3]['value'] = [
            "percentage" => $total_engaement_previous ? self::point_two_digits(($percentages_reactions_previous['total'] / $total_engaement_previous) * 100) : 0,
            "date" => $percentages_share_previous['date'],
            'total' => self::point_two_digits($total_engaement_previous, 0)
        ];
        $data['prcentage_of_engagement_previous'][4]['value'] = [
            "percentage" => $total_engaement_previous ? self::point_two_digits(($percentages_views_previous['total'] / $total_engaement_previous) * 100) : 0,
            "date" => $percentages_share_previous['date'],
            'total' => self::point_two_digits($total_engaement_previous, 0)
        ];


        if (isset($data['engagement'])) {
            foreach ($data['engagement'] as $index => $engagement) {
                $data['engagement'][$index]['value'] = array_values($data['engagement'][$index]['value']);

            }

            $data['engagement'] = array_values($data['engagement']);
        }
        // $data['engagement'] = isset($data['engagement']) ? array_values($data['engagement']['value']) : null;
        $data['prcentage_of_engagement_previous'] = isset($data['prcentage_of_engagement_previous']) ? array_values($data['prcentage_of_engagement_previous']) : null;
        $data['prcentage_of_engagement_current'] = isset($data['prcentage_of_engagement_current']) ? array_values($data['prcentage_of_engagement_current']) : null;
//

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementByDayKey($items, $only_data = false)
    {
        $data = null;
        //$items = $raw->get();
        $total_engaement = 0;

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
            if (!isset($data['value'][0])) {
                $data['value'][0] = [
                    'id' => 1,
                    'keyword_name' => 'Share',
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][1] = [
                    'id' => 2,
                    'keyword_name' => 'Comment',
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];


                $data['value'][2] = [
                    'id' => 3,
                    'keyword_name' => 'reactions',
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][3] = [
                    'id' => 4,
                    'keyword_name' => 'Views',
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

            }
            $data['value'][0]['data'][$index_label] += $item->number_of_shares;
            $data['value'][1]['data'][$index_label] += $item->number_of_comments;
            $data['value'][2]['data'][$index_label] += $item->number_of_reactions;
            $data['value'][3]['data'][$index_label] += $item->number_of_views;
        }


//        $table =  'total_engagement_of_source_d_m_y_h_i_s';
//        $period = $request->period;
//
//        $start_date = $this->date_carbon($request->start_date) ?? null;
//        $end_date = $this->date_carbon($request->end_date) ?? null;
//
//        $campaign_id = $request->campaign_id;
//


        if ($only_data) {
            return $data;
        }
        return parent::handleRespond($data);
    }

    public function EngagementByTimeKey($items, $only_data = false)
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


            if (isset($data['value'][1])) {

                $data['value'][1]['data'][$index_label] += $item->number_of_shares;
                $data['value'][2]['data'][$index_label] += $item->number_of_comments;
                $data['value'][3]['data'][$index_label] += $item->number_of_reactions;
                $data['value'][4]['data'][$index_label] += $item->number_of_views;


            } else {

                $data['value'][1] = [
                    "id" => 1,
                    "keyword_name" => 'Share',
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][3] = [
                    "id" => 3,
                    "keyword_name" => 'Reactions',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][4] = [
                    "id" => 4,
                    "keyword_name" => 'Views',
                    /*"campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    'data' => [0, 0, 0, 0]
                ];
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementByDeviceKey($items, $only_data = false)
    {


        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

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

                if (!isset($data['value'])) {

                    $data['value'][1] = [
                        "id" => 1,
                        "keyword_name" => 'Share',
                        /*"campaign_id" => $item->campaign_id,
                        "campaign_name" => $item->campaign_name,*/
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][2] = [
                        "id" => 2,
                        "keyword_name" => 'Comment',
                        /*"campaign_id" => $this->campaign_id,
                        "campaign_name" => $item->campaign_name,*/
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][3] = [
                        "id" => 3,
                        "keyword_name" => 'Reactions',
                        /*"campaign_id" => $this->campaign_id,
                        "campaign_name" => $item->campaign_name,*/
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][4] = [
                        "id" => 4,
                        "keyword_name" => 'Views',
                        /*"campaign_id" => $this->campaign_id,
                        "campaign_name" => $item->campaign_name,*/
                        'data' => [0, 0, 0]
                    ];


                }
                $data['value'][1]['data'][$index_label] += $item->number_of_shares;
                $data['value'][2]['data'][$index_label] += $item->number_of_comments;
                $data['value'][3]['data'][$index_label] += $item->number_of_reactions;
                $data['value'][4]['data'][$index_label] += $item->number_of_views;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementByAccountKey($infulencers, $only_data = false)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        /*$infulencers = $this->raw_message($this->campaign_id, $this->start_date, $this->end_date);
        $infulencers = $infulencers->where('reference_message_id', '')->get();*/


        foreach ($infulencers as $infulencer) {
            if ($infulencer->reference_message_id == "") {
                if ($infulencer) {
                    if (isset($data['value'][1]['data'][0])) {
                        $data['value'][1]['data'][0] += $infulencer->number_of_shares;
                        $data['value'][2]['data'][0] += $infulencer->number_of_comments;
                        $data['value'][3]['data'][0] += $infulencer->number_of_reactions;
                        $data['value'][4]['data'][0] += $infulencer->number_of_views;
                    } else {
                        $data['value'][1] = [
                            'id' => 1,
                            "keyword_name" => "Share",
                            "data" => [$infulencer->number_of_shares, 0]
                        ];

                        $data['value'][2] = [
                            'id' => 2,
                            "keyword_name" => "Comment",
                            "data" => [$infulencer->number_of_comments, 0]
                        ];

                        $data['value'][3] = [
                            'id' => 3,
                            "keyword_name" => "Reaction",
                            "data" => [$infulencer->number_of_reactions, 0]
                        ];

                        $data['value'][4] = [
                            'id' => 4,
                            "keyword_name" => "Views",
                            "data" => [$infulencer->number_of_views, 0]
                        ];

                    }
                }
            }
        }

        /*        $followers = $this->raw_message($this->campaign_id, $this->start_date, $this->end_date);
                $followers = $followers->where('reference_message_id', '!=', '')->get();*/


        foreach ($infulencers as $follower) {
            if ($follower->reference_message_id != "") {
                if (isset($data['value'][1]['data'][0])) {
                    $data['value'][1]['data'][1] += $follower->number_of_shares;
                    $data['value'][2]['data'][1] += $follower->number_of_comments;
                    $data['value'][3]['data'][1] += $follower->number_of_reactions;
                    $data['value'][4]['data'][1] += $follower->number_of_views;
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

    public function EngagementChannelKey($items, $only_data = false)
    {

        $sources = self::getAllSource();
        $data = $this->listSource();
        //$items = $raw->get();

        foreach ($items as $item) {
            $sourceName = self::matchSourceName($sources, $item->source_id);
            $index_label = array_search($sourceName, $data['labels']);

            if (isset($data['value'][1])) {
                $data['value'][1]['data'][$index_label] += $item->number_of_shares;
                $data['value'][2]['data'][$index_label] += $item->number_of_comments;
                $data['value'][3]['data'][$index_label] += $item->number_of_reactions;
                $data['value'][4]['data'][$index_label] += $item->number_of_views;
            } else {
                $data['value'][1] = [
                    'id' => 1,
                    'name' => "Share",
                ];

                $data['value'][2] = [
                    'id' => 2,
                    'name' => "Comment",
                ];

                $data['value'][3] = [
                    'id' => 3,
                    'name' => "Reaction",
                ];

                $data['value'][4] = [
                    'id' => 4,
                    'name' => "Views",
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {

                    $data['value'][1]['data'][$i] = 0;
                    $data['value'][2]['data'][$i] = 0;
                    $data['value'][3]['data'][$i] = 0;
                    $data['value'][4]['data'][$i] = 0;
                }

                $data['value'][1]['data'][$index_label] = $item->number_of_shares;
                $data['value'][2]['data'][$index_label] = $item->number_of_comments;
                $data['value'][3]['data'][$index_label] = $item->number_of_reactions;
                $data['value'][4]['data'][$index_label] = $item->number_of_views;
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


    public function EngagementComparisonBy(Request $request)
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $raw = $this->raw_message($keywords, $this->start_date, $this->end_date)->get();
        $raw_previous = $this->raw_message($keywords, $this->start_date_previous, $this->end_date_previous)->get();
        $sources = self::getAllSource();
        return parent::handleRespond([
            "EngagementComparison" => $this->EngagementComparison($request, true),
            "EngagementPeriodPlarform" => $this->EngagementPeriodPlarform($raw, $raw_previous, $sources, true),
            "EngagementPeriodSentiment" => $this->EngagementPeriodSentiment($request, true),
            "EngagementTypeComparison" => $this->EngagementTypeComparison($raw, $raw_previous,$keywords,  true),
            "EngagementActionComparison" => $this->EngagementActionComparison($raw,$keywords, true),
        ]);
    }

    public function EngagementComparisonByAccount(Request $request)
    {
        $keyword = self::findKeywords($this->campaign_id, $this->keyword_id);
        $raw = $this->raw_message($keyword, $this->start_date, $this->end_date);
        $raw_previous = $this->raw_message($keyword, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond([
            "EngagementByInfulencer" => $this->EngagementByInfulencer($raw, $raw_previous, $request, true),
        ]);
    }


    public function EngagementComparison(Request $request, $only_data = false)
    {

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $data = null;
        $raw_current = $this->raw_message($keywords, $this->start_date, $this->end_date);
        $raw_previous = $this->raw_message($keywords, $this->start_date_previous, $this->end_date_previous);

        $totalEngagement_current = $raw_current->select([
            DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as totalEngagement_current'),
            DB::raw('SUM(number_of_shares) as total_share_current'),
            DB::raw('SUM(number_of_comments) as total_comment_current'),
            DB::raw('SUM(number_of_reactions) as total_reactions_current'),
            DB::raw('SUM(number_of_views) as total_views_current'),
        ]);
        $totalEngagement_current = $totalEngagement_current->first();

        $totalEngagement_previous = $raw_previous->select([
            DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as totalEngagement_previous'),
            DB::raw('SUM(number_of_shares) as total_share_previous'),
            DB::raw('SUM(number_of_comments) as total_comment_previous'),
            DB::raw('SUM(number_of_reactions) as total_reactions_previous'),
            DB::raw('SUM(number_of_views) as total_views_previous'),
        ]);
        $totalEngagement_previous = $totalEngagement_previous->first();

        $total_share_current = $totalEngagement_current->total_share_current;
        $total_share_previous = $totalEngagement_previous->total_share_previous;

        $total_comment_current = $totalEngagement_current->total_comment_current;
        $total_comment_previous = $totalEngagement_previous->total_comment_previous;

        $total_reactions_current = $totalEngagement_current->total_reactions_current;
        $total_reactions_previous = $totalEngagement_previous->total_reactions_previous;

        $total_views_current = $totalEngagement_current->total_views_current;
        $total_views_previous = $totalEngagement_previous->total_views_previous;

        $totalEngagement_current = $totalEngagement_current->totalEngagement_current;
        $totalEngagement_previous = $totalEngagement_previous->totalEngagement_previous;

        $data['totalEngagement'] = [
            "totalValue" => $this->custom_number_format((int)$totalEngagement_current),
            "comparison" => (float)parent::point_two_digits($totalEngagement_current - $totalEngagement_previous !== 0 ? $this->overPeriodComparison($totalEngagement_current, $totalEngagement_previous) : 0),
            "type" => $totalEngagement_current - $totalEngagement_previous > 0 ? "plus" : "minus",
        ];

        $data['share'] = [
            "totalValue" => $this->custom_number_format((int)$total_share_current),
            // "comparison" => $total_share_previous !== 0 ? (float)parent::point_two_digits($total_share_current - $total_share_previous !== 0 ? (($total_share_current - $total_share_previous) / $total_share_previous * 100) : 0) : 0,
            "comparison" => $total_share_previous != 0 ? (float)parent::point_two_digits(($total_share_current - $total_share_previous) / $total_share_previous * 100) : 0,
            "type" => $total_share_current - $total_share_previous > 0 ? "plus" : "minus",
        ];

        $data['comment'] = [
            "totalValue" => $this->custom_number_format((int)$total_comment_current),
            // "comparison" => $total_comment_previous !== 0 ? (float)parent::point_two_digits($total_comment_current - $total_comment_previous !== 0 ? (($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0) : 0,
            "comparison" => $total_comment_previous != 0 ? (float)parent::point_two_digits(($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0,
            "type" => $total_comment_current - $total_comment_previous > 0 ? "plus" : "minus",
        ];

        $data['reaction'] = [
            "totalValue" => $this->custom_number_format((int)$total_reactions_current),
            // "comparison" => $total_reactions_previous ? (float)parent::point_two_digits(($total_reactions_current - $total_reactions_previous) / $total_reactions_previous * 100) : 0,
            "comparison" => $total_reactions_previous != 0 ? (float)parent::point_two_digits(($total_reactions_current - $total_reactions_previous) / $total_reactions_previous * 100) : 0,
            "type" => $total_reactions_current - $total_reactions_previous > 0 ? "plus" : "minus",
        ];

        $data['views'] = [
            "totalValue" => $this->custom_number_format((int)$total_views_current),
            // "comparison" => $total_views_previous !== 0 ? (float)parent::point_two_digits($total_views_current - $total_views_previous !== 0 ? (($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0) : 0,
            "comparison" => $total_views_previous != 0 ? (float)parent::point_two_digits(($total_views_current - $total_views_previous) / $total_views_previous * 100) : 0,
            "type" => $total_views_current - $total_views_previous > 0 ? "plus" : "minus",
        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementPeriodPlarform($raw_current, $items_previous,$sources, $only_data = false)
    {

        $data = $this->listSource();

        $current_share = [];
        $previous_share = [];

        $current_comment = [];
        $previous_comment = [];

        $current_reaction = [];
        $previous_reaction = [];

        $current_views = [];
        $previous_views = [];

        $debug = null;

        foreach ($raw_current as $item) {
            $sourceName = self::matchSourceName($sources, $item->source_id);
            $index_label = array_search($sourceName, $data['labels']);

            if (isset($data['value'][2])) {
                $data['value'][2]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views);;
                $current_share[$index_label] += $item->number_of_shares;
                $current_comment[$index_label] += $item->number_of_comments;
                $current_reaction[$index_label] += $item->number_of_reactions;
                $current_views[$index_label] += $item->number_of_views;

            } else {
                $data['value'][1] = [
                    'id' => 1,
                    'keyword_name' => "Previous",
                ];

                $data['value'][2] = [
                    'id' => 2,
                    'keyword_name' => "Current",
                ];

                for ($i = 0; $i < count($data['labels']); $i++) { // <=
                    $data['value'][1]['data'][$i] = 0;
                    $data['value'][2]['data'][$i] = 0;

                    $data['share'][$i] = 0;
                    $data['comment'][$i] = 0;
                    $data['reaction'][$i] = 0;
                    $data['views'][$i] = 0;

                    $current_share[$i] = 0;
                    $current_share[$index_label] = $item->number_of_shares;
                    $current_comment[$i] = 0;
                    $current_comment[$index_label] = $item->number_of_comments;
                    $current_reaction[$i] = 0;
                    $current_reaction[$index_label] = $item->number_of_reactions;
                    $current_views[$i] = 0;
                    $current_views[$index_label] = $item->number_of_views;

                    $previous_share[$i] = 0;
                    $previous_comment[$i] = 0;
                    $previous_reaction[$i] = 0;
                    $previous_views[$i] = 0;
                }

                $data['value'][2]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views);
            }
        }


        // foreach ($items_previous as $item) {
        //     $sourceName = self::matchSourceName($sources, $item->source_id);
        //     $index_label = array_search($sourceName, $data['labels']);

        //     if (isset($data['value'][1])) {
        //         $data['value'][1]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views);
        //     } else {

        //     }

        //     $previous_share[$index_label] += $item->number_of_shares;
        //     $previous_comment[$index_label] += $item->number_of_comments;
        //     $previous_reaction[$index_label] += $item->number_of_reactions;
        //     $previous_views[$index_label] += $item->number_of_views;
        // }

        foreach ($items_previous as $item) {
            $sourceName = self::matchSourceName($sources, $item->source_id);
            $index_label = array_search($sourceName, $data['labels']);

            if (!isset($data['value'][1]['data'][$index_label])) {
                $data['value'][1]['data'][$index_label] = 0;
            }

            $data['value'][1]['data'][$index_label] += (
                $item->number_of_shares +
                $item->number_of_comments +
                $item->number_of_reactions +
                $item->number_of_views
            );

            // ตรวจสอบและตั้งค่า default ถ้ายังไม่มี
            if (!isset($previous_share[$index_label])) $previous_share[$index_label] = 0;
            if (!isset($previous_comment[$index_label])) $previous_comment[$index_label] = 0;
            if (!isset($previous_reaction[$index_label])) $previous_reaction[$index_label] = 0;
            if (!isset($previous_views[$index_label])) $previous_views[$index_label] = 0;

            $previous_share[$index_label] += $item->number_of_shares;
            $previous_comment[$index_label] += $item->number_of_comments;
            $previous_reaction[$index_label] += $item->number_of_reactions;
            $previous_views[$index_label] += $item->number_of_views;
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        for ($i = 0; $i <= count($data['labels']); $i++) {

            if (isset($data['share'][$i])) {
                $data['share'][$i] = $this->overPeriodComparison($current_share[$i], $previous_share[$i]);
                $data['comment'][$i] = $this->overPeriodComparison($current_comment[$i], $previous_comment[$i]);
                $data['reaction'][$i] = $this->overPeriodComparison($current_reaction[$i], $previous_reaction[$i]);
                $data['views'][$i] = $this->overPeriodComparison($current_views[$i], $previous_views[$i]);
            } else {
//                $data['share'][$i] = [
//                    "totalValue" => 0,
//                    "comparison" => 0,
//                    "type" => "minus",
//                ];
//
//                $data['comment'][$i] = [
//                    "totalValue" => 0,
//                    "comparison" => 0,
//                    "type" => "minus",
//                ];
//
//                $data['reaction'][$i] = [
//                    "totalValue" => 0,
//                    "comparison" => 0,
//                    "type" => "minus",
//                ];
//                $data['views'][$i] = [
//                    "totalValue" => 0,
//                    "comparison" => 0,
//                    "type" => "minus",
//                ];
            }

        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementPeriodSentiment(Request $request, $only_data = false)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $raw_current = $this->raw_message_classification($this->campaign_id, $this->start_date, $this->end_date);
        $raw_previous = $this->raw_message_classification($this->campaign_id, $this->start_date_previous, $this->end_date_previous);

        $items_current = $raw_current->get();
        $items_previous = $raw_previous->get();
        $current_share = [];
        $previous_share = [];
        $current_comment = [];
        $previous_comment = [];
        $current_reaction = [];
        $previous_reaction = [];
        $current_views = [];
        $previous_views = [];

        $debug = null;
        $data['value'][1] = [
            'id' => 1,
            'keyword_name' => "Previous",
        ];

        $data['value'][2] = [
            'id' => 2,
            'keyword_name' => "Current",
        ];

        for ($i = 0; $i < count($data['labels']); $i++) {
            $data['value'][1]['data'][$i] = 0;
            $data['value'][2]['data'][$i] = 0;

            $data['share'][$i] = 0;
            $data['comment'][$i] = 0;
            $data['reaction'][$i] = 0;
            $data['views'][$i] = 0;

            $current_share[$i] = 0;
            $current_comment[$i] = 0;
            $current_reaction[$i] = 0;
            $current_views[$i] = 0;

            $previous_share[$i] = 0;
            $previous_comment[$i] = 0;
            $previous_reaction[$i] = 0;
            $previous_views[$i] = 0;
        }
$classifications = $this->getClassificationMaster();
        foreach ($items_current as $item) {
            $classificationName = self::matchClassificationName($classifications, $item->classification_sentiment_id);
            $index_label = array_search($classificationName, $data['labels']);

            if (isset($data['value'][2])) {
                $data['value'][2]['data'][$index_label] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
                $current_share[$index_label] += $item->number_of_shares;
                $current_comment[$index_label] += $item->number_of_comments;
                $current_reaction[$index_label] += $item->number_of_reactions;
                $current_views[$index_label] += $item->number_of_views;

            } else {


//                for ($i = 0; $i < count($data['labels']); $i++) {
//                    $data['value'][1]['data'][$i] = 0;
//                    $data['value'][2]['data'][$i] = 0;
//
//                    $data['share'][$i] = 0;
//                    $data['comment'][$i] = 0;
//                    $data['reaction'][$i] = 0;
//                    $data['views'][$i] = 0;
//
//                    $current_share[$i] = 0;
                $current_share[$index_label] = $item->number_of_shares;
//                    $current_comment[$i] = 0;
                $current_comment[$index_label] = $item->number_of_comments;
//                    $current_reaction[$i] = 0;
                $current_reaction[$index_label] = $item->number_of_reactions;
                $current_views[$index_label] = $item->number_of_views;

                $previous_share[$i] = 0;
                $previous_comment[$i] = 0;
                $previous_reaction[$i] = 0;
                $previous_views[$i] = 0;
//                }

                $data['value'][2]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views);
            }
        }

        foreach ($items_previous as $item) {
            $classificationName = self::matchClassificationName($classifications, $item->classification_sentiment_id);
            $index_label = array_search($classificationName, $data['labels']);

            if (isset($data['value'][1])) {

                $data['value'][1]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views);
            }

            $previous_share[$index_label] += $item->number_of_shares;
            $previous_comment[$index_label] += $item->number_of_comments;
            $previous_reaction[$index_label] += $item->number_of_reactions;
            $previous_views[$index_label] += $item->number_of_views;
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        for ($i = 0; $i < count($data['labels']); $i++) {
            $data['share'][$i] = $this->overPeriodComparison($current_share[$i], $previous_share[$i]);
            $data['comment'][$i] = $this->overPeriodComparison($current_comment[$i], $previous_comment[$i]);
            $data['reaction'][$i] = $this->overPeriodComparison($current_reaction[$i], $previous_reaction[$i]);
            $data['views'][$i] = $this->overPeriodComparison($current_views[$i], $previous_views[$i]);
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Previous",
//            "data" => [
//                47, 16, 30,
//            ],
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Current",
//            "data" => [
//                12, 16, 78,
//            ],
//        ];
//
//        $data['share'] = [
//            "-30%", "-30%", "-30%", "-30%", "-30%",
//        ];
//
//        $data['comment'] = [
//            "-23%", "-23%", "-23%", "-23%", "-23%",
//        ];
//
//        $data['reaction'] = [
//            "-56%", "-56%", "-56%", "-56%", "-56%",
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementTypeComparison($items_current, $items_previous, $keywords,  $only_data = false)
    {
        $data = [];


        $current = null;
        $previous = null;

        foreach ($items_current as $item) {
            if (isset($current[$item->keyword_id]) && $current[$item->keyword_id]) {
                $current[$item->keyword_id]['total'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
                $current[$item->keyword_id]['share'] += $item->number_of_shares;
                $current[$item->keyword_id]['comment'] += $item->number_of_comments;
                $current[$item->keyword_id]['reaction'] += $item->number_of_reactions;
                // $current[$item->keyword_id]['views'] += $item->number_of_views;
            } else {
                $current[$item->keyword_id]['keyword_name'] = self::matchKeywordName($keywords,$item->keyword_id);
                $current[$item->keyword_id]['total'] = $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
                $current[$item->keyword_id]['share'] = $item->number_of_shares;
                $current[$item->keyword_id]['comment'] = $item->number_of_comments;
                $current[$item->keyword_id]['reaction'] = $item->number_of_reactions;
                $current[$item->keyword_id]['views'] = $item->number_of_views;
            }
        }

        foreach ($items_previous as $item) {
            if (isset($previous[$item->keyword_id]) && $previous[$item->keyword_id]) {
                $previous[$item->keyword_id]['total'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
                $previous[$item->keyword_id]['share'] += $item->number_of_shares;
                $previous[$item->keyword_id]['comment'] += $item->number_of_comments;
                $previous[$item->keyword_id]['reaction'] += $item->number_of_reactions;
                $previous[$item->keyword_id]['views'] += $item->number_of_views;
            } else {
                $previous[$item->keyword_id]['total'] = $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views;
                $previous[$item->keyword_id]['share'] = $item->number_of_shares;
                $previous[$item->keyword_id]['comment'] = $item->number_of_comments;
                $previous[$item->keyword_id]['reaction'] = $item->number_of_reactions;
                $previous[$item->keyword_id]['views'] = $item->number_of_views;
            }
        }


        if ($current) {

            foreach ($current as $key => $item) {

                $p_total = 0;
                $s_total = 0;
                $c_total = 0;
                $r_total = 0;
                $v_total = 0;

                if (isset($previous[$key])) {
                    $p_total = $previous[$key]['total'];
                    $s_total = $previous[$key]['share'];
                    $c_total = $previous[$key]['comment'];
                    $r_total = $previous[$key]['reaction'];
                    $v_total = $previous[$key]['views'];
                }

                $data[] = [
                    'keyword_id' => $key,
                    'keyword_name' => $item['keyword_name'],
                    'total' => [
                        "value" => $item['total'] - $p_total,
                        // "percentage" => $this->overPeriodComparison($item['total'], $p_total),
                        "percentage" => $p_total ? self::point_two_digits((($item['total'] - $p_total) / $p_total) * 100) : 0,
                        "type" => $item['total'] - $p_total > 0 ? "plus" : "minus",
                    ],
                    'share' => [
                        "value" => $item['share'] - $s_total,
                        // "percentage" => $this->overPeriodComparison($item['share'], $s_total),
                        "percentage" => $s_total ? self::point_two_digits((($item['share'] - $s_total) / $s_total) * 100) : 0,
                        "type" => $item['share'] - $s_total > 0 ? "plus" : "minus",
                    ],
                    'comment' => [
                        "value" => $item['comment'] - $c_total,
                        // "percentage" => $this->overPeriodComparison($item['comment'], $c_total),
                        "percentage" => $c_total ? self::point_two_digits((($item['comment'] - $c_total) / $c_total) * 100) : 0,
                        "type" => $item['comment'] - $c_total > 0 ? "plus" : "minus",
                    ],
                    'reaction' => [
                        "value" => $item['reaction'] - $r_total,
                        // "percentage" => $this->overPeriodComparison($item['reaction'], $r_total),
                        "percentage" => $r_total ? self::point_two_digits((($item['reaction'] - $r_total) / $r_total) * 100) : 0,
                        "type" => $item['reaction'] - $r_total > 0 ? "plus" : "minus",
                    ],
                    'views' => [
                        "value" => $item['views'] - $v_total,
                        // "percentage" => $this->overPeriodComparison($item['views'], $v_total),
                        "percentage" => $v_total ? self::point_two_digits((($item['views'] - $v_total) / $v_total) * 100) : 0,
                        "type" => $item['views'] - $v_total > 0 ? "plus" : "minus",
                    ],
                ];
            }
        }
        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementActionComparison($engagement_actions, $keywords, $only_data = false)
    {
        $percentages = null;
        foreach ($engagement_actions as $engagement_action) {
            if (isset($percentages[$engagement_action->keyword_id])) {
                $engagements = ($engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions + $engagement_action->number_of_views);
                $percentages[$engagement_action->keyword_id]['total'] += $engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions + $engagement_action->number_of_views;
                $percentages[$engagement_action->keyword_id]['share_r'] += (float)$engagement_action->number_of_shares;
                $percentages[$engagement_action->keyword_id]['comment_r'] += (float)$engagement_action->number_of_comments;
                $percentages[$engagement_action->keyword_id]['reaction_r'] += (float)$engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['views_r'] += (float)$engagement_action->number_of_views;
                $percentages[$engagement_action->keyword_id]['share'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_shares / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['comment'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_comments / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['reaction'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_reactions / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['views'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_views / (float)$engagements) * 100) : 0;
            } else {
                $percentages[$engagement_action->keyword_id] = [
                    'share' => 0,
                    'share_r' => 0,
                    'comment' => 0,
                    'comment_r' => 0,
                    'reaction' => 0,
                    'reaction_r' => 0,
                    'views' => 0,
                    'views_r' => 0,
                    'total' => 0,
                    "keyword_id" => $engagement_action->keyword_id,
                    "keyword_name" => self::matchKeywordName($keywords,$engagement_action->keyword_id),
                    /*"campaign_id" => $engagement_action->campaign_id,
                    "campaign_name" => $engagement_action->campaign_name,*/
                ];

                $engagements = ($engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions + $engagement_action->number_of_views);
                $percentages[$engagement_action->keyword_id]['share_r'] += (float)$engagement_action->number_of_shares;
                $percentages[$engagement_action->keyword_id]['comment_r'] += (float)$engagement_action->number_of_comments;
                $percentages[$engagement_action->keyword_id]['reaction_r'] += (float)$engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['views_r'] += (float)$engagement_action->number_of_views;
                $percentages[$engagement_action->keyword_id]['share'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_shares / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['comment'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_comments / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['reaction'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_reactions / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['views'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_views / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['total'] += $engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions + $engagement_action->number_of_views;
            }
        }

        if ($percentages) {
            $percentages = array_values($percentages);
        }

        if ($only_data) {
            return $percentages;
        }
        return parent::handleRespond($percentages);
    }

    //todo mamybe is wrong
    public function EngagementByInfulencer($raw_current, $raw_previous, Request $request, $only_data = false)
    {
        $data = null;

        $page = $request->page ?? null;
        // $limit = $request->limit ?? 5;
        // $start = $page === null || $page === 1 ? null : $page * $limit;
        // $start = $start === 1 ? null : $start - 1;

        // $raw_current = $raw_current->groupBy('author');
        // $raw_previous = $raw_previous->groupBy('author');

        $items_current = $raw_current->get();
        $items_previous = $raw_previous->get();


        $current = null;
        $previous = null;
        $total = 0;

        foreach ($items_current as $item) {

            if ($item) {
                if (isset($current[$item->author]) && $current[$item->author]) {
                    $current[$item->author]['total'] += $item->total_engagement;
                    $current[$item->author]['share'] += $item->number_of_shares;
                    $current[$item->author]['comment'] += $item->number_of_comments;
                    $current[$item->author]['reaction'] += $item->number_of_reactions;
                    $current[$item->author]['views'] += $item->number_of_views;
                } else {
                    $current[$item->author]['message_id'] = $item->message_id;
                    $current[$item->author]['infulencer'] = $item->author;
                    $current[$item->author]['total'] = $item->total_engagement;
                    $current[$item->author]['share'] = $item->number_of_shares;
                    $current[$item->author]['comment'] = $item->number_of_comments;
                    $current[$item->author]['reaction'] = $item->number_of_reactions;
                    $current[$item->author]['views'] = $item->number_of_views;
                }
            }

        }

        foreach ($items_previous as $item) {
            if (isset($previous[$item->author]) && $previous[$item->author]) {
                $previous[$item->author]['total'] += $item->total_engagement;
                $previous[$item->author]['share'] += $item->number_of_shares;
                $previous[$item->author]['comment'] += $item->number_of_comments;
                $previous[$item->author]['reaction'] += $item->number_of_reactions;
                $previous[$item->author]['views'] += $item->number_of_views;
            } else {
                $previous[$item->author]['total'] = $item->total_engagement;
                $previous[$item->author]['share'] = $item->number_of_shares;
                $previous[$item->author]['comment'] = $item->number_of_comments;
                $previous[$item->author]['reaction'] = $item->number_of_reactions;
                $previous[$item->author]['views'] = $item->number_of_views;
            }
        }

//        dd($current, $previous);

        if ($current) {

            foreach ($current as $key => $item) {
                $previous_total = isset($previous[$key]['total']) ? $previous[$key]['total'] : 0;
                $data[] = [
                    'message_id' => $item['message_id'],
                    'infulencer' => $item['infulencer'],
                    "total" => $item['total'],
                    "share" => $item['share'],
                    "comment" => $item['comment'],
                    "reaction" => $item['reaction'],
                    "views" => $item['views'],
                    "period_over_preiod" => $item['total'] - $previous_total,
                    "period_over_period_percentage" => $this->overPeriodComparison($item['total'], $previous_total),

                ];
            }
        }


        $data = match ($request->select) {
            "top10" => array_slice($data, 0, 10),
            "top20" => array_slice($data, 0, 20),
            "top50" => array_slice($data, 0, 50),
            "top100" => array_slice($data, 0, 100),
            default => $data ? $data : null,
        };

        if ($data) {
            usort($data, function ($a, $b) {
                return $b['total'] - $a['total'];
            });
        }

        if ($only_data) {
            if ($data) {

                $data_['data'] = $data;
                $data_['total'] = count($data_['data']);

                // $page = $page < 1 ? 1 : $page;
                $start = ($page - 1) * (9 + 1);
                $offset = 9 + 1;

                $data_['data'] = array_slice($data_['data'], $start, $offset);

            }

            return $data_ ?? null;
        }


        return parent::handleRespond($data);
    }

    private function percentageOfEngagement($keywordIds, $start_date, $end_date, $keyword)
    {

        $message_keyword = [];
        $message_total = 0;
        $data = null;

        $totalKeyword = DB::table('messages')
            ->select([
                DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as total_engagement'),
                'messages.keyword_id as keyword_id',
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',*/
                'messages.created_at as date_m',
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        // if ($this->source_id) {
        //     $totalKeyword->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $totalKeyword->whereIn('source_id', $this->source_id);
            } else {
                $totalKeyword->where('source_id', $this->source_id);
            }
        }

        $totalKeyword = $totalKeyword->groupBy('keyword_id', 'date_m')->get();

        foreach ($totalKeyword as $object) {
            // dd($object);
            $item = (array)$object;

            if (isset($message_keyword[$item['keyword_id']])) {
                $message_keyword[$item['keyword_id']]['total'] += $object->total_engagement;
            } else {
                $message_keyword[$item['keyword_id']]['total'] = $object->total_engagement;
                $message_keyword[$item['keyword_id']]['keyword_name'] = self::matchKeywordName($keyword, $item['keyword_id']);
            }

            $message_total += $object->total_engagement;
        }

        foreach ($message_keyword as $keyword_id => $value) {
            $percentage = 0;
            if (isset($value['total']) && $value['total'] && $message_total) {
                $percentage = self::point_two_digits(($value['total'] / $message_total) * 100);
            }
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
                'total' => self::point_two_digits($message_total, 0),
                "keyword_name" => $value['keyword_name'],
                "name" => $value['keyword_name'],
            ];
        }

        if ($data) {
            $data = array_values($data);

        }

        return $data;

    }

    private function engagement($keywordIds)
    {
        $data = null;
        $totalKeyword = DB::table('messages')
            ->select([
                DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as total_engagement'),
                'messages.keyword_id as keyword_id',
                'messages.created_at as date_m',
                /*'campaigns.name AS campaign_name',
                                'keywords.name as keyword_name',
                                'keywords.campaign_id AS campaign_id',*/
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        // if ($this->source_id) {
        //     $totalKeyword->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $totalKeyword->whereIn('source_id', $this->source_id);
            } else {
                $totalKeyword->where('source_id', $this->source_id);
            }
        }

        $totalKeyword = $totalKeyword->groupBy('keyword_id', 'date_m')->get();
        $keywordName = DB::table('keywords')->where("status", "=", 1)->get();
        $campaign = DB::table('campaigns')->where("id", $this->campaign_id)->first();
        foreach ($totalKeyword as $item) {
            $keyword_id = $item->keyword_id;
            $date_m = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$keyword_id])) {
                if (isset($data[$keyword_id]['value'][$date_m])) {
                    $data[$keyword_id]['value'][$date_m]['total_at_date'] += $item->total_engagement;

                } else {

                    $data[$keyword_id]['value'][$date_m] = [
                        'date' => $date_m,
                        'total_at_date' => $item->total_engagement
                    ];
                }
            } else {
                $nestData = [
                    'date' => $date_m,
                    'total_at_date' => $item->total_engagement
                ];
                $data[$keyword_id] = [
                    "keyword_id" => $item->keyword_id,
                    "keyword_name" => self:: matchKeywordName($keywordName, $item->keyword_id),
                    "campaign_id" => $campaign->id,
                    "campaign_name" => $campaign->name,
                ];

                $data[$keyword_id]['value'][$date_m] = $nestData;
            }
        }

        if ($data) {

            foreach ($data as $k => $value) {
                if ($value['value']) {
                    $data[$k]['value'] = array_values($value['value']);
                }
            }
        }

        if ($data) {
            $data = array_values($data);
        }
        return $data;
    }


    private function overPeriodComparison($current, $previous)
    {

        if ($current - $previous === 0 || $previous === 0) {
            return 0;
        }

        if ($previous == 0) {
            return 0; // หลีกเลี่ยงการหารด้วยศูนย์
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

    private function raw_message($keywords, $start_date, $end_date)
    {

        $keywordIds = $keywords->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'sources.name as source_name',*/
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.message_id as message_id',
                'messages.reference_message_id as reference_message_id',
                'messages.author as author',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_reactions as number_of_reactions',
                'messages.number_of_views as number_of_views',
                DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) +
                    COALESCE(number_of_views, 0) AS total_engagement')
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->whereIn('messages.keyword_id', $keywordIds)
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

    private function raw_message_classification($campaign_id, $start_date, $end_date)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
                'messages.device as device',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_reactions as number_of_reactions',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_views as number_of_views',
                'message_results_2.media_type',
                'message_results_2.classification_sentiment_id',
            ])

            ->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
            ->whereIn('keyword_id', $keywordIds)
            ->where('message_results_2.media_type', 1)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);
            //->whereIn('message_results_2.classification_sentiment_id', ['1', '2', '3']);

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
