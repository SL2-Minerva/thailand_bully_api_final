<?php

namespace App\Http\Controllers\report;

use App\Exports\BullyExport;
use App\Exports\ChannelExport;
use App\Exports\EngagementExport;
use App\Exports\MonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageResult;
use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\OverAllExport;
use App\Exports\SentimentExport;
use App\Exports\VoiceExport;
use Maatwebsite\Excel\Facades\Excel;

class LevelthreeController extends Controller
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
        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        $this->source_id = $request->source === "all" ? "" : $request->source;

        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }

        if ($request->period === 'customrange') {
            $this->start_date_previous = $this->date_carbon($request->start_date_period);
            $this->end_date_previous = $this->date_carbon($request->end_date_period);
        }

        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();

            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

    }

    private function messageFullData($start_date, $end_date, $campaign_id, $report_number = null)
    {
        $keywords = self::findKeywords($campaign_id, $this->keyword_id);
        if ($report_number === '2.2.013') {
            $data = DB::table('messages')
                ->select([
                    'messages.message_id AS message_id',
                    'messages.message_datetime AS date_m',
                    'messages.author AS author',
                    'messages.keyword_id AS keyword_id',
                    'messages.full_message AS full_message',
                    'messages.link_message AS link_message',
                    'messages.message_type AS message_type',
                    'messages.device AS device',
                    'message_results_2.media_type',
                    'message_results_2.classification_sentiment_id',
                    'message_results_2.classification_type_id',
                    'message_results_2.classification_level_id',
                    /*,
                'keywords.name AS keyword_name',
                'sources.name AS source_name',
                'campaigns.name AS campaign_name',
                'classifications.name AS classification_name',
                'classifications.classification_type_id AS classification_type_id'*/
                ])
                /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
                ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
                ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
                ->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
                /*->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
                ->where('campaign_id', $campaign_id)*/
                ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                //->whereIn('message_results.classification_type_id', [1, 2, 3])
                ->whereIn("keyword_id", $keywords->pluck('id')->toArray())
                ->whereNotNull('author')->groupBy('author')
                ->orderBy('date_m', 'ASC');
        } else {

            $data = DB::table('messages')
                ->select([
                    'messages.message_id AS message_id',
                    'messages.message_datetime AS date_m',
                    'messages.author AS author',
                    'messages.keyword_id AS keyword_id',
                    'messages.full_message AS full_message',
                    'messages.link_message AS link_message',
                    'messages.message_type AS message_type',
                    'messages.device AS device',
                    'message_results.classification_type_id'/*,
                    /*'campaigns.name AS campaign_name',
                    'keywords.name AS keyword_name',
                    'sources.name AS source_name',
                    'classifications.name AS classification_name',
                    'classifications.classification_type_id AS classification_type_id'*/
                ])
                /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
                ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
                ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
                ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
                /*->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
                ->where('campaign_id', $campaign_id)*/
                ->whereIn("keyword_id", $keywords->pluck('id')->toArray())
                ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                ->whereIn('message_results.classification_type_id', [1, 2, 3])
                ->orderBy('date_m', 'ASC')->limit(10000);
        }

        return $data;
    }

    public function exportOverAll(Request $request)
    {
        //$source = parent::listSource();
        $report = $this->messageFullData($this->start_date, $this->end_date, $this->campaign_id, $request->report_number);
        return Excel::download(new OverAllExport($report), 'Overall-' . Carbon::now() . '.xlsx');
    }

    public function exportVoice(Request $request)
    {
        //$source = parent::listSource();
        $report = $this->messageFullData($this->start_date, $this->end_date, $this->campaign_id, $request->report_number);
        return Excel::download(new VoiceExport($report, $request->report_number), 'Voice-' . Carbon::now() . '.xlsx');
    }

    public function exportChannel(Request $request)
    {
        //$source = parent::listSource();
        $report = $this->messageFullData($this->start_date, $this->end_date, $this->campaign_id, $request->report_number);
        return Excel::download(new ChannelExport($report, $request->report_number), 'Channel-' . Carbon::now() . '.xlsx');
    }

    public function exportEngagement(Request $request)
    {
        //  $source = parent::listSource();
        $report = $this->messageFullData($this->start_date, $this->end_date, $this->campaign_id, $request->report_number);
        return Excel::download(new EngagementExport($report, $request->report_number), 'Engagement-' . Carbon::now() . '.xlsx');
    }

    public function exportSentiment(Request $request)
    {
        //    $source = parent::listSource();
        $report = $this->messageFullData($this->start_date, $this->end_date, $this->campaign_id, $request->report_number);
        return Excel::download(new SentimentExport($report, $request->report_number), 'Sentiment-' . Carbon::now() . '.xlsx');
    }

    public function exportBully(Request $request)
    {
        //$source = parent::listSource();
        $report = $this->messageFullData($this->start_date, $this->end_date, $this->campaign_id, $request->report_number);
        return Excel::download(new BullyExport($report, $request->report_number), 'Bully-' . Carbon::now() . '.xlsx');
    }

}


