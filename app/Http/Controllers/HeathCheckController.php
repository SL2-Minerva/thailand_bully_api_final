<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeathCheckController extends Controller
{
    public function index(Request $request)
    {
        try {
            if (DB::connection()->getPdo())
            {
                return parent::handleRespond();
            }
        } catch (\Exception $e) {
            return parent::handleRespond(null, null, 500, "connect_database_fail");
        }
    }
}
