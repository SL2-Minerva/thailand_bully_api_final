<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use App\Models\MessageResultFullData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VoiceExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    private $report;
    private $report_number;

    public function __construct($report, $report_number)
    {
        $this->report = $report;
        $this->report_number = $report_number;
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

        if ($this->report_number === '2.2.002' ||
            $this->report_number === '2.2.003' ||
            $this->report_number === '2.2.004' ||
            $this->report_number === '2.2.006' ||
            $this->report_number === '2.2.008' ||
            $this->report_number === '2.2.009' ||
            $this->report_number === '2.2.010' ||
            $this->report_number === '2.2.013'
        ) {
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
        }

        if ($this->report_number === '2.2.005') {
            foreach ($items as $item) {
                if ($item->device != '' || $item->device != null) {
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
            }
        }

        if ($this->report_number === '2.2.007') {
            foreach ($items as $item) {
                if ($item->source_name) {
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
            }
        }

        // if ($this->report_number === '2.2.008') {
        //     foreach ($items as $item) {
        //         if ($item->classification_type_id === 1) {
        //             $date_d = Carbon::parse($item->date_m)->format('D');
        
        //             $anylsys[$item->message_id]["message_id"] = $item->message_id;
        //             $anylsys[$item->message_id]["message_detail"] = $item->full_message;
        //             $anylsys[$item->message_id]["account_name"] = $item->author;
        //             $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
        //             $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('H:i');
        //             $anylsys[$item->message_id]["day"] = $date_d;
        //             $anylsys[$item->message_id]["message_type"] = $item->message_type;
        //             $anylsys[$item->message_id]["device"] = $item->device;
        //             $anylsys[$item->message_id]["channel"] = $item->source_name;
        //             $anylsys[$item->message_id]["source_name"] = $item->source_name;
        //             $anylsys[$item->message_id]["link_message"] = $item->link_message;
        //             $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
        //         }
        //     }
        // }

        // if ($this->report_number === '2.2.009') {
        //     foreach ($items as $item) {
        //         if ($item->classification_type_id === 3) {
        //             $date_d = Carbon::parse($item->date_m)->format('D');
        
        //             $anylsys[$item->message_id]["message_id"] = $item->message_id;
        //             $anylsys[$item->message_id]["message_detail"] = $item->full_message;
        //             $anylsys[$item->message_id]["account_name"] = $item->author;
        //             $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
        //             $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('H:i');
        //             $anylsys[$item->message_id]["day"] = $date_d;
        //             $anylsys[$item->message_id]["message_type"] = $item->message_type;
        //             $anylsys[$item->message_id]["device"] = $item->device;
        //             $anylsys[$item->message_id]["channel"] = $item->source_name;
        //             $anylsys[$item->message_id]["source_name"] = $item->source_name;
        //             $anylsys[$item->message_id]["link_message"] = $item->link_message;
        //             $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
        //         }
        //     }
        // }

        // if ($this->report_number === '2.2.010') {
        //     foreach ($items as $item) {
        //         if ($item->classification_type_id === 2) {
        //             $date_d = Carbon::parse($item->date_m)->format('D');
        
        //             $anylsys[$item->message_id]["message_id"] = $item->message_id;
        //             $anylsys[$item->message_id]["message_detail"] = $item->full_message;
        //             $anylsys[$item->message_id]["account_name"] = $item->author;
        //             $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
        //             $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('H:i');
        //             $anylsys[$item->message_id]["day"] = $date_d;
        //             $anylsys[$item->message_id]["message_type"] = $item->message_type;
        //             $anylsys[$item->message_id]["device"] = $item->device;
        //             $anylsys[$item->message_id]["channel"] = $item->source_name;
        //             $anylsys[$item->message_id]["source_name"] = $item->source_name;
        //             $anylsys[$item->message_id]["link_message"] = $item->link_message;
        //             $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
        //         }
        //     }
        // }

        // foreach ($anylsys as $anylsy) {
        //     $data[] = $anylsy;
        // }

        return collect($anylsys);
    }
}
