<?php

namespace App\Http\Controllers\report;

use App\Exports\MonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Message;
use App\Models\MessageDeleteLog;
use App\Models\Organization;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LevelThreeTableController extends Controller
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

        if ($request->period === 'customrange') {
            $this->start_date_previous = $this->date_carbon($request->start_date_period);
            $this->end_date_previous = $this->date_carbon($request->end_date_period);
        }

    }

    // function parseLabelClassification($Llabel)
    // {
    //     //error_log("parseLabelClassification:" . $Llabel);
    //     $result = -1;
    //     if ($Llabel == "")
    //         return $result;
    //     if ($Llabel === 'Positive') {
    //         $result = 1;
    //     } else if ($Llabel === 'Neutral') {
    //         $result = 3;
    //     } else if ($Llabel === 'Negative') {
    //         $result = 2;
    //     } else if ($Llabel === 'NoBully' || $Llabel === 'No Bully') {
    //         $result = 4;
    //     } else if ($Llabel === 'Gossip') {
    //         $result = 5;
    //     } else if ($Llabel === 'Harassment') {
    //         $result = 6;
    //     } else if ($Llabel === 'Exclusion') {
    //         $result = 7;
    //     } else if ($Llabel === 'HateSpeech' || $Llabel === 'Hate Speech') {
    //         $result = 8;
    //     } else if ($Llabel === 'Violence') {
    //         $result = 9;
    //     } else if ($Llabel === 'Level 0') {
    //         $result = 10;
    //     } else if ($Llabel === 'Level 1') {
    //         $result = 11;
    //     } else if ($Llabel === 'Level 2') {
    //         $result = 12;
    //     } else if ($Llabel === 'Level 3') {
    //         $result = 13;
    //     }

    //     return $result;

    // }

    function parseLabelClassification($Llabel)
    {
        //error_log("parseLabelClassification:" . $Llabel);
        $result = -1;
        if ($Llabel == "")
            return $result;
        if ($Llabel === 'Positive' || $Llabel === 'เชิงบวก') {
            $result = 1;
        } else if ($Llabel === 'Neutral' || $Llabel === 'เป็นกลาง') {
            $result = 3;
        } else if ($Llabel === 'Negative' || $Llabel === 'เชิงลบ') {
            $result = 2;
        } else if ($Llabel === 'NoBully' || $Llabel === 'No Bully' || $Llabel === 'ไม่มีการคุกคาม') {
            $result = 4;
        } else if ($Llabel === 'PhysicalBully' || $Llabel === 'Physical Bully' || $Llabel === 'การคุกคามทางร่างกาย') {
            $result = 5;
        } else if ($Llabel === 'VerbalBullying' || $Llabel === 'Verbal Bullying' || $Llabel === 'การคุกคามทางวาจา') {
            $result = 6;
        } else if ($Llabel === 'SocialBullying' || $Llabel === 'Social Bullying' || $Llabel === 'การคุกคามทางสังคม') {
            $result = 7;
        } else if ($Llabel === 'CyberBullying' || $Llabel === 'Cyber Bullying' || $Llabel === 'การคุกคามทางโลกออนไลน์') {
            $result = 8;
        } else if ($Llabel === 'Level 0' || $Llabel === 'ระดับ 0') {
            $result = 9;
        } else if ($Llabel === 'Level 1' || $Llabel === 'ระดับ 1') {
            $result = 10;
        } else if ($Llabel === 'Level 2' || $Llabel === 'ระดับ 2') {
            $result = 11;
        } else if ($Llabel === 'Level 3' || $Llabel === 'ระดับ 3') {
            $result = 12;
        }
        return $result;

    }

    private function parseLabelToEngagement($label, $raw)
    {
        if ($label == "")
            return $raw;
        if ($label === 'Comment') {
            $raw->where('messages.number_of_comments', '>', 0);
        } else if ($label === 'Reaction' || $label === 'reactions' || $label === 'Reactions') {
            $raw->where('messages.number_of_reactions', '>', 0);
        } else if ($label === 'Share') {
            $raw->where('messages.number_of_shares', '>', 0);
        } else if ($label === "Share of Voice") {
            $raw->where('messages.number_of_shares', '>', 0);
        } else if ($label === "Views") {
            $raw->where('messages.number_of_views', '>', 0);
        }
        return $raw;
    }

    private function parseTarget($label, $raw)
    {

        if ($label == "") {
            return $raw;
        }

        $target = "";
        if ($label === 'Andriod' || $label === 'Android') {
            $target = 'android';
        } else if ($label === 'Iphone') {
            $target = 'iphone';
        } else if ($label === 'Web App' || $label === 'Web+App') {
            $target = 'website';
        }

        if ($target != "")
            $raw->where('device', $target);
        return $raw;
    }

    public
        function messageLevelThree(
        Request $request
    ) {

        /*$classificationTypes = self::getClassificationJoinTypeMaster();
        $classification = self::getClassificationMaster();*/
        $sources = $this->getAllSource();
        $fillter_keywords = $request->keyword_id;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }
        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);

        $limit = $request->limit;
        $page = $request->page;
        if ($page == null || $page == 0)
            $page = 1;
        if ($limit == null || $limit == 0)
            $limit = 10;
        $offset = $limit * ($page - 1);

        /*         if ($request->report_number === '2.2.013') {
                    $date_request = Carbon::createFromFormat('d/m/Y', $request->label)->format('Y-m-d');
                    $this->start_date = $date_request;
                    $this->end_date = $date_request;
                    $data = $this->raw_account($request, $this->campaign_id, $date_request, $date_request, $request->report_number);
                    $total = $data['total'];
                } else { */
        $raw = self::messageLevelThreeRaw($request, $sources, $keywords);
        //error_log("raw:" . $raw->toSql());

        $total = $raw->count();
        //error_log("total:" . $total . ' $offset:' . $offset . ' $limit:' . $limit);
        $items = $raw->offset($offset)->limit($limit)->get();

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            // $types = $this->getClassificationName($item->message_id);
            $types = $this->getClassificationName($item->id);
            /*   if (
                  $request->report_number === '5.2.008' ||
                  $request->report_number === '5.2.009' ||
                  $request->report_number === '6.2.008' ||
                  $request->report_number === '6.2.018'
              ) {
                  $llValue = $request->Llabel;
              } else { */
            $parent = null;

            //if ($item->reference_message_id && $item->reference_message_id != '') {
            $parent = $item->message_id;

            //Match type
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

            $sourceName = $this->matchSourceName($sources, $item->source_id);
            $sourceImage = $this->matchSourceImage($sources, $item->source_id);
            $data_push = [
                "id" => $item->id,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('H:i'),
                "day" => $date_d,
                "message_type" => $item->message_type,
                "scrape_date" => Carbon::parse($item->scraping_time)->format('Y/m/d'),
                "scrape_time" => Carbon::parse($item->scraping_time)->format('H:i'),
                "device" => $item->device,
                "imageUrl" => $item->screen_capture_image,
                "channel" => $sourceName,
                "source_name" => $sourceName,
                "source_image" => $sourceImage,
                "link_message" => $item->link_message,
                "parent" => $parent,
                "engagement" => $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views,
            ];

            //loop for get classification name
            // foreach ($types as $type) {
            //     if ($type->classification_type_id == 1) {
            //         $data_push['sentiment'] = $type->classification_name;
            //     }

            //     if ($type->classification_type_id == 2) {
            //         $data_push['bully_type'] = $type->classification_name;
            //     }

            //     if ($type->classification_type_id == 3) {
            //         $data_push['bully_level'] = $type->classification_name;
            //     }
            // }

            //New loop table
            foreach ($types as $type) {
                if ($type->classification_sentiment_id) {
                    $data_push['sentiment'] = $bullytype[$type->classification_sentiment_id] ?? null;
                }

                if ($type->classification_type_id) {
                    $data_push['bully_type'] = $bullytype[$type->classification_type_id] ?? null;
                }

                if ($type->classification_level_id) {
                    $data_push['bully_level'] = $bullytype[$type->classification_level_id] ?? null;
                }
            }
            $data['message'][$item->id] = $data_push;
            //   }

            if (isset($data['message'])) {
                $data['message'] = array_values($data['message']);
            }
        }
        $data['total'] = $total;

        // }
        return parent::handleRespondPage($data, ["total_rows" => $total, "limit" => intval($limit), "page" => intval($page)]);

    }

    public
        function messageLevelThreeRaw(
        Request $request,
        $sources,
        $keywords
    ) {
        /*$classificationTypes = self::getClassificationJoinTypeMaster();
        $classification = self::getClassificationMaster();*/
        $isHasMessageDate = false;

        $label = str_replace("+", " ", $request->label);
        $Llabel = str_replace("+", " ", $request->Llabel);
        $ylabel = str_replace("+", " ", $request->ylabel);
        // error_log(json_encode('label'.$label));
        // error_log(json_encode('Llabel'.$Llabel));

        if (
            $request->report_number === '2.2.008' ||
            $request->report_number === '2.2.009' ||
            $request->report_number === '2.2.010' ||
            $request->report_number === '2.2.016' ||
            $request->report_number === '2.2.017' ||
            $request->report_number === '2.2.018' ||
            $request->report_number === '2.2.019' ||
            $request->report_number === '3.2.007' ||
            $request->report_number === '3.2.008' ||
            $request->report_number === '3.2.009' ||
            $request->report_number === '5.2.002' ||
            $request->report_number === '5.2.003' ||
            $request->report_number === '5.2.004' ||
            $request->report_number === '5.2.006' ||
            $request->report_number === '5.2.007' ||
            $request->report_number === '5.2.008' ||
            $request->report_number === '5.2.009' ||
            $request->report_number === '6.2.002' ||
            $request->report_number === '6.2.003' ||
            $request->report_number === '6.2.004' ||
            $request->report_number === '6.2.006' ||
            $request->report_number === '6.2.007' ||
            $request->report_number === '6.2.008' ||
            $request->report_number === '6.2.012' ||
            $request->report_number === '6.2.013' ||
            $request->report_number === '6.2.014' ||
            $request->report_number === '6.2.016' ||
            $request->report_number === '6.2.017' ||
            $request->report_number === '6.2.018'
        ) {
            $raw_class = DB::table('messages');
            // error_log(json_encode($request->report_number));
            $classification_valid = -1;
            if ($keywords) {
                if ($request->keyword_id) {
                    $raw_class->where('messages.keyword_id', $request->keyword_id);
                } else {

                    $keywordIds = $keywords->pluck('id')->all();
                    if ($keywordIds) {
                        $raw_class->whereIn('messages.keyword_id', $keywordIds);
                    }
                }
            }
            if ($label != '' || $Llabel != '' || $ylabel != '') {
                if ($this->source_id == null) {
                    $sourceId = $this->matchSourceByName($sources, $Llabel);
                    if ($sourceId) {
                        $this->source_id = $sourceId->id;
                    }
                }
                if ($classification_valid == -1)

                    if ($Llabel) {
                        $classification_1 = 0;
                        $classification_1 = self::parseLabelClassification($Llabel);
                        // error_log(json_encode($classification_1));
                        if ($classification_1 > 0) {
                            $classification_valid = 1;
                        }

                        $classification_type = '';
                        if ($classification_1 < 4) {
                            $classification_type = 'classification_sentiment_id';
                        } elseif ($classification_1 > 3 && $classification_1 < 9) {
                            $classification_type = 'classification_type_id';
                        } elseif ($classification_1 > 8) {
                            $classification_type = 'classification_level_id';
                        }
                    }

                if ($label) {
                    $classification_2 = 0;
                    $classification_2 = self::parseLabelClassification($label);
                    if ($classification_2 > 0) {
                        $classification_valid = 1;
                    }

                    $classification_type_2 = '';
                    if ($classification_2 < 4) {
                        $classification_type_2 = 'classification_sentiment_id';
                    } elseif ($classification_2 > 3 && $classification_2 < 9) {
                        $classification_type_2 = 'classification_type_id';
                    } elseif ($classification_2 > 8) {
                        $classification_type_2 = 'classification_level_id';
                    }
                }

                if ($ylabel) {
                    $classification_3 = 0;
                    $classification_3 = self::parseLabelClassification($ylabel);
                    if ($classification_3 > 0) {
                        $classification_valid = 1;
                    }

                    $classification_type_3 = '';
                    if ($classification_3 < 4) {
                        $classification_type_3 = 'classification_sentiment_id';
                    } elseif ($classification_3 > 3 && $classification_3 < 9) {
                        $classification_type_3 = 'classification_type_id';
                    } elseif ($classification_3 > 8) {
                        $classification_type_3 = 'classification_level_id';
                    }
                }
            }

            if ($classification_valid != -1) {
                // error_log(json_encode($classification_2));
                $raw_class->select([
                    'messages.id AS id',
                    'messages.message_id AS message_id',
                    'messages.reference_message_id AS reference_message_id',
                    'message_results_2.media_type',
                    'message_results_2.classification_sentiment_id',
                    'message_results_2.classification_type_id',
                    'message_results_2.classification_level_id',
                    // 'message_results.classification_type_id',
                    // 'message_results.classification_id',
                    'messages.created_at AS scraping_time',
                ]);
                $raw_class->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id');
                $raw_class->where('message_results_2.media_type', 1);

                if ($Llabel && $classification_1 > 0) {
                    $raw_class->where('message_results_2.' . $classification_type, $classification_1);
                }
                if ($label && $classification_2 > 0) {
                    $raw_class->where('message_results_2.' . $classification_type_2, $classification_2);
                }
                if ($ylabel && $classification_3 > 0) {
                    $raw_class->where('message_results_2.' . $classification_type_3, $classification_3);
                }
                // $raw_class->whereBetween('messages.created_at', [$this->start_date . " 00:00:01", $this->end_date . " 23:59:59"]);

                if (
                    $request->report_number === '6.2.002' ||
                    $request->report_number === '6.2.012'
                ) {
                    try {
                        $date = Carbon::createFromFormat('d/m/Y', $label);
                        $errors = Carbon::getLastErrors();

                        if ($date && $errors['warning_count'] === 0 && $errors['error_count'] === 0) {
                            $labelformat = $date->format('Y-m-d');
                        } else {
                            $labelformat = null;
                        }
                    } catch (\Exception $e) {
                        $labelformat = null;
                    }
                } else {
                    $labelformat = null;
                }
                if ($labelformat) {
                    $raw_class->whereBetween('messages.created_at', [$labelformat . " 00:00:01", $labelformat . " 23:59:59"]);
                } else
                    $raw_class->whereBetween('messages.created_at', [$this->start_date . " 00:00:01", $this->end_date . " 23:59:59"]);


                if ($this->source_id) {
                    if (is_array($this->source_id) && count($this->source_id) > 1) {
                        $raw_class->whereIn('source_id', $this->source_id);
                    } else {
                        $raw_class->where('source_id', is_array($this->source_id) ? $this->source_id[0] : $this->source_id);
                    }
                }

                $multiclassi_que = $raw_class->get();
            }
        }

        $raw = DB::table('messages');
        if ($keywords) {
            if ($request->keyword_id) {
                $raw->where('messages.keyword_id', $request->keyword_id);
            } else {

                $keywordIds = $keywords->pluck('id')->all();
                if ($keywordIds) {
                    $raw->whereIn('messages.keyword_id', $keywordIds);
                }
            }
        }

        $classification = -1;

        if ($label != "") {
            $sourceId = Sources::where('name', $label)->first();
            if ($sourceId) {
                $this->source_id = $sourceId->id;
            }
            if ($this->source_id == null) {
                $sourceId = $this->matchSourceByName($sources, $Llabel);
                if ($sourceId) {
                    $this->source_id = $sourceId->id;
                }
            }
            $classification = self::parseLabelClassification($label);
        }

        if ($Llabel != '') {
            if ($this->source_id == null) {
                $sourceId = $this->matchSourceByName($sources, $Llabel);
                if ($sourceId) {
                    $this->source_id = $sourceId->id;
                }
            }

            if ($classification == -1)
                $classification = self::parseLabelClassification($Llabel);
        }

        if ($ylabel != '') {
            if ($this->source_id == null) {
                $sourceId = $this->matchSourceByName($sources, $ylabel);
                if ($sourceId) {
                    $this->source_id = $sourceId->id;
                }
            }

            if ($classification == -1)
                $classification = self::parseLabelClassification($ylabel);
        }

        //error_log("classification:" . $classification . " / source_id :" . json_encode($this->source_id));
        if ($classification != -1) {
            $raw->select([
                'messages.id AS id',
                'messages.message_id AS message_id',
                'messages.reference_message_id AS reference_message_id',
                'messages.keyword_id AS keyword_id',
                'messages.message_datetime AS date_m',
                'messages.author AS author',
                'messages.source_id AS source_id',
                'messages.full_message AS full_message',
                'messages.message_type',
                'messages.link_message AS link_message',
                'messages.device AS device',
                'messages.screen_capture_image AS screen_capture_image',
                'messages.number_of_views AS number_of_views',
                'messages.number_of_comments AS number_of_comments',
                'messages.number_of_shares AS number_of_shares',
                'messages.number_of_reactions AS number_of_reactions',
                // 'message_results.classification_type_id',
                // 'message_results.classification_id',
                'message_results_2.media_type',
                'message_results_2.classification_sentiment_id',
                'message_results_2.classification_type_id',
                'message_results_2.classification_level_id',
                'messages.created_at AS scraping_time',
                DB::raw('COALESCE(tbl_messages.number_of_comments, 0) +
                    COALESCE(tbl_messages.number_of_shares, 0) +
                    COALESCE(tbl_messages.number_of_reactions, 0) +
                    COALESCE(tbl_messages.number_of_views, 0) AS total_engagement')

            ]);
            $raw->leftJoin('message_results_2', 'message_results_2.message_id', '=', 'messages.id');
            $raw->where('message_results_2.media_type', 1);
            // $raw->where('message_results_2.classification_id', $classification);
            if (isset($multiclassi_que) && count($multiclassi_que) > 0) {
                $multi_ids = $multiclassi_que->pluck('id')->all(); // ดึง id ทั้งหมดออกมา
                $raw->whereIn('messages.id', $multi_ids);
            }
        } else {
            $raw->select([
                'messages.id AS id',
                'messages.message_id AS message_id',
                'messages.reference_message_id AS reference_message_id',
                'messages.keyword_id AS keyword_id',
                'messages.message_datetime AS date_m',
                'messages.author AS author',
                'messages.source_id AS source_id',
                'messages.full_message AS full_message',
                'messages.message_type',
                'messages.link_message AS link_message',
                'messages.device AS device',
                'messages.screen_capture_image AS screen_capture_image',
                'messages.number_of_views AS number_of_views',
                'messages.number_of_comments AS number_of_comments',
                'messages.number_of_shares AS number_of_shares',
                'messages.number_of_reactions AS number_of_reactions',
                'messages.created_at AS scraping_time',
                DB::raw('COALESCE(tbl_messages.number_of_comments, 0) +
                    COALESCE(tbl_messages.number_of_shares, 0) +
                    COALESCE(tbl_messages.number_of_reactions, 0) +
                    COALESCE(tbl_messages.number_of_views, 0) AS total_engagement')

            ]);
        }


        if (isset($request->meesage_id)) {
            $raw->where('messages.message_id', $request->meesage_id);
        }

        if ($this->source_id) {
            if (is_array($this->source_id) && count($this->source_id) > 1) {
                $raw->whereIn('source_id', $this->source_id);
            } else {
                $raw->where('source_id', is_array($this->source_id) ? $this->source_id[0] : $this->source_id);
            }
        }

        $raw = $this->parseLabelToEngagement($label, $raw);
        $raw = $this->parseLabelToEngagement($Llabel, $raw);
        $raw = $this->parseTarget($label, $raw);
        $raw = $this->parseTarget($Llabel, $raw);

        if ($request->sort && $request->field) {
            // error_log("sort:" . $request->sort . " / field :" . $request->field);
            $field = $request->field;
            $field_table = match ($field) {
                'message_type' => "messages.message_type",
                'author' => "messages.author",
                'date' => "messages.message_datetime",
                'scrapingtime' => "messages.created_at",
                'device' => "messages.device",
                'source' => "messages.source_id",
                //'bully_level', 'bully_type', 'sentiment' => "message_results.classification_id",
                'bully_level' => "message_results_2.classification_level_id",
                'bully_type' => "message_results_2.classification_type_id",
                'bully_sentiment' => "message_results_2.classification_sentiment_id",
                default => "total_engagement",
            };
            // error_log(json_encode($field_table));
            $raw->orderBy($field_table, $request->sort);
        } else {
            $raw->orderByDesc('total_engagement');
        }

        if (
            $request->report_number === '3.2.013' ||
            $request->report_number === '3.2.014'
        ) {

            if ($request->select_period === 'previous') {
                $start_date = $this->start_date_previous;
                $end_date = $this->end_date_previous;
            } else {
                $start_date = $this->start_date;
                $end_date = $this->end_date;
            }
            $this->start_date = $start_date;
            $this->end_date = $end_date;
        }

        if (
            $request->page_name === 'monitoringDashboard'
        ) {
            $raw->whereIn('messages.message_type', ["Post", "Video", "post"]);
        }
        //Overall Dashboard
        if (
            $request->report_number === '1.2.002' ||
            $request->report_number === '2.2.002' ||
            $request->report_number === '2.2.013' ||
            $request->report_number === '3.2.002' ||
            $request->report_number === '4.2.002' ||
            $request->report_number === '4.2.012' ||
            $request->report_number === '5.2.002' ||
            $request->report_number === '6.2.002' ||
            $request->report_number === '6.2.012'

        ) {

            if ($request->label) {
                $date_request = Carbon::createFromFormat('d/m/Y', $request->label)->format('Y-m-d');
                $this->start_date = $date_request;
                $this->end_date = $date_request;
                // error_log('date request :'.json_encode($date_request));
                // error_log('start_date :'.json_encode($this->start_date));
                // error_log('end_date :'.json_encode($this->end_date));

                if (
                    $request->report_number === '1.2.002' ||
                    $request->report_number === '4.2.002'
                ) {
                    $keyword = Keyword::where('name', $Llabel)
                        ->where('status', 1)
                        ->first();
                    if ($keyword) {
                        $raw->where('messages.keyword_id', $keyword->id);
                    }
                }
            } else {

            }
        }

        // Date Format
        if (
            $request->report_number === '2.2.003' ||
            $request->report_number === '3.2.003' ||
            $request->report_number === '4.2.003' ||
            $request->report_number === '4.2.013' ||
            $request->report_number === '5.2.003' ||
            $request->report_number === '6.2.003' ||
            $request->report_number === '6.2.013'
        ) {
            //$isHasMessageDate = false;
            //     if ($request->label){
            //         $raw->whereRaw('DATE_FORMAT(tbl_messages.created_at, "%a") = ?', [$request->label]);
            //         error_log(json_encode([$request->label]));
            //     }

            // }

            if ($request->label) {
                $thaiToEng = [
                    'วันอาทิตย์' => 'Sun',
                    'วันจันทร์' => 'Mon',
                    'วันอังคาร' => 'Tue',
                    'วันพุธ' => 'Wed',
                    'วันพฤหัสบดี' => 'Thu',
                    'วันศุกร์' => 'Fri',
                    'วันเสาร์' => 'Sat',
                ];

                $label = $thaiToEng[$request->label] ?? $request->label;

                $raw->whereRaw('DATE_FORMAT(tbl_messages.created_at, "%a") = ?', [$label]);
                // error_log(json_encode([$label]));
            }
        }

        // time Format  
        if (
            $request->report_number === '2.2.004' ||
            $request->report_number === '3.2.004' ||
            $request->report_number === '4.2.004' ||
            $request->report_number === '4.2.014' ||
            $request->report_number === '5.2.004' ||
            $request->report_number === '6.2.004' ||
            $request->report_number === '6.2.014'
        ) {

            if ($request->label === 'Before 6 AM' || $request->label === 'ก่อน 06.00 น.') {
                $raw->whereRaw('HOUR(tbl_messages.created_at) < ?', [6]);
            } else if ($request->label === '6 AM-12 PM' || $request->label === '06.00-12.00 น.') {
                $raw->whereRaw('HOUR(tbl_messages.created_at) >= ? AND HOUR(tbl_messages.created_at) < ?', [6, 12]);
            } else if ($request->label === '12 PM-6 PM' || $request->label === '12.00-18.00 น.') {
                $raw->whereRaw('HOUR(tbl_messages.created_at) >= ? AND HOUR(tbl_messages.created_at) < ?', [12, 18]);
            } else if ($request->label === 'After 6 PM' || $request->label === 'หลัง 18.00 น.') {
                $raw->whereRaw('HOUR(tbl_messages.created_at) >= ?', [18]);
            }
            // $isHasMessageDate = true;
        }
        //user_typr
        if (
            $request->report_number === '2.2.006' ||
            $request->report_number === '3.2.006' ||
            $request->report_number === '4.2.006' ||
            $request->report_number === '4.2.016' ||
            $request->report_number === '5.2.006' ||
            $request->report_number === '6.2.006' ||
            $request->report_number === '6.2.016'
        ) {

            if (
                $request->report_number === '2.2.006'
                || $request->report_number === '3.2.006'
                || $request->report_number === '4.2.006'
                || $request->report_number === '5.2.006'
                || $request->report_number === '6.2.006'
                || $request->report_number === '6.2.016'
            ) {
                if ($request->label != '') {
                    // error_log('label in if:' . $request->label);
                    if ($request->label === 'Influencer' || $request->label === 'เจ้าของโพสต์') {
                        $raw->where('reference_message_id', '');
                    } else {
                        $raw->where('reference_message_id', '!=', '');
                    }
                }
            } else {
                if ($request->label != '') {
                    if ($request->label === 'Post Owner' || $request->label === 'เจ้าของโพสต์') {
                        $raw->where('reference_message_id', '=', '');
                    } else {
                        $raw->where('reference_message_id', '!=', '');
                    }
                }
            }
        }

        //Day&Time
        if (
            $request->report_number === '2.2.016' ||
            $request->report_number === '2.2.017' ||
            $request->report_number === '2.2.018' ||
            $request->report_number === '2.2.019'
        ) {
            if (isset($request->label) && is_numeric($request->label)) {
                if ($request->report_number === '2.2.016') {
                    $raw->whereRaw('DATE_FORMAT(tbl_messages.created_at, "%a") = ?', [$request->ylabel]);
                }
                $raw->whereRaw('HOUR(tbl_messages.created_at) >= ? AND HOUR(tbl_messages.created_at) < ?', [$request->label, $request->label + 1]);

            } else {
                $raw->whereRaw('DATE_FORMAT(tbl_messages.created_at, "%a") = ?', [$request->label]);
            }
        }

        //if (!$isHasMessageDate) {
        $raw->whereBetween('messages.created_at', [$this->start_date . " 00:00:01", $this->end_date . " 23:59:59"]);
        //}
        return $raw;
    }

    // private
    //     function getClassificationName(
    //     // $message_id
    //     $id
    // ) {
    //     return DB::table('messages')
    //         ->select([
    //             'classifications.name AS classification_name',
    //             'classifications.classification_type_id AS classification_type_id'
    //         ])
    //         // ->where('messages.message_id', $message_id)
    //         ->where('messages.id', $id)
    //         ->join('message_results', 'message_results.message_id', '=', 'messages.id')
    //         ->join('classifications', 'message_results.classification_id', '=', 'classifications.id')
    //         // ->limit(3)
    //         ->get(['classifications.classification_type_id', 'classification_name']);
    // }

    // New table
    private function getClassificationName($message_id)
    {
        return DB::table('message_results_2')
            ->select([
                'media_type',
                'classification_sentiment_id',
                'classification_type_id',
                'classification_level_id'
            ])
            ->where('message_id', $message_id)
            // ->where('media_type', 1)
            ->limit(1)
            ->get();
    }

    private
        function raw_account(
        Request $request,
        $campaign_id,
        $start_date,
        $end_date,
        $report_number
    ) {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();


        $subquery = DB::table('messages')
            ->select([
                'messages.id',
                'messages.author AS author',
                'messages.message_id AS message_id',
                'messages.reference_message_id AS reference_message_id',
                'messages.keyword_id AS keyword_id',
                'messages.message_datetime AS date_m',
                'messages.source_id AS source_id',
                'messages.full_message AS full_message',
                'messages.message_type',
                'messages.link_message AS link_message',
                'messages.device AS device',
                'messages.number_of_views AS number_of_views',
                'messages.number_of_comments AS number_of_comments',
                'messages.number_of_shares AS number_of_shares',
                'messages.number_of_reactions AS number_of_reactions',
                'message_results.classification_id',
                'messages.created_at AS created_at',
                DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) + 
                    COALESCE(number_of_views, 0) AS total_engagement')
            ])
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->groupBy('messages.author')
            ->havingRaw('total_engagement > 0'); // Exclude rows with total_engagement = 0 if desired
        // ->orderByDesc('total_engagement');
        // ->get();
        if ($this->source_id) {
            if (is_array($this->source_id) && count($this->source_id) > 1) {
                $subquery->whereIn('source_id', $this->source_id);
            } else {
                $subquery->where('source_id', is_array($this->source_id) ? $this->source_id[0] : $this->source_id);
            }
        }

        // $count = $results->count();

        // dd($subquery->get()->count());
        if ($request->sort && $request->field) {
            $field = $request->field;
            $field_table = match ($field) {
                'message_type' => "messages.message_type",
                'author' => "messages.author",
                'date' => "messages.message_datetime",
                'scrapingtime' => "messages.created_at",
                'device' => "messages.device",
                'source' => "sources_id",
                'sentiment', 'engagement', 'bully_type' => "classifications_id",
                default => "total_engagement",
            };

            $subquery->orderBy($field_table, $request->sort);

        } else {
            $subquery->orderByDesc('total_engagement');
        }
        $total = $subquery->count() ?? 0;
        $raw = $subquery->offset($start)->limit($limit)->get();

        foreach ($raw as $ke => $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->message_id);
            $parent = null;
            if (!$item->reference_message_id || $item->reference_message_id === null || $item->reference_message_id === '') {
                $parent = $item->message_id;
            }


            $data_push = [
                "id" => $item->id ?? null,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('H:i'),
                "day" => $date_d,
                "message_type" => $item->message_type,
                "device" => $item->device,
                /*"channel" => $item->source_name,
                "source_name" => $item->source_name,*/
                "link_message" => $item->link_message,
                "parent" => $parent,
                "engagement" => $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions + $item->number_of_views,
            ];

            //loop for get classification name   
            foreach ($types as $type) {
                if ($type->classification_sentiment_id) {
                    $data_push['sentiment'] = $type->classification_sentiment_id;
                }

                if ($type->classification_type_id) {
                    $data_push['bully_type'] = $type->classification_type_id;
                }

                if ($type->classification_level_id) {
                    $data_push['bully_level'] = $type->classification_level_id;
                }
            }
            $data['message'][$item->message_id] = $data_push;
        }


        if (isset($data['message'])) {
            $data['message'] = array_values($data['message']);
        }

        $data['total'] = $total;
        return $data;
    }

    private
        function classifacation_multiple(
        Request $request,
        $campaign_id,
        $start_date,
        $end_date,
        $report_number
    ) {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $label = str_replace("+", " ", $request->label);
        $Llabel = str_replace("+", " ", $request->Llabel);

        if ($Llabel === "No Bully") {
            $Llabel = "NoBully";
        } else if ($Llabel === "Hate Speech") {
            $Llabel = "HateSpeech";
        } else if ($Llabel === "No Bully") {
            $Llabel = "NoBully";
        } else if ($Llabel === "Hate Speech") {
            $Llabel = "HateSpeech";
        }

        $rawQuery = "SELECT
            tbl_messages.id as id,
            tbl_messages.message_id as message_id,
            tbl_messages.reference_message_id as reference_message_id,
            tbl_messages.keyword_id as keyword_id,
            tbl_messages.link_message as link_message,
            tbl_messages.message_datetime as date_m,
            tbl_messages.author as author,
            tbl_messages.source_id as source_id,
            tbl_messages.full_message as full_message,
            tbl_messages.message_type,
            tbl_messages.device as device,
            tbl_messages.number_of_views as number_of_views,
            tbl_messages.number_of_comments as number_of_comments,
            tbl_messages.number_of_shares as number_of_shares,
            tbl_messages.number_of_reactions as number_of_reactions,
        
            tbl_message_results.classification_type_id,
            tbl_message_results.classification_id,
            tbl_messages.created_at as created_at
    
        FROM
            tbl_messages
            LEFTJOIN tbl_message_results ON tbl_message_results.message_id = tbl_messages.id
        WHERE
            tbl_messages.message_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

        if ($this->source_id) {
            $rawQuery .= " AND tbl_messages.source_id = " . $this->source_id;
        }

        //error_log("label:" . $label . " / ---> :" . $rawQuery);
        $rows = DB::select(DB::raw($rawQuery));

        //$raw = $query->get();
        $parents = [];
        foreach ($rows as $item) {
            if ($item->reference_message_id) {
                if (array_search($item->reference_message_id, $parents) === false) {
                    $parents[] = $item->reference_message_id;
                }
            }
        }
        $sources = $this->getAllSource();
        $anylsys = [];

        foreach ($rows as $item) {
            // dd($item);
            $date_d = Carbon::parse($item->date_m)->format('D');
            $parent = null;
            if (array_search($item->message_id, $parents) !== false) {
                $parent = $item->message_id;
            }

            $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
            $anylsys[$item->message_id]["id"] = $item->id;
            $anylsys[$item->message_id]["message_id"] = $item->message_id;
            $anylsys[$item->message_id]["message_type"] = $item->message_type;
            $anylsys[$item->message_id]["message_detail"] = $item->full_message;
            $anylsys[$item->message_id]["account_name"] = $item->author;
            $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
            $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('H:i');
            $anylsys[$item->message_id]["day"] = $date_d;
            $anylsys[$item->message_id]["device"] = $item->device;
            $anylsys[$item->message_id]["source_id"] = $item->source_id;
            // $anylsys[$item->message_id]["bully_level"] = $item->classification_name;
            // $anylsys[$item->message_id]["bully_type"] = $item->classification_id;
            $anylsys[$item->message_id]["channel"] = self::matchSourceName($sources, $item->source_id);
            $anylsys[$item->message_id]["link_message"] = $item->link_message;
            $anylsys[$item->message_id]["engagement"] = $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions + $item->number_of_views;
            $anylsys[$item->message_id]["parent"] = $parent;
        }

        foreach ($anylsys as $anylsy) {
            if ($report_number === '5.2.008') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];

                if ($anylsy[1] == $Llabel && $anylsy[3] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

            if ($report_number === '5.2.009') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];

                if ($anylsy[1] == $Llabel && $anylsy[2] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

            if ($report_number === '6.2.008') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];

                if ($anylsy[3] == $Llabel && $anylsy[1] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

            if ($report_number === '6.2.018') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];

                if ($anylsy[2] == $Llabel && $anylsy[1] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

        }

        if ($data) {
            $data['total'] = count($data['message']);
            $offset = 9 + 1;

            $data['message'] = array_slice($data['message'], $start, $offset);
        }

        return $data;
    }

    public
        function deleteMessage(
        Request $request
    ) {
        if ($request->id) {
            $originalMessage = Message::find($request->id);

            if ($originalMessage) {
                // remove data old table
                $delete = Message::destroy($originalMessage->id);

                if ($delete) {
                    $newMessage = new MessageDeleteLog();
                    $newMessage->id = $originalMessage->id;
                    $newMessage->message_id = $originalMessage->message_id;
                    $newMessage->reference_message_id = $originalMessage->reference_message_id;
                    $newMessage->keyword_id = $originalMessage->keyword_id;
                    $newMessage->message_datetime = $originalMessage->message_datetime;
                    $newMessage->author = $originalMessage->author;
                    $newMessage->source_id = $originalMessage->source_id;
                    $newMessage->full_message = $originalMessage->full_message;
                    $newMessage->link_message = $originalMessage->link_message;
                    $newMessage->message_type = $originalMessage->message_type;
                    $newMessage->device = $originalMessage->device;
                    $newMessage->number_of_shares = $originalMessage->number_of_shares;
                    $newMessage->number_of_comments = $originalMessage->number_of_comments;
                    $newMessage->number_of_reactions = $originalMessage->number_of_reactions;
                    $newMessage->number_of_views = $originalMessage->number_of_views;

                    $newMessage->save();

                    return parent::handleRespond($newMessage);
                }
            }

            return parent::handleRespond(null, null, 404, 'Message id not found');
        }

        return parent::handleRespond(null, null, 404, 'Plase send id of message');
    }

    public function exportMonitoring(Request $request)
    {

        // $classificationTypes = self::getClassificationJoinTypeMaster();

        $sources = $this->getAllSource();
        $fillter_keywords = $request->keyword_id;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }
        $keywords = $this->findKeywords($this->campaign_id, $this->keyword_id);

        // $bullytype = [
        //     1 => 'Positive', 2 => 'Negative', 3 => 'Neutral',
        //     4 => 'NoBully', 5 => 'Physical Bully', 6 => 'Verbal Bullying',
        //     7 => 'Social Bullying', 8 => 'Cyber Bullying', 9 => 'Level 0',
        //     10 => 'Level 1', 11 => 'Level 2', 12 => 'Level 3',
        // ];

        $raw = self::messageLevelThreeRaw($request, $sources, $keywords);
        $data = $raw->get();
        $messageIds = $data->pluck('id')->all();
        // $count = count($messageIds);
        // error_log("จำนวนข้อมูล: " . $count);

        $messageResult = DB::table('messages')
            ->join('message_results_2', 'message_results_2.message_id', '=', 'messages.id')
            ->select([
                'messages.full_message',
                'messages.message_type',
                'messages.author',
                'messages.message_datetime as date_m',
                'messages.created_at as scraping_time',
                'messages.source_id',
                // 'messages.total_engagement',
                'messages.number_of_views',
                'messages.number_of_comments',
                'messages.number_of_shares',
                'messages.number_of_reactions',
                'messages.link_message',
                'message_results_2.media_type',
                'message_results_2.message_id',
                'message_results_2.classification_sentiment_id as sentiment',
                'message_results_2.classification_type_id as bully_type',
                'message_results_2.classification_level_id as bully_level',
            ])
            // ->where('message_results_2.media_type', 1)
            ->whereIn('messages.id', $messageIds);

        $messageResults = $messageResult->get();
        // $count = count($messageResults);
        // error_log("จำนวนข้อมูล: " . $count);
        $result = [];
        // error_log("messageResult:" . json_encode($messageResults));
        foreach ($messageResults as $message) {
            // $message->sentiment = "";
            // $message->bully_type = "";
            // $message->bully_level = "";

            // foreach ($messageResult as $item) {
            //     if ($message->id == $item->message_id) { 
            //         if ($item->classification_sentiment_id){
            //             $message->sentiment = $bullytype[$item->classification_sentiment_id] ?? '';
            //         }
            //         if ($item->classification_type_id){
            //             $message->bully_type = $bullytype[$item->classification_type_id] ?? '';
            //         }
            //         if ($item->classification_level_id){
            //             $message->bully_level = $bullytype[$item->classification_level_id] ?? '';
            //         }
            //     }
            // }
            // error_log(json_encode($message));
            $result[] = $message;

        }

        // $count = 0;
        // foreach ($messageResult as $item) {
        //     if ($message->id == $item->message_id) {
        //         $count++;
        // $message = $this->packObjectClassificationTypeName($classificationTypes, $item, $message);
        //         // error_log(json_encode($message));
        //     }
        //     if ($count > 2) {
        //         break;
        //     }
        // }
        //         $result[] = $message;
        // }
        //return parent::handleRespond($result);
        return Excel::download(new MonitoringExport($result, "dailyMessage", $sources, $keywords), 'Monitoring-' . Carbon::now() . '.xlsx');
    }

}
