<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use App\Models\MessageResultFullData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OverAllExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    private $report;

    public function __construct($report)
    {
        $this->report = $report;
    }

    public function headings(): array
    {
        return [
            "Message ID", 
            "Keyword Name", 
            "Message Detail", 
            "Account Name",
            "Post Date",
            "Post Time",
            "Day",
            "Message Type",
            "Device",
            "Channel",
            "Source Name",
            "Link Message",
            "Sentiment",
            "Bully Type",
            "Bully Level",
        ];
    }
    
    public function collection()
    {
        $items = $this->report->get();
        
        $anylsys = [];

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');

            $anylsys[$item->message_id]["message_id"] = $item->message_id;
            $anylsys[$item->message_id]["keyword_name"] = $item->keyword_name;
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

        // foreach ($anylsys as $anylsy) {
        //     $data[] = $anylsy;
        // }

        return collect($anylsys);
    }

}
