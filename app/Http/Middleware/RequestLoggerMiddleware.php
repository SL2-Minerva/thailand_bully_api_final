<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class RequestLoggerMiddleware extends Controller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, JsonResponse $response)
    {
        if ($request->getRequestUri()) {
            $sub_endpoint = substr($request->getRequestUri() ,4);
            switch( $sub_endpoint ) {
                case strstr($sub_endpoint, "/auth"):
                    $feature = "Authentication";
                    break;
                case strstr($sub_endpoint, "/campaign"):
                    $feature = "Campaign";
                    break;
                case strstr($sub_endpoint, "/user/info"):
                    $feature = "User Info";
                    break;
                case strstr($sub_endpoint, "/user"):
                    $feature = "User";
                    break;
                case strstr($sub_endpoint, "/organization-type"):
                    $feature = "Organization Type";
                    break;
                case strstr($sub_endpoint, "/organization-group"):
                    $feature = "Organization Group";
                    break;
                case strstr($sub_endpoint, "/organization"):
                    $feature = "Organization";
                    break;
                case strstr($sub_endpoint, "/source"):
                    $feature = "Source";
                    break;
                case strstr($sub_endpoint, "/permission"):
                    $feature = "Permission";
                    break;
                case strstr($sub_endpoint, "/role"):
                    $feature = "Role";
                    break;
                case strstr($sub_endpoint, "/organization-content"):
                    $feature = "Organization Content";
                    break;
                case strstr($sub_endpoint, "/domain"):
                    $feature = "Domain";
                    break;
                case strstr($sub_endpoint, "/keyword"):
                    $feature = "Keyword";
                    break;
                case strstr($sub_endpoint, "/dashboard-channel"):
                    $feature = "Report Channel Dashboard";
                    break;
                case strstr($sub_endpoint, "/dashboard-overall"):
                    $feature = "Report Overall Dashboard";
                    break;
                case strstr($sub_endpoint, "/dashboard-voice"):
                    $feature = "Report Voice Dashboard";
                    break;
                case strstr($sub_endpoint, "/dashboard-engagement"):
                    $feature = "Report Engagement Dashboard";
                    break;
                case strstr($sub_endpoint, "/dashboard-sentiment"):
                    $feature = "Report Sentiment Dashboard";
                    break;
                case strstr($sub_endpoint, "/dashboard-bully"):
                    $feature = "Report Bully Dashboard";
                    break;
                case strstr($sub_endpoint, "/export"):
                    $feature = "Export";
                    break;
                case strstr($sub_endpoint, "/activity-log"):
                    $feature = "Activity Log";
                    break;
                case strstr($sub_endpoint, "/health-check"):
                    $feature = "Health Check";
                    break;
                case strstr($sub_endpoint, "/forget_password"):
                    $feature = "Forget Password";
                    break;
                case strstr($sub_endpoint, "/reset_password"):
                    $feature = "Reset Password";
                    break;
                default :
                    $feature = null;
            }
        }

        if (!strstr($request->getPathInfo(), "/activity-log")) {
            ActivityLog::create([
                'method' => $request->method(),
                'ip' => $request->ip(),
                'end_point' =>  $request->getPathInfo(),
                'request' =>  $request->getQueryString() ?? null,
                'status_code' => $response->getStatusCode(),
                'feature' => $feature ?? null,
                'request_by' => $this->user_login->id ?? 0
            ]);
        }

    }
}
