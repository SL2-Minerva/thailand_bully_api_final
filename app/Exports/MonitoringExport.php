<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use App\Models\MessageResultFullData;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MonitoringExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */

    private $report;
    private $report_type;
    private $sources;
    private $keywords;

    private $headingDailyMessage = [
        "No.",
        "Message Detail",
        "Message Type",
        "Account Name",
        "Post Time",
        "Scraping Time",
        "Channel",
        // "Engagement",
        "Views",
        "Comments",
        "Reactions",
        "Shares",
        "Media Type",
        "Sentiment",
        "Bully Type",
        "Bully Level",
        "Link"
    ];
    private $headingSentiment = [
        "No.",
        "Account Name",
        "Channel",
        "Total Post",
        // "Total Engagement",
        "Views",
        "Comments",
        "Reactions",
        "Shares",
        "Media Type",
        "Positive",
        "Normal",
        "Negative"
    ];
    private $headingEngagement = [
        "No.",
        "Keyword",
        "Account Name",
        "Message Detail",
        "Post Time",
        "Scraping Time",
        "Channel",
        // "Engagement",
        "Views",
        "Comments",
        "Reactions",
        "Shares",
        "Media Type",
        "Sentiment",
        "Bully Level",
        "Bully Type",
    ];


    public function __construct($report, $report_type, $sources = null, $keywords = null)
    {
        $this->report = $report;
        $this->sources = $sources;
        $this->keywords = $keywords;
        $this->report_type = $report_type;
    }

    public function headings(): array
    {
        if ($this->report_type === 'dailyMessage') {

            return $this->headingDailyMessage;
        } else if ($this->report_type === 'sentiment') {
            return $this->headingSentiment;
        } else {
            return $this->headingEngagement;
        }
    }

    protected function matchSourceName($source, $sourceId)
    {
        foreach ($source as $item) {
            if ($item->id == $sourceId) {
                return $item->name;
            }
        }
        return "";
    }


    protected function matchKeywordName($keyword, $keywordId)
    {
        foreach ($keyword as $item) {
            if ($item->id == $keywordId) {
                return $item->name;
            }
        }
        return "";
    }

    protected function matchSourceImage($source, $sourceId)
    {
        foreach ($source as $item) {
            if ($item->id == $sourceId) {
                return $item->image;
            }
        }
        return "";
    }

    protected function matchMediaType($mediaTypes, $mediaTypeId)
    {
        foreach ($mediaTypes as $id => $label) {
            if ($id == $mediaTypeId) {
                return $label;
            }
        }
        return "";
    }

    protected function matchBullyType($bullyTypes, $bullyTypeId)
    {
        foreach ($bullyTypes as $id => $label) {
            if ($id == $bullyTypeId) {
                return $label;
            }
        }
        return "";
    }

    public function collection()
    {
        $bullytype = [
            1 => 'Positive',
            2 => 'Negative',
            3 => 'Neutral',
            4 => 'NoBully',
            5 => 'Physical Bully',
            6 => 'Verbal Bullying',
            7 => 'Social Bullying',
            8 => 'Cyber Bullying',
            9 => 'Level 0',
            10 => 'Level 1',
            11 => 'Level 2',
            12 => 'Level 3',
        ];

        $media_type = [
            1 => 'Text',
            2 => 'Image',
            3 => 'Voice',
            4 => 'Video',
        ];

        $excel = [];
        if ($this->report_type === 'dailyMessage') {
            foreach ($this->report as $position => $item) {
                $row = array();
                $row["no"] = $position + 1;
                $row["message_detail"] = $item->full_message;
                $row["message_type"] = $item->message_type;
                //$row["keyword_name"] = $this->matchKeywordName($this->keywords, $item->keyword_id);
                $row["account_name"] = $item->author;
                $row["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d, H:i');
                //$row["post_time"] = Carbon::parse($item->date_m)->format('H:i');
                /*$row["day"] = $date_d;
                $row["message_type"] = $item->message_type;*/
                // $row["device"] = $item->device;
                //$row["channel"] = $this->matchKeywordName($this->sources, $item->source_id);
                $row["scrape_date"] = Carbon::parse($item->scraping_time)->format('Y/m/d, H:i');
                $row["source_name"] = $this->matchKeywordName($this->sources, $item->source_id);
                // $row["source_image"] = $this->matchSourceImage($this->sources, $item->source_id);
                // $row["total_engagement"] = $item->total_engagement;
                $row["number_of_views"] = (string) ($item->number_of_views ?? "0");
                $row["number_of_comments"] = (string) ($item->number_of_comments ?? "0");
                $row["number_of_reactions"] = (string) ($item->number_of_reactions ?? "0");
                $row["number_of_shares"] = (string) ($item->number_of_shares ?? "0");
                $row["media_type"] = $this->matchMediaType($media_type, $item->media_type);
                $row["sentiment"] = $this->matchBullyType($bullytype, $item->sentiment);
                $row["bully_type"] = $this->matchBullyType($bullytype, $item->bully_type);
                $row["bully_level"] = $this->matchBullyType($bullytype, $item->bully_level);
                $row["link_message"] = $item->link_message;
                // $excel[$position][] = $row;
                $excel[] = $row;
                //error_log(json_encode($row));
            }
        } else if ($this->report_type === 'sentiment') {
            foreach ($this->report as $position => $item) {
                $row = array();
                $row["no"] = $position + 1;
                $row["account_name"] = $item["account_name"];
                $row["source_name"] = $item["source_name"];
                $row["source_image"] = $item["source_image"];
                $row["total_post"] = $item["total_post"];
                // $row["total_engagement"] = $item["total_engagement"];
                $row["number_of_views"] = (string) ($item->number_of_views ?? "0");
                $row["number_of_comments"] = (string) ($item->number_of_comments ?? "0");
                $row["number_of_reactions"] = (string) ($item->number_of_reactions ?? "0");
                $row["number_of_shares"] = (string) ($item->number_of_shares ?? "0");
                $row["media_type"] = $this->matchMediaType($media_type, $item["media_type"]);
                $row["negative"] = $this->matchBullyType($bullytype, $item["negative"]);
                $row["neutral"] = $this->matchBullyType($bullytype, $item["neutral"]);
                $row["positive"] = $this->matchBullyType($bullytype, $item["positive"]);
                // $excel[$position][] = $row;
                $excel[] = $row;
            }
        } else {
            foreach ($this->report as $position => $item) {
                $row = array();
                $row["no"] = $position + 1;
                $row["keyword_name"] = $item["keyword_name"];
                $row["account_name"] = $item["account_name"];
                $row["message_detail"] = $item["full_message"];
                $row["post_time"] = Carbon::parse($item["date_m"])->format('Y/m/d H:i');
                $row["scraping_time"] = Carbon::parse($item["scraping_at"])->format('Y/m/d, H:i');
                // $row["scrape_time"] = Carbon::parse($item["scraping_time"])->format('Y/m/d H:i');
                $row["source_name"] = $item["source_name"];
                $row["source_image"] = $item["source_image"];
                // $row["total_engagement"] = $item["total_engagement"];
                $row["number_of_views"] = (string) ($item->number_of_views ?? "0");
                $row["number_of_comments"] = (string) ($item->number_of_comments ?? "0");
                $row["number_of_reactions"] = (string) ($item->number_of_reactions ?? "0");
                $row["number_of_shares"] = (string) ($item->number_of_shares ?? "0");
                $row["media_type"] = $this->matchMediaType($media_type, $item["media_type"]);
                $row["sentiment"] = $this->matchBullyType($bullytype, $item["sentiment"]);
                $row["bully_level"] = $this->matchBullyType($bullytype, $item["bully_level"]);
                $row["bully_type"] = $this->matchBullyType($bullytype, $item["bully_type"]);
                // $excel[$position][] = $row;
                $excel[] = $row;
            }
        }
        return collect($excel);
    }

}
