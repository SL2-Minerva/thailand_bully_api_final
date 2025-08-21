<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class LevethreeOverAllDashboardController extends Controller
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
    }

    public function dailyMessageLevelThree(Request $request)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $raw = DB::table('message_results')
            //->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->where('classification_type_id', 1);


        if ($request->message_id) {
            $raw->where('message_id', $request->message_id);

        }

        if ($request->report_number === '1.2.002'
        ) {

            $date_request = Carbon::createFromFormat('d/m/Y', $request->label)->format('Y-m-d');
            $raw->whereBetween('date_m', [$date_request . " 00:00:00", $date_request . " 23:59:59"]);
        }


        if (isset($request->keyword_id)) {
            $raw->where('keyword_id', $request->keyword_id);
        }

        if (isset($request->meesage_id)) {
            $raw->where('message_id', $request->meesage_id);
        }
        $total = $raw->count();
        $items = $raw->orderBy('date_m', 'ASC')
            ->offset($start)->limit($limit)->get();

        $parents = [];
        foreach ($items as $item) {
            if ($item->reference_message_id) {
                if (array_search($item->reference_message_id, $parents) === false) {
                    $parents[] = $item->reference_message_id;
                }
            }
        }


        foreach ($items as $ke => $item) {
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

        $data['count'] = count($data['message']);
        $data['total'] = $total;

        return parent::handleRespond($data);
    }

    private function getClassificationName($message_id)
    {
        return DB::table('message_result_full_data')->where('message_id', $message_id)
            ->limit(3)->get(['classification_type_id', 'classification_name']);
    }
}
