<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Message;
use App\Models\Organization;
use App\Models\SNA;
use App\Models\SNAChildNode;
use App\Models\SNARootNode;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;
use function PHPUnit\Framework\returnArgument;

class DashboardController extends Controller
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

    public function overAll(Request $request)
    {
        $data = null;
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword->whereIn('id', $this->keyword_id);
        }

        $keywordIds = $keyword->pluck('id')->all();

        $total_keywords = $this->getTotalKeywords($keywordIds, $this->start_date, $this->end_date, $this->source_id);
        $total_keywords_previous = $this->getTotalKeywords($keywordIds, $this->start_date_previous, $this->end_date_previous, $this->source_id);

        $sources = $this->getSources();
        $keywords = $this->getKeywords();
        $campaign = $this->getCampaign($this->campaign_id);

        $data['daily_message'] = $this->dailyMessage($total_keywords, $sources, $keywords, $campaign);
        $data['date_of_messages_current'] = $this->getDateRange($this->start_date, $this->end_date);
        $data['date_of_messages_previous'] = $this->getDateRange($this->start_date_previous, $this->end_date_previous);
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($this->start_date, $this->end_date, $total_keywords, $sources, $keywords, $campaign);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($this->start_date_previous, $this->end_date_previous, $total_keywords_previous, $sources, $keywords, $campaign);

        return parent::handleRespond($data);
    }

    private function getTotalKeywords($keywordIds, $startDate, $endDate, $sourceId = null)
    {
        return DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.created_at as date_m',
            ])
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', ["$startDate 00:00:00", "$endDate 23:59:59"])
            ->when($sourceId, function ($query) use ($sourceId) {
                //return $query->where('source_id', $sourceId);

                if ($sourceId) {
                    if (is_array($sourceId) && count($sourceId) > 1) {
                        return $query->whereIn('source_id', $sourceId);
                    } else {
                        return $query->where('source_id', is_array($sourceId) ? $sourceId[0] : $sourceId);
                    }
                }
            })
            ->get();
    }

    private function getSources()
    {
        return DB::table('sources')->where("status", "=", 1)->get();
    }

    private function getKeywords()
    {
        return DB::table("keywords")->where('campaign_id', $this->campaign_id)->get();
    }

    private function dailyMessage($totalKeywords, $sources, $keywords, $campaign)
    {
        $data = [];

        foreach ($totalKeywords as $item) {
            $keyword = self::matchKeywordName($keywords, $item->keyword_id);
            $dateFormat = Carbon::parse($item->date_m)->format('Y-m-d');

            if (!isset($data[$keyword])) {
                $data[$keyword] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $keyword,
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'source_id' => $item->source_id,
                    'source_name' => self::matchSourceName($sources, $item->source_id),
                    'value' => [],
                ];
            }

            if (!isset($data[$keyword]['value'][$dateFormat])) {
                $data[$keyword]['value'][$dateFormat] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $keyword,
                    'date_m' => $dateFormat,
                    'total_at_date' => 0,
                ];
            }

            $data[$keyword]['value'][$dateFormat]['total_at_date'] += 1;
        }

        foreach ($data as &$item) {
            $item['value'] = array_values($item['value']);
        }

        return array_values($data);
    }

    private function getDateRange($startDate, $endDate)
    {
        return Carbon::createFromFormat('Y-m-d', $startDate)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $endDate)->format('d/m/Y');
    }

    private function percentageOfMessages($startDate, $endDate, $totalKeywords, $sources, $keywords, $campaign)
    {
        $messageKeyword = [];
        $messageTotal = 0;

        foreach ($totalKeywords as $item) {
            $keyword = self::matchKeywordName($keywords, $item->keyword_id);
            if (!isset($messageKeyword[$keyword])) {
                $messageKeyword[$keyword] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $keyword,
                    'count' => 0
                ];
            }
            $messageKeyword[$keyword]['count'] += 1;
            $messageTotal += 1;
        }

        $data = [];

        foreach ($messageKeyword as $keyword => $value) {
            $percentage = $messageTotal ? self::point_two_digits(($value['count'] / $messageTotal) * 100) : $messageTotal;
            $data[] = [
                'keyword_id' => $value['keyword_id'],
                'keyword_name' => $value['keyword_name'],
                'value' => [
                    [
                        'date' => $this->getDateRange($startDate, $endDate),
                        'percentage' => $percentage,
                    ]
                ],
                'total' => self::point_two_digits($messageTotal, 0)
            ];
        }

        return $data;
    }


    public function keyStats(Request $request)
    {
        //$source_id = $request->source ?? null;

        //dd($this->source_id);
        // Fetch keywordIds once
        $keywordIds = self::findKeywords($this->campaign_id, $this->keyword_id)
            ->pluck('id')
            ->all();

        $data['total_messages'] = $this->totalMessages($keywordIds, $this->start_date, $this->end_date, $this->source_id, $this->start_date_previous, $this->end_date_previous);
        $data['total_engagement'] = $this->totalEngagement($keywordIds, $this->start_date, $this->end_date, $this->source_id, $this->start_date_previous, $this->end_date_previous);
        $data['total_accounts'] = $this->totalAccounts($keywordIds, $this->start_date, $this->end_date, $this->source_id, $this->start_date_previous, $this->end_date_previous);
        return parent::handleRespond($data);
    }

    private function totalMessages($keywordIds, $start_date, $end_date, $source_id, $start_date_previous, $end_date_previous)
    {

        $total_current = $this->calculateMessage($keywordIds, $start_date, $end_date, $source_id);
        $total_previous = $this->calculateMessage($keywordIds, $start_date_previous, $end_date_previous, $source_id);

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if ($percentage == -100) {
            $percentage = 0;
        }

        return [
            "total_message" => (int) $total_current, // $this->point_two_digits($total_current, 2),
            "average_message" => $diff_date ? $this->point_two_digits($total_current / $diff_date, 2) : 0,
            "comparison" => $this->point_two_digits($comparison, 2),
            "percentage" => $this->point_two_digits($percentage, 2),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function calculateMessage($keywordIds, $start_date, $end_date, $source_id)
    {
        return DB::table('messages')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->when($source_id, function ($query, $source_id) {
                return $query->whereIn('source_id', $source_id);
            })
            ->count();
    }

    private function totalAccounts($keywordIds, $start_date, $end_date, $source_id, $start_date_previous, $end_date_previous)
    {
        // No need to execute another query to get ids
        // $keywordIds = $this->getKeywordIds();

        // Use a helper method to calculate total accounts for a given period
        $total_current = $this->calculateAccounts($keywordIds, $start_date, $end_date, $source_id);
        $total_previous = $this->calculateAccounts($keywordIds, $start_date_previous, $end_date_previous, $source_id);

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if ($percentage == -100) {
            $percentage = 0;
        }

        return [
            "total_account" => (int)$total_current, // $this->point_two_digits($total_current, 2),
            "average_account" => $this->point_two_digits($total_current / $diff_date, 2),
            "comparison" => $this->point_two_digits($comparison, 2),
            "percentage" => $this->point_two_digits($percentage, 2),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

// A new helper method to calculate total accounts for a given period
    private function calculateAccounts($keywordIds, $start_date, $end_date, $source_id)
    {
        return DB::table('messages')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->when($source_id, function ($query, $source_id) {
                return $query->whereIn('source_id', $source_id);
            })
            ->distinct('author')
            ->count('author');
    }

    private function totalEngagement($keywordIds, $start_date, $end_date, $source_id, $start_date_previous, $end_date_previous)
    {
        $total_current = $this->calculateEngagement($keywordIds, $start_date, $end_date, $source_id);
        $total_previous = $this->calculateEngagement($keywordIds, $start_date_previous, $end_date_previous, $source_id);

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if ($percentage == -100) {
            $percentage = 0;
        }

        return [
            "total_engagement" => (int) $total_current, // $this->point_two_digits($total_current, 2),
            "average_engagement" => $this->point_two_digits($total_current / $diff_date, 2),
            "comparison" => $this->point_two_digits($comparison, 2),
            "percentage" => $this->point_two_digits($percentage, 2),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }


    private function calculateEngagement($keywordIds, $start_date, $end_date, $source_id)
    {
        return DB::table('messages')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->when($source_id, function ($query, $source_id) {
                return $query->whereIn('source_id', $source_id);
            })
            ->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions + number_of_views'));
    }

    public function sentimentScore(Request $request)
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);

        $result = $this->findSentiment($keywords, $this->start_date, $this->end_date, $this->source_id);
        $current = self::parseSentiment($result);

        $result = $this->findSentiment($keywords, $this->start_date_previous, $this->end_date_previous, $this->source_id);
        $previous = self::parseSentiment($result);

        return parent::handleRespond([
            "neutral_value" => $current['results'] ?? 0.00,
            "current" => $current,
            "pervious" => $previous,
            "sentiment_percentage" => $current['sentiment_percentage'] ?? 0,
            "pervious_sentiment" => $previous['results'] ?? 0.00,
            "text" => $current['text']
        ]);
    }

    private function findSentiment($keywords, $start_date, $end_date, $source_id = null)
    {
        $convert_id = null;
        if ($keywords) {
            $convert_id = implode(',', $keywords->pluck('id')->all());
        }

        // $sourceQuery = "";
        // if ($source_id) {
        //     $sourceQuery = "m.source_id = $source_id AND ";
        // }

        if (!is_array($this->source_id)) {
            $sourceQuery = "m.source_id = " . $this->source_id . " AND ";
        } else {
            $sourceQuery = "m.source_id IN (" . implode(",", $this->source_id) . ") AND ";
        }   

        $results = DB::select(DB::raw("SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN mr.classification_sentiment_id = '1' THEN 1 ELSE 0 END) AS positive,
            SUM(CASE WHEN mr.classification_sentiment_id = '2' THEN 1 ELSE 0 END) AS negative,
            SUM(CASE WHEN mr.classification_sentiment_id = '3' THEN 1 ELSE 0 END) AS neutral,
            ((SUM(CASE WHEN mr.classification_sentiment_id = '1' THEN 1 ELSE 0 END) * 1) +
            (SUM(CASE WHEN mr.classification_sentiment_id = '2' THEN 1 ELSE 0 END) * -1)) / COUNT(*) * 5 AS sentiment_score
        FROM
            tbl_messages m
        LEFT JOIN tbl_message_results_2 mr ON m.id = mr.message_id
        WHERE $sourceQuery
            m.keyword_id IN ($convert_id) AND m.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'"));
        /*AND c.name IN ('Positive', 'Negative', 'Neutral')*/
        return $results;
    }

    function parseSentiment($results)
    {

        $positive = 0;
        $negative = 0;
        $neutral = 0;
        $sentiment_score = 0;
        if (!empty($results)) {
            $results = $results[0];
            $sentiment_score = $results->sentiment_score ?? 0;
            $positive = $results->positive;
            $negative = $results->negative;
            $neutral = $results->neutral;
            //$sentiment_score = $results->sentiment_score;
        }

        $data['neutral'] = $neutral ?? 0;
        $data['positive'] = $positive ?? 0;
        $data['negative'] = $negative ?? 0;
        $data['results'] = round($sentiment_score ?? 0, 2);
        $data['sentiment_score'] = $sentiment_score ?? 0;

        $sentiment_score = (int)round($sentiment_score ?? 0);


        if ($sentiment_score === 0) {
            $percentage = 0;
        }

        if ($sentiment_score <= -5) {
            $percentage = 0;
        } else if ($sentiment_score == -4) {
            $percentage = 10;
        } else if ($sentiment_score == -3) {
            $percentage = 20;
        } else if ($sentiment_score == -2) {
            $percentage = 30;
        } else if ($sentiment_score == -1) {
            $percentage = 40;
        } else if ($sentiment_score == 0) {
            $percentage = 50;
        } else if ($sentiment_score == 1) {
            $percentage = 60;
        } else if ($sentiment_score == 2) {
            $percentage = 70;
        } else if ($sentiment_score == 3) {
            $percentage = 80;
        } else if ($sentiment_score == 4) {
            $percentage = 90;
        } else if ($sentiment_score >= 5) {
            $percentage = 100;
        }

        $data['sentiment_percentage'] = $percentage ?? 0;
        $data['text'] = $this->closest_sentiment_score($data['sentiment_percentage'] ?? 0);
        return $data;
    }

    private function closest_sentiment_score($target)
    {
        if ($target <= 40) {
            return 'Negative';
        }

        if (($target >= 41 && $target <= 70)) {
            return 'Neutral';
        }

        if ($target > 70) {
            return 'Positive';
        }
    }

    public function sentimentType(Request $request)
    {
        //$keyword->pluck('id')->all();
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);

        $result = $this->findSentiment($keywords, $this->start_date, $this->end_date, $this->source_id);
        $current = self::parseSentiment($result);
        $message_total = $current['positive'] + $current['negative'] + $current['neutral'];

        return parent::handleRespond([
            "positive_percentage" => $message_total ? self::point_two_digits(($current['positive'] / $message_total) * 100) : 0,
            "neutral_percentage" => $message_total ? self::point_two_digits(($current['neutral'] / $message_total) * 100) : 0,
            "negative_percentage" => $message_total ? self::point_two_digits(($current['negative'] / $message_total) * 100) : 0,
        ]);
    }

    public function keywordSummary(Request $request)
    {
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();
        $totalKeyword = DB::table('messages')
            ->select([
                'keyword_id', DB::raw('COUNT(*) as row_count'),
                DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as total_engagement'),
                'author', DB::raw('COUNT(DISTINCT author) as author_count'),
            ])
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

        $totalKeyword = $totalKeyword->groupBy('keyword_id')->get();

        $diff_date = $this->diff_date($this->start_date, $this->end_date);

        foreach ($totalKeyword as $keywordData) {
            $data_push = [
                // "id" => $id++,
                "keyword" => self::matchKeywordName($keyword, $keywordData->keyword_id),
                "keyword_id" => $keywordData->keyword_id,
                "message" => $this->point_two_digits($keywordData->row_count, 0),
                "engagement" => $this->point_two_digits($keywordData->total_engagement, 0),
                "accounts" => $this->point_two_digits($keywordData->author_count, 0),
                "average_message" => $this->point_two_digits($keywordData->row_count / $diff_date),
                "average_engagement" => $this->point_two_digits($keywordData->total_engagement / $diff_date),
            ];

            $data[] = $data_push;
        }


        return parent::handleRespond($data);
    }

    public function keywordSummaryTop(Request $request)
    {
        $data = null;
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();


        $data['main_keyword'] = $this->mainKeyWords($keywordIds, $this->start_date, $this->end_date, $keyword);
        $data['top_sites'] = $this->topSites($keywordIds, $this->start_date, $this->end_date, $keyword);
        $data['top_hastag'] = $this->topHashtag($keywordIds, $this->start_date, $this->end_date, $keyword);

        return parent::handleRespond($data);
    }


    public function mainKeyWords($keywordIds, $start_date, $end_date, $keyword)
    {

        $totalKeyword = DB::table('messages')
            ->select('keyword_id', DB::raw('COUNT(*) as row_count'))
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

        $totalKeyword = $totalKeyword->groupBy('keyword_id')->get();

        $messageAll = $totalKeyword->sum('row_count');
        $mainKeyword = [];

        foreach ($totalKeyword as $keywordId) {
            //$keyword = Keyword::find($keywordId);


            if ($keywordId) {
                $percentage = ($keywordId->row_count / $messageAll) * 100;
            } else {
                $percentage = 0;
            }

            $mainKeyword[] = [
                'keyword' => self::matchKeywordName($keyword, $keywordId->keyword_id),
                'keyword_id' => $keywordId->keyword_id,
                'no_of_message' => $this->point_two_digits($keywordId ? $keywordId->row_count : 0),
                'percentage' => $this->point_two_digits($percentage),
                "type" => ($percentage >= 0 ? "plus" : "minus"),
            ];
        }

        return $mainKeyword;
    }

    private function topSites($keywordIds, $start_date, $end_date, $keywords)
    {
        $totalKeyword = DB::table('messages')
            ->whereIn('keyword_id', $keywordIds)
            ->where('source_id', 5)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        //$messageAll = $totalKeyword->count();
        $totalKeyword = $totalKeyword->get();
        $messageAll = count($totalKeyword);
        $mainLink = [];
        $data = []; 

        foreach ($totalKeyword as $object) {
            $item = (array)$object;

            if (isset($mainLink[$item['link_message']])) {
                $mainLink[$item['link_message']] += 1;
            } else {
                $mainLink[$item['link_message']] = 1;
            }
        }

        foreach ($mainLink as $link_message => $value) {
            // $id + 1;
            $percentage = 0;
            if ($value && $messageAll) {
                $percentage = self::point_two_digits(($value / $messageAll) * 100);
            }

            $data[$link_message] = [
                // 'id' => $id++,
                'site_domain' => $link_message,
                'percentage' => $percentage,
                "no_of_message" => $value ?? 0
            ];
        }

        if ($data) {
            $data = array_values($data);
            array_multisort(array_column($data, "no_of_message"), SORT_DESC, $data);
            $data = array_slice($data, 0, 10);
        }

        return $data;
    }

    private function topHashtag($keywordIds, $start_date, $end_date, $keywords)
    {


        $raw_total = DB::table('hashtags')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('date_count', [$start_date, $end_date]);

        $raw = DB::table('hashtags')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('date_count', [$start_date, $end_date])->orderBy('count_number', 'desc');

        // if ($this->source_id) {
        //     $raw_total->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $raw_total->whereIn('source_id', $this->source_id);
            } else {
                $raw_total->where('source_id', $this->source_id);
            }
        }        

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->whereIn('source_id', $source_ids);
            $raw_total->whereIn('source_id', $source_ids);
        }

        $total_keywords = $raw_total->sum('count_number');

        $hashtags = $raw->limit(100)->get();
        $data = [];

        foreach ($hashtags as $hashtag) {

            if (isset($data[$hashtag->hashtag])) {
                $data[$hashtag->hashtag]['no_of_message'] += $hashtag->count_number;
                $data[$hashtag->hashtag]["percentage"] = $total_keywords > 0 ? $data[$hashtag->hashtag]['no_of_message'] / $total_keywords * 100 : 0;
                $data[$hashtag->hashtag]["type"] = $data[$hashtag->hashtag]['no_of_message'] >= 0 ? 'plus' : 'minus';
            } else {
                $data[$hashtag->hashtag] = [
                    "id" => $hashtag->id,
                    "hashtag" => $hashtag->hashtag,
                    "keyword_id" => $hashtag->keyword_id,
                    "no_of_message" => $hashtag->count_number,
                    "total_keyword" => $total_keywords,
                    "percentage" => $total_keywords > 0 ? $hashtag->count_number / $total_keywords * 100 : 0,
                    "type" => $hashtag->count_number >= 0 ? 'plus' : 'minus',
                ];
            }
        }

        if ($data) {
            $data = array_values($data);

            usort($data, function ($a, $b) {
                return $b['no_of_message'] - $a['no_of_message'];
            });
        }


        return $data;
    }

    public function shareOfVoice(Request $request)
    {
        $data = null;

        $keyword = self::findKeywords($this->campaign_id, $this->keyword_id);
        $campaign = $this->getCampaign($this->campaign_id);
        $keywordIds = $keyword->pluck('id')->all();

        $total_keywords = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',*/
                'messages.source_id as source_id',
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);
        // if ($this->source_id) {
        //     $total_keywords->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id) && count($this->source_id) > 1) {
                $total_keywords->whereIn('messages.source_id', $this->source_id);
            } else {
                $total_keywords->where('messages.source_id', is_array($this->source_id) ? $this->source_id[0] : $this->source_id);
            }
        }

        $source = Sources::where('status', 1)->get();

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $total_keywords->whereIn('source_id', $source_ids);

            $source = Sources::whereIn('id', $source_ids)
            ->where('status',1)
            ->get();
        }

        foreach ($source as $source_id) {
            $push['name'] = $source_id->name;
            $push['id'] = $source_id->id;
            $labels['labels'][] = $push;
        }

        // foreach ($total_keywords->get() as $item) {
        //     $keyword_id = $item->keyword_id;
        //     if (isset($data[$keyword_id]['value'])) {
        //         // $data[$keyword_id]['value'][$item->source_id]['number_of_message'] += 1;
        //         if (!isset($data[$keyword_id]['value'][$item->source_id]['number_of_message'])) {
        //             $data[$keyword_id]['value'][$item->source_id]['number_of_message'] = 0;
        //         }
        //         $data[$keyword_id]['value'][$item->source_id]['number_of_message'] += 1;
        //         $data[$keyword_id]['total'] += 1;
        //     } else {
        //         $data[$keyword_id]['keyword_id'] = $item->keyword_id;
        //         $data[$keyword_id]['keyword_name'] = self::matchKeywordName($keyword, $item->keyword_id);
        //         $data[$keyword_id]['campaign_id'] = $campaign->id;
        //         $data[$keyword_id]['campaign_name'] = $campaign->name;
        //         $data[$keyword_id]['organization_id'] = 1;
        //         $data[$keyword_id]['organization_name'] = 'organizations_name 1';
        //         $data[$keyword_id]['total'] = 1;

        //         for ($i = 0; $i < count($labels['labels']); $i++) {
        //             $source_id = $labels['labels'][$i]['id'];
        //             $data[$keyword_id]['value'][$labels['labels'][$i]['id']]['channel'] = $labels['labels'][$i]['name'];
        //             $data[$keyword_id]['value'][$labels['labels'][$i]['id']]['id'] = $labels['labels'][$i]['id'];
        //             $data[$keyword_id]['value'][$labels['labels'][$i]['id']]['number_of_message'] = 0;
        //             $data[$keyword_id]['value'][$labels['labels'][$i]['id']]['keyword_id'] = $item->keyword_id;
        //             $source_image = $source->firstWhere('id', $source_id)->image ?? null;
        //             $data[$keyword_id]['value'][$source_id]['source_image'] = $source_image;
        //         }
        //     }
        // }

        $source = $source->where('status', 1);
        foreach ($total_keywords->get() as $item) {
            $keyword_id = $item->keyword_id;
            $source_id = $item->source_id;

            
            if (isset($data[$keyword_id]['value'])) {
                if (!isset($data[$keyword_id]['value'][$source_id]['number_of_message'])) {
                    $data[$keyword_id]['value'][$source_id]['number_of_message'] = 0;
                }
                $data[$keyword_id]['value'][$source_id]['number_of_message'] += 1;
                $data[$keyword_id]['total'] += 1;
            } else {
                $data[$keyword_id]['keyword_id'] = $item->keyword_id;
                $data[$keyword_id]['keyword_name'] = self::matchKeywordName($keyword, $item->keyword_id);
                $data[$keyword_id]['campaign_id'] = $campaign->id;
                $data[$keyword_id]['campaign_name'] = $campaign->name;
                $data[$keyword_id]['organization_id'] = 1;
                $data[$keyword_id]['organization_name'] = 'organizations_name 1';
                $data[$keyword_id]['total'] = 1;

                foreach ($labels['labels'] as $label) {
                    $label_id = $label['id'];
                    $data[$keyword_id]['value'][$label_id]['channel'] = $label['name'];
                    $data[$keyword_id]['value'][$label_id]['id'] = $label_id;
                    $data[$keyword_id]['value'][$label_id]['keyword_id'] = $item->keyword_id;

                    if ($label_id == $source_id) {
                        $data[$keyword_id]['value'][$label_id]['number_of_message'] = 1;
                    } else {
                        $data[$keyword_id]['value'][$label_id]['number_of_message'] = 0;
                    }
                    $source_image = $source->firstWhere('id', $label_id)->image ?? null;
                    $data[$keyword_id]['value'][$label_id]['source_image'] = $source_image;
                }
            }
        }


        // if ($data) {
        //     foreach ($data as $item_share) {
        //         $keyword_id = $item_share['keyword_id'];
        //         $total = $item_share['total'];

        //         foreach ($item_share['value'] as $value) {
        //             $percentage = !$total ? 0 : ($value['number_of_message'] / $total) * 100;
        //             $data[$value['keyword_id']]['value'][$value['id']]['percentage'] = self::point_two_digits($percentage);
        //         }

        //         if (isset($data[$keyword_id]['value'])) {
        //             $data[$keyword_id]['value'] = array_values($data[$keyword_id]['value']);
        //         }
        //     }
        // }

        if ($data) {
            foreach ($data as &$item_share) {
                $keyword_id = $item_share['keyword_id'];
                $total = $item_share['total'];
                $total_percentage = 0;

                foreach ($item_share['value'] as &$value) {
                    $percentage = !$total ? 0 : ($value['number_of_message'] / $total) * 100;
                    // $value['percentage'] = self::point_two_digits($percentage);
                    // $total_percentage += self::point_two_digits($percentage);
                    $value['percentage'] = self::point_two_digits($percentage);
                    $total_percentage += self::point_two_digits($percentage);
                }

                // $last_index = count($item_share['value']) - 1;
                // if ($total_percentage != 100) {
                //     $diff = 100 - $total_percentage;
                //     $value = &$item_share['value'][$last_index];
                //     // $value["percentage"] += $diff;
                //     if (!isset($value["percentage"])) {
                //         $value["percentage"] = 0; 
                //     }
                //     $value["percentage"] += $diff;
                //     $value["percentage"] = self::point_two_digits($value["percentage"]);

                //     if ($value['percentage'] > 100) {
                //         $value['percentage'] = 100;
                //     }
                // }

                if (isset($data[$keyword_id]['value'])) {
                    $data[$keyword_id]['value'] = array_values($data[$keyword_id]['value']);
                }
            }
        }

        if ($data) {
            $data = array_values($data);
        }

        return parent::handleRespond($data);
    }

    public function sentimentLevel(Request $request)
    {
        $data = null;

        $keyword = self::findKeywords($this->campaign_id, $this->keyword_id);
        $keywordIds = $keyword->pluck('id')->all();
        // error_log(json_encode($keywordIds));
        $raw_query = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',*/
                /*'campaigns.name AS campaign_name',*/
                'messages.source_id as source_id',
                /*'sources.name as source_name',*/
                'messages.created_at as date_m',
                'message_results_2.media_type',
                'message_results_2.classification_sentiment_id',
                // 'message_results.classification_id as classification_id',
                /*'classifications.name as classification_name',*/
            ])
            //->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            //->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            /*->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
            /*->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('messages.created_at', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            //->whereIn('message_results_2.classification_sentiment_id', ['1', '2', '3'])
            ->where('message_results_2.media_type', 1);

            // error_log(json_encode($this->start_date));
            // error_log(json_encode($this->end_date));
        if ($this->keyword_id) {
            $raw_query->whereIn('keyword_id', $this->keyword_id);
        }

        // if ($this->source_id) {
        //     $raw_query->where('source_id', $this->source_id);
        // }
        
        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $raw_query->whereIn('source_id', $this->source_id);
            } else {
                $raw_query->where('source_id', $this->source_id);
            }
        }

        $data1 = $raw_query->get();
        $count = $data1->count();
        error_log(json_encode($count));

        $raw_query = $raw_query->get();
        $keywordName = DB::table('keywords')->where("status", "=", 1)->get();
        //$campaign = DB::table('campaigns')->where("id", "=", $this->campaign_id)->get()->first();
        $classification = parent::getClassificationMaster();
        foreach ($raw_query as $result) {
            $classification_name = $this->matchClassificationName($classification, $result->classification_sentiment_id);
            if (!isset($data[$result->keyword_id])) {
                $data[$result->keyword_id] = [
                    'keyword_id' => $result->keyword_id,
                    'keyword_name' => self::matchKeywordName($keywordName, $result->keyword_id),
                    /*'campaign_id' => $campaign->id,*/
                    /*'campaign_name' => $campaign->name,*/
                    'organization_id' => 1,
                    'organizations_name' => 'organizations_name 1',
                    'Negative' => 0,
                    'Neutral' => 0,
                    'Positive' => 0,
                    'total' => 0,
                ];

            }
            $data[$result->keyword_id][$classification_name] += 1;
            $data[$result->keyword_id]['total'] += 1;
        }

        if ($data) {

            foreach ($data as $item) {
                $data[$item['keyword_id']]['Negative'] = $item['total'] ? self::point_two_digits(($item['Negative'] / $item['total']) * 100) : 0;
                $data[$item['keyword_id']]['Positive'] = $item['total'] ? self::point_two_digits(($item['Positive'] / $item['total']) * 100) : 0;
                $data[$item['keyword_id']]['Neutral'] = $item['total'] ? self::point_two_digits(($item['Neutral'] / $item['total']) * 100) : 0;
            }
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    private function wordCloudsData($worlds)
    {
        $dummy_data = [];

        foreach ($worlds as $world) {
            if (isset($dummy_data[$world->word])) {
                $dummy_data[$world->word]['value'] += self::point_two_digits($world->count_number, 0);
                // $dummy_data[$world->word]['total'] = self::point_two_digits($worlds->count(), 0);
            } else {
                $dummy_data[$world->word] = [
                    'text' => $world->word,
                    'data' => $world,
                    'value' => self::point_two_digits($world->count_number, 0),
                    //                    'total' => self::point_two_digits($worlds->count(), 0)
                ];
            }
        }

        if ($dummy_data) {

            $dummy_data = array_values($dummy_data);
            usort($dummy_data, function ($a, $b) {
                return $b['value'] - $a['value'];
            });
        }


        return $dummy_data;
    }

    public function wordClouds(Request $request)
    {
        $data = null;
        $campaign_id = $this->campaign_id ?? "";

        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $keywords = self::findKeywords($campaign_id, $this->keyword_id);
        $limit = self::selectData($request->select);
        if ($limit == 0) {
            $limit = 100;
        }
        $sourceQuery = "";
        $wordQuery = "";
        if ($request->platform_id) {
            $this->source_id = $request->platform_id;
            $sourceQuery = "source_id = " . $this->source_id . " and ";
        }

        /*        if ($request->word) {
                    $wordQuery="word Like '%".$request->word."%' and ";
                }*/

        $sources = DB::table('sources')->where('status', 1)->pluck('id')->toArray();
        $sourceId = implode(",", $sources);

        $wordclouds = DB::select("SELECT message_id,author,keyword_id,source_id,
    word as text,
    SUM(count_number) AS value
FROM
    tbl_word_clouds
WHERE
    word != 'https' AND word != 'http' and
    message_id != '' and
    source_id IN ($sourceId) AND
    $sourceQuery
keyword_id IN (" . implode(",", $keywords->pluck('id')->toArray()) . ") AND
    date_count between '$this->start_date 00:00:00' AND '$this->end_date 23:59:59'
GROUP BY
    text ORDER BY value desc limit $limit;");

        /*if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw_total->whereIn('source_id', $source_ids);
        }*/

        /*if ($this->source_id) {
            $raw_total->where('source_id', $this->source_id);
        }
        $wordclouds = $raw_total->orderBy('count_number', 'desc')->get();
*/
        $count_number = 0;
        foreach ($wordclouds as $wordcloud) {
            $count_number = $wordcloud->value;
        }

        $data['word_clouds'] = $wordclouds;//self::wordCloudsMessage($this->wordCloudsData($wordclouds), $select);
        $data['word_total'] = self::point_two_digits($count_number);
        $data['total'] = count($wordclouds);
        $data['word_clouds_table'] = $this->wordCloudsMessageTable($keywords, $wordclouds, $count_number);
        /**/


        return parent::handleRespond($data);
    }

    private function wordCloudsMessageTable($keywords, $wordclouds, $countNumberTotal)
    {

        $data = null;
        foreach ($wordclouds as $wordcloud) {
            $data[] = [
                'keyword' => $wordcloud->text,
                'keyword_id' => $wordcloud->keyword_id,
                'keyword_name' => self::matchKeywordName($keywords, $wordcloud->keyword_id),
                'total' => $wordcloud->value,
                'percent' => (float)self::point_two_digits(($wordcloud->value / $countNumberTotal), 2)
            ];
        }
        return $data;
    }

    public function wordCloudsPlateform(Request $request)
    {
        $data = null;
        $campaign_id = $this->campaign_id ?? "";

        //$select = $request->select ?? null;
        $keywords = self::findKeywords($campaign_id, $this->keyword_id);
        $limit = self::selectData($request->select);
        if ($limit == 0) {
            $limit = 100;
        }
        $sourceQuery = "";
        $wordQuery = "";
        if ($request->platform_id) {
            $this->source_id = $request->platform_id;
            $sourceQuery = "source_id = " . $this->source_id . " and ";
        }

        if ($request->word) {
            $wordQuery = "word Like '%" . $request->word . "%' and ";
        }

        $sources = DB::table('sources')->where('status', 1)->pluck('id')->toArray();
        $sourceId = implode(",", $sources);

        $wordclouds = DB::select("SELECT message_id,author,keyword_id,source_id,date_count,
    word as text,
    SUM(count_number) AS value
FROM
    tbl_word_clouds
WHERE
    word != 'https' AND word != 'http' and
    message_id != '' and
    source_id IN ($sourceId) AND
    $sourceQuery$wordQuery
keyword_id IN (" . implode(",", $keywords->pluck('id')->toArray()) . ") AND
    date_count between '$this->start_date 00:00:00' AND '$this->end_date 23:59:59'
GROUP BY
    text ORDER BY value desc limit $limit;");

        //$wordclouds = $raw_total->orderBy('count_number', 'desc')->get();
        $data['word_clouds_platform'] = $wordclouds;// self::wordCloudsMessage($this->wordCloudsData($wordclouds), $select);
        $data['wordCloudByAccount'] = $this->wordCloudByAccount($wordclouds);
        return parent::handleRespond($data);
    }

    private function wordCloudByAccount($wordclouds)
    {
        $sources = Sources::where('status', 1)->get();

        //SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as total')
        $messageIds = [];
        $data = [];
        foreach ($wordclouds as $wordcloud) {
            $messageIds[] = $wordcloud->message_id;
            $data[] = [
                'author' => $wordcloud->author,
                'source_id' => $wordcloud->source_id,
                'source_name' => self::matchSourceName($sources, $wordcloud->source_id),
                'image'=> self::matchSourceImage($sources, $wordcloud->source_id),
                'total_message' => $wordcloud->value,
                'message_id' => $wordcloud->message_id,
                'engagements' => 0,
                'date_count' => $wordcloud->date_count
            ];
        }


        return $this->getEngagements($data, $messageIds);
    }

    private function getEngagements($data, $messageIds)
    {
        $engagements = DB::table('messages')
            ->select("message_id", DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions + number_of_views) as total'))
            ->whereIn('message_id', $messageIds)->groupBy('message_id')
            ->get();
        $result = [];
        foreach ($data as $key => $value) {
            foreach ($engagements as $engagement) {
                if ($value['message_id'] == $engagement->message_id) {
                    $data[$key]['engagements'] = intval($engagement->total);
                    break;
                }
            }
            $result = $data;
        }
        return $result;
    }

    public function wordCloudsPosition(Request $request)
    {
        $data = null;
        $campaign_id = $this->campaign_id ?? "";

        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $select = $request->select ?? null;
        $keywords = self::findKeywords($campaign_id, $this->keyword_id);
        $classificationQuery = "";
        $sourceQuery = "";
        if ($request->sentiment_type) {
            $classificationQuery = "classification_id = 1 and ";
            if ($request->sentiment_type !== 'positive') {
                $classificationQuery = "classification_id = 2 and ";
            }
        }
        if ($request->platform_id) {
            $this->source_id = $request->platform_id;
            $sourceQuery = "source_id = " . $this->source_id . " and ";
        }

        $limit = self::selectData($request->select);
        if ($limit == 0) {
            $limit = 100;
        }

        $sources = DB::table('sources')->where('status', 1)->pluck('id')->toArray();
        $sourceId = implode(",", $sources);

        $wordclouds = DB::select("SELECT message_id,author,keyword_id,source_id,date_count,
    word as text,
    SUM(count_number) AS value
FROM
    tbl_word_clouds
WHERE
    word != 'https' AND word != 'http' and
    message_id != '' and
    source_id IN ($sourceId) AND
    $sourceQuery$classificationQuery
keyword_id IN (" . implode(",", $keywords->pluck('id')->toArray()) . ") AND
    date_count between '$this->start_date 00:00:00' AND '$this->end_date 23:59:59'
GROUP BY
    text ORDER BY value desc limit $limit;");

        $data['word_clouds_position'] = $wordclouds;//ฝฝ self::wordCloudsMessage($this->wordCloudsData($wordclouds), $select);
        $data['wordCloudBySentimentType'] = $this->WordCloudBySentimentType($wordclouds);
        //        $data['total'] = $this->wordCloudBySentimentType($request, true);

        return parent::handleRespond($data);
    }

    private function wordCloudBySentimentType($wordclouds)
    {

        $sources = Sources::where('status', 1)->get();
        $messageIds = [];
        $data = [];
        foreach ($wordclouds as $wordcloud) {
            $messageIds[] = $wordcloud->message_id;
            $data[] = [
                'author' => $wordcloud->author,
                'source_id' => $wordcloud->source_id,
                'source_name' => self::matchSourceName($sources, $wordcloud->source_id),
                'image'=> self::matchSourceImage($sources, $wordcloud->source_id),
                'total_message' => $wordcloud->value,
                'message_id' => $wordcloud->message_id,
                'date_count' => $wordcloud->date_count,
                'engagements' => 0
            ];
        }

        return $this->getEngagements($data, $messageIds);
    }
}