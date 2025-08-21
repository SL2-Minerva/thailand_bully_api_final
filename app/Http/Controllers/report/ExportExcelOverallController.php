<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\UserOrganizationGroup;
use Illuminate\Support\Carbon;
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportExcelOverallController extends Controller
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

        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();

            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

    }

    public function ExportOverall(Request $request)
    {
        $source = parent::listSource();
        $report = $this->messageFullData($this->start_date, $this->end_date, $this->campaign_id);

        if ($this->keyword_id) {
            $report->whereIn('keyword_id', $this->keyword_id);
        }

        // if ($this->source_id) {
        //     $report->where('source_id', $this->source_id);
        // }

        if ($this->source_id) {
            if (is_array($this->source_id)) {
                $report->whereIn('source_id', $this->source_id);
            } else {
                $report->where('source_id', $this->source_id);
            }
        }        

        $items = $report->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Message ID');
        $sheet->setCellValue('B1', 'Message Detail');
        $sheet->setCellValue('C1', 'Account Name');
        $sheet->setCellValue('D1', 'Post Date');
        $sheet->setCellValue('E1', 'Post Time');
        $sheet->setCellValue('F1', 'Day');
        $sheet->setCellValue('G1', 'Message Type');
        $sheet->setCellValue('H1', 'Device');
        $sheet->setCellValue('I1', 'Channel');
        $sheet->setCellValue('J1', 'Source Name');
        $sheet->setCellValue('K1', 'Link Message');
        $sheet->setCellValue('L1', 'Parent');
        $sheet->setCellValue('M1', 'Sentiment');
        $sheet->setCellValue('N1', 'Bully Type');
        $sheet->setCellValue('O1', 'Bully Level');

        $rowcount = 2;
        
        $anylsys = [];

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');

            $anylsys[$item->message_id]["message_id"] = $item->message_id;
            $anylsys[$item->message_id]["message_detail"] = $item->full_message;
            $anylsys[$item->message_id]["account_name"] = $item->author;
            $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
            $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('H:i');
            $anylsys[$item->message_id]["day"] = $date_d;
            $anylsys[$item->message_id]["message_type"] = $item->message_type;
            $anylsys[$item->message_id]["device"] = $item->device;
            $anylsys[$item->message_id]["channel"] = $item->source_name;
            $anylsys[$item->message_id]["source_name"] = $item->source_name;
            $anylsys[$item->message_id]["link_message"] = $item->link_message;
            $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
        }

        foreach ($anylsys as $anylsy) {
            // $data[] = $anylsy;
            $sheet->setCellValue('A'.$rowcount, $anylsy['message_id'] ?? "");
            $sheet->setCellValue('B'.$rowcount, $anylsy['message_detail'] ?? "");
            $sheet->setCellValue('C'.$rowcount, $anylsy['account_name'] ?? "");
            $sheet->setCellValue('D'.$rowcount, $anylsy['post_date'] ?? "");
            $sheet->setCellValue('E'.$rowcount, $anylsy['post_time'] ?? "");
            $sheet->setCellValue('F'.$rowcount, $anylsy['day'] ?? "");
            $sheet->setCellValue('G'.$rowcount, $anylsy['message_type'] ?? "");
            $sheet->setCellValue('H'.$rowcount, $anylsy['device'] ?? "");
            $sheet->setCellValue('I'.$rowcount, $anylsy['channel'] ?? "");
            $sheet->setCellValue('J'.$rowcount, $anylsy['source_name'] ?? "");
            $sheet->setCellValue('K'.$rowcount, $anylsy['link_message'] ?? "");
            $sheet->setCellValue('L'.$rowcount, $anylsy['parent'] ?? "");
            $sheet->setCellValue('M'.$rowcount, $anylsy[1] ?? "");
            $sheet->setCellValue('N'.$rowcount, $anylsy[2] ?? "");
            $sheet->setCellValue('O'.$rowcount, $anylsy[3] ?? "");
            $rowcount++;
        }
        
        $final_filename = 'test.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.urlencode($final_filename).'"');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

    }

    private function messageFullData($start_date, $end_date, $campaign_id)
    {
        $data = DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date. " 00:00:00", $end_date. " 23:59:59"])
            ->whereIn('classification_type_id', [1, 2, 3])
            ->select(
                'message_id',
                'date_m',
                'author',
                'source_name',
                'full_message',
                'link_message',
                'message_type',
                'device',
                'classification_name',
                'classification_type_id',
            )
            ->orderBy('date_m', 'ASC');

        return $data;
    }
}
