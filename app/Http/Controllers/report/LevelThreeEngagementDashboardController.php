<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LevelThreeEngagementDashboardController extends Controller
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
            $this->start_date_previous =  $this->date_carbon($request->start_date_period);
            $this->end_date_previous =  $this->date_carbon($request->end_date_period);
        }

    }


    public function report(Request $request) {

        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $label = str_replace("+", " ", $request->label);
        $Llabel = str_replace("+", " ", $request->Llabel);

        $total = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1])
            ->orderBy('date_m', 'ASC')
            ->offset($start)->limit($limit);

        // fillter by date
        if ($request->report_number === '4.2.002' ||
            $request->report_number === '4.2.012'
        ) {

            $date_request = Carbon::createFromFormat('d/m/Y', $request->label)->format('Y-m-d');

            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$date_request . " 00:00:00", $date_request . " 23:59:59"])
                ->whereIn('classification_type_id', [1])
                ->orderBy('date_m', 'ASC')
                ->offset($start)->limit($limit);


            $total = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$date_request . " 00:00:00", $date_request . " 23:59:59"])
                ->whereIn('classification_type_id', [1]);

            if ($request->report_number === '4.2.002') {
                $total->where('keyword_name', $Llabel);
                $raw->where('keyword_name', $Llabel);
            }

            if ($request->report_number === '4.2.012') {
                if ($Llabel === 'Comment') {
                    $raw->where('number_of_comments', '>', 0);
                    $total->where('number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reactions') {
                    $raw->where('number_of_reactions', '>', 0);
                    $total->where('number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('number_of_shares', '>', 0);
                    $total->where('number_of_shares', '>', 0);
                }

                if ($Llabel === 'Views') {
                    $raw->where('number_of_views', '>', 0);
                    $total->where('number_of_views', '>', 0);
                }

            }

        }

        if ($request->report_number === '4.2.003' ||
            $request->report_number === '4.2.013'
        ) {
            $raw->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$label]);
            // return $raw->get();
            $total->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$label]);

            if ($request->report_number === '4.2.013') {
                if ($Llabel === 'Comment') {
                    $raw->where('number_of_comments', '>', 0);
                    $total->where('number_of_comments', '>', 0);
                }

                if ($Llabel === 'reactions') {
                    $raw->where('number_of_reactions', '>', 0);
                    $total->where('number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('number_of_shares', '>', 0);
                    $total->where('number_of_shares', '>', 0);
                }

                if ($Llabel === 'Views') {
                    $raw->where('number_of_views', '>', 0);
                    $total->where('number_of_views', '>', 0);
                }

            }

        }

        // fillter by time before ...
        if ($request->report_number === '4.2.004' ||
            $request->report_number === '4.2.014'
        ) {

            if ($label === 'Before 6 AM') {

                $raw->whereRaw('HOUR(date_m) < ?', [6]);
                $total->whereRaw('HOUR(date_m) < ?', [6]);

            }

            if ($label === '6 AM-12 PM') {
                $raw->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [6, 12]);
                $total->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [6, 12]);
            }

            if ($label === '12 PM-6 PM') {
                $raw->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [12, 18]);
                $total->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [12, 18]);
            }

            if ($label === 'After 6 PM') {
                $raw->whereRaw('HOUR(date_m) >= ?', [18]);
                $total->whereRaw('HOUR(date_m) >= ?', [18]);
            }

            if ($request->report_number === '4.2.014') {
                if ($Llabel === 'Comment') {
                    $raw->where('number_of_comments', '>', 0);
                    $total->where('number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reactions') {
                    $raw->where('number_of_reactions', '>', 0);
                    $total->where('number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('number_of_shares', '>', 0);
                    $total->where('number_of_shares', '>', 0);
                }

                if ($Llabel === 'Views') {
                    $raw->where('number_of_views', '>', 0);
                    $total->where('number_of_views', '>', 0);
                }

            }

        }

        if ($request->report_number === '4.2.005' ||
            $request->report_number === '4.2.015'
        ) {
            $target = 'dddddd';


            if ($label === 'Andriod' || $label === 'Android') {
                $target = 'android';
            }

            if ($label === 'Iphone') {
                $target = 'iphone';
            }

            if ($label === 'Web App') {
                $target = 'webapp';
            }

            $raw->where('device', $target);
            $total->where('device', $target);

            if ($request->report_number === '4.2.015') {
                if ($Llabel === 'Comment') {
                    $raw->where('number_of_comments', '>', 0);
                    $total->where('number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reactions') {
                    $raw->where('number_of_reactions', '>', 0);
                    $total->where('number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('number_of_shares', '>', 0);
                    $total->where('number_of_shares', '>', 0);
                }

                if ($Llabel === 'Views') {
                    $raw->where('number_of_views', '>', 0);
                    $total->where('number_of_views', '>', 0);
                }

            }

        }

        if ($request->report_number === '4.2.006' ||
            $request->report_number === '4.2.016'
        ) {
            if ($label === 'Influencer') {

                $raw->where('reference_message_id', '');
                $total->where('reference_message_id', '');

            } else {

                $raw->where('reference_message_id', '!=', '');
                $total->where('reference_message_id', '!=', '');
            }

            if ($request->report_number === '4.2.016') {
                if ($Llabel === 'Comment') {
                    $raw->where('number_of_comments', '>', 0);
                    $total->where('number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reaction') {
                    $raw->where('number_of_reactions', '>', 0);
                    $total->where('number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('number_of_shares', '>', 0);
                    $total->where('number_of_shares', '>', 0);
                }

                if ($Llabel === 'Views') {
                    $raw->where('number_of_views', '>', 0);
                    $total->where('number_of_views', '>', 0);
                }

            }


        }

        if ($request->report_number === '4.2.007' ||
            $request->report_number === '4.2.017'
        ) {
            $raw->where('source_name', $request->label);
            $total->where('source_name', $request->label);

            if ($request->report_number === '4.2.017') {
                if ($Llabel === 'Comment') {
                    $raw->where('number_of_comments', '>', 0);
                    $total->where('number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reaction') {
                    $raw->where('number_of_reactions', '>', 0);
                    $total->where('number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('number_of_shares', '>', 0);
                    $total->where('number_of_shares', '>', 0);
                }

                if ($Llabel === 'Views') {
                    $raw->where('number_of_views', '>', 0);
                    $total->where('number_of_views', '>', 0);
                }
            }

        }

        if ($request->report_number === '4.2.008') {
            if ($request->label === "Share of Voice") {
                $raw->where('number_of_shares', '>', 0);
                $total->where('number_of_shares', '>', 0);
            }

            if ($request->Llabel === "Comments") {
                $raw->where('number_of_comments', '>', 0);
                $total->where('number_of_comments', '>', 0);
            }

            if ($request->Llabel === "Reaction") {
                $raw->where('number_of_reactions', '>', 0);
                $total->where('number_of_reactions', '>', 0);
            }

            if ($request->Llabel === "Views") {
                $raw->where('number_of_views', '>', 0);
                $total->where('number_of_views', '>', 0);
            }

        }

        if (isset($request->keyword_id)) {
            if ($request->report_number === '4.2.007' ||
                $request->report_number === '4.2.006' ||
                $request->report_number === '4.2.005' ||
                $request->report_number === '4.2.004' ||
                $request->report_number === '4.2.003' ||
                $request->report_number === '4.2.002'
            ) {
                $raw->where('keyword_id', $request->keyword_id);
                $total->where('keyword_id', $request->keyword_id);
            }
        }

        $items = $raw->get();

        $parents = [];
        foreach ($items as $item) {
            if ($item->reference_message_id) {
                if (array_search($item->reference_message_id, $parents) === false) {
                    $parents[] = $item->reference_message_id;
                }
            }
        }


        foreach ($items as  $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->message_id);
            $parent = null;

            if (array_search($item->message_id, $parents) !== false) {
                $parent = $item->message_id;
            }

            $data_push = [
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('H:i'),
                "day" => $date_d,
                "message_type" => $item->message_type,
                "device" => $item->device,
                "channel" => $item->source_name,
                "source_name" => $item->source_name,
                "link_message" => $item->link_message,
                "parent" => $parent
            ];


            // loop for get classification name
            foreach ($types as $type) {
                if ($type->classification_type_id == 1) {
                    $data_push['sentiment'] = $type->classification_name;
                }

                if ($type->classification_type_id == 2) {
                    $data_push['bully_type'] = $type->classification_name;
                }

                if ($type->classification_type_id == 3) {
                    $data_push['bully_level'] = $type->classification_name;
                }
            }

            $data['message'][$item->message_id] = $data_push;
        }


        if (isset($data['message'])) {
            $data['message'] = array_values($data['message']);
        }

        $data['total'] = $total->get()->count();

        return parent::handleRespond($data);
    }

    private function getClassificationName($message_id)
    {
        return DB::table('message_result_full_data')->where('message_id', $message_id)
            ->limit(3)->get(['classification_type_id', 'classification_name']);
    }
}
