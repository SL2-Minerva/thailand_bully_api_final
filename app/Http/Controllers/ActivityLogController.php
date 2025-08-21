<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = [];

        $raw = ActivityLog::join('users', 'users.id', 'activity_log.request_by')
            ->select('activity_log.*', 'users.name as request_by_name')
            ->offset($start)->limit($limit)
            ->orderBy('activity_log.id', 'DESC');

        $raw_total = ActivityLog::join('users', 'users.id', 'activity_log.request_by')
            ->select('activity_log.*', 'users.name as request_by_name')
            ->orderBy('activity_log.id', 'DESC');

        if ($request->search) {
            $search = $request->search;
            $raw->where(function($q) use ($search, $request) {
                if ($request) {
                    $q->orWhere('end_point', 'LIKE', '%' . $search . '%');
                    $q->orWhere('method', 'LIKE', '%' . $search . '%');
                    $q->orWhere('feature', 'LIKE', '%' . $search . '%');
                    $q->orWhere('users.name', 'LIKE', '%' . $search . '%');
                }
            });

            $raw_total->where(function($q) use ($search, $request) {
                if ($request) {
                    $q->orWhere('end_point', 'LIKE', '%' . $search . '%');
                    $q->orWhere('method', 'LIKE', '%' . $search . '%');
                    $q->orWhere('feature', 'LIKE', '%' . $search . '%');
                    $q->orWhere('users.name', 'LIKE', '%' . $search . '%');
                }
            });

        }

        if ($request->status_code) {
            $status_code = $request->status_code;

            $raw->where(function($q) use ($status_code, $request) {
                if ($request) {
                    $q->where('status_code', $status_code);
                }
            });

            $raw_total->where(function($q) use ($status_code, $request) {
                if ($request) {
                    $q->where('status_code', $status_code);
                }
            });
        }

        $raw_data = $raw->get();

        foreach ($raw_data as $key => $item) {
            if ($item->status_code == '200') {
                $raw_data[$key]['status'] = 'สถานะปกติ';
            } else if ($item->status_code == '401') {
                $raw_data[$key]['status'] = 'ไม่สามารถเข้าถึง (Unauthorized)';
            } else if ($item->status_code == '403') {
                $raw_data[$key]['status'] = 'ไม่สามารถเข้าหน้าเว็บได้';
            } else if ($item->status_code == '404') {
                $raw_data[$key]['status'] = 'ไม่พบหน้าเว็บ';
            } else if ($item->status_code == '500') {
                $raw_data[$key]['status'] = 'เซิร์ฟเวอร์มีปัญหา';
            }
        }
        
        $data['total'] = $raw_total->get()->count();
        $data['activity_log'] = $raw_data;

        return parent::handleRespond($data);
        
    }
}