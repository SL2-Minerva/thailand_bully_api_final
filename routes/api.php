<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\auth\AuthController;
use App\Http\Controllers\main\CampaignController;
use App\Http\Controllers\main\DomainController;
use App\Http\Controllers\main\KeywordController;
use App\Http\Controllers\main\SourceController;
use App\Http\Controllers\permission\PermissionController;
use App\Http\Controllers\permission\RoleController;
use App\Http\Controllers\report\BullyDashboardController;
use App\Http\Controllers\report\DashboardController;
use App\Http\Controllers\report\LevelfourController;
use App\Http\Controllers\report\LevelThreeBullyDashboardController;
use App\Http\Controllers\report\LevelthreeController;
use App\Http\Controllers\report\LevelThreeEngagementDashboardController;
use App\Http\Controllers\report\LevelThreeSentimentDashboardController;
use App\Http\Controllers\user\OrganizationContentController;
use App\Http\Controllers\user\OrganizationController;
use App\Http\Controllers\user\OrganizationGroupController;
use App\Http\Controllers\user\OrganizationTypeController;
use App\Http\Controllers\user\UserController;
use App\Http\Controllers\report\VoiceDashboardController;
use App\Http\Controllers\report\ChannelDashboardController;
use App\Http\Controllers\report\EngagementDashboardController;
use App\Http\Controllers\report\ExportExcelOverallController;
use App\Http\Controllers\report\LeveltreeChannelDashboardController;
use App\Http\Controllers\report\LeveltreeVoiceDashboardController;
use App\Http\Controllers\report\SentimentDashboardController;
use App\Http\Controllers\report\LevethreeOverAllDashboardController;
use App\Http\Controllers\HeathCheckController;
use App\Http\Controllers\report\MonitoringController;
use App\Http\Controllers\report\LevelThreeTableController;
use App\Http\Controllers\report\LevelFourTableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('user/forget_password', [UserController::class, 'forget_password']);
Route::post('user/reset_password', [UserController::class, 'reset_password']);

Route::group(['middleware' => ['api']], function () {
    Route::get('health-check', [HeathCheckController::class, 'index']);

    Route::get('image-loader', [MonitoringController::class, 'imageLoader']);
    // group only auth
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/reset-password', [AuthController::class, 'reset_password']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    Route::group(['middleware' => ['api', 'auth:api']], function () {

        Route::group(['prefix' => 'campaign'], function () {
            Route::get('/', [CampaignController::class, 'show']);
            Route::get('/list', [CampaignController::class, 'index']);
            Route::post('/create', [CampaignController::class, 'store']);
            Route::put('/update', [CampaignController::class, 'update']);
            Route::put('/delete', [CampaignController::class, 'destroy']);
            Route::get('/search', [CampaignController::class, 'search']);
        });

        Route::group(['prefix' => 'user'], function () {
            Route::get('/info', [UserController::class, 'info']);
            Route::get('/info-transaction', [UserController::class, 'info_transaction']);
            Route::get('/list', [UserController::class, 'data']);
            Route::get('/list-active', [UserController::class, 'list_active']);
            Route::post('/create', [UserController::class, 'create']);
            Route::post('/update/{user}', [UserController::class, 'update']);
            Route::delete('/delete/{user}', [UserController::class, 'delete']);
            Route::get('/search', [UserController::class, 'search']);
        });
        // organization-type
        Route::group(['prefix' => 'organization-type'], function () {
            Route::post('/', [OrganizationTypeController::class, 'show']);
            Route::get('/list', [OrganizationTypeController::class, 'data']);
            Route::post('/create', [OrganizationTypeController::class, 'store']);
            Route::put('/update', [OrganizationTypeController::class, 'update']);
            Route::put('/delete', [OrganizationTypeController::class, 'delete']);
        });

        // organization-group
        Route::group(['prefix' => 'organization-group'], function () {
            Route::post('/', [OrganizationGroupController::class, 'show']);
            Route::get('/list', [OrganizationGroupController::class, 'data']);
            Route::post('/create', [OrganizationGroupController::class, 'store']);
            Route::put('/update', [OrganizationGroupController::class, 'update']);
            Route::put('/delete', [OrganizationGroupController::class, 'delete']);
        });

        // organization
        Route::group(['prefix' => 'organization'], function () {
            Route::get('/', [OrganizationController::class, 'show']);
            Route::get('/list', [OrganizationController::class, 'data']);
            Route::post('/create', [OrganizationController::class, 'store']);
            Route::put('/update', [OrganizationController::class, 'update']);
            Route::put('/delete', [OrganizationController::class, 'destroy']);
            Route::get('/search', [OrganizationController::class, 'search']);
        });

        Route::group(['prefix' => 'source'], function () {
            Route::get('/', [SourceController::class, 'show']);
            Route::get('/list', [SourceController::class, 'data']);
            Route::get('/public-source', [SourceController::class, 'public_source']);
            Route::post('/create', [SourceController::class, 'store']);
            Route::post('/update', [SourceController::class, 'update']);
            Route::put('/delete', [SourceController::class, 'destroy']);
        });

        Route::group(['prefix' => 'permission'], function () {
            Route::get('/', [PermissionController::class, 'show']);
            //Route::get('/list', [PermissionController::class, 'list']);
            Route::post('/create', [PermissionController::class, 'store']);
            Route::put('/update', [PermissionController::class, 'update']);
            Route::put('/delete', [PermissionController::class, 'destroy']);
            Route::get('/report-chart-list', [PermissionController::class, 'report_chart_list']);
        });


        Route::group(['prefix' => 'role'], function () {
            Route::get('/', [RoleController::class, 'show']);
            Route::get('/list', [RoleController::class, 'index']);
            Route::post('/create', [RoleController::class, 'store']);
            Route::put('/update', [RoleController::class, 'update']);
            Route::put('/delete', [RoleController::class, 'destroy']);
        });

        Route::group(['prefix' => 'organization-content'], function () {
            Route::get('/', [OrganizationContentController::class, 'index']);
            Route::post('/', [OrganizationContentController::class, 'store']);
            Route::get('/show', [OrganizationContentController::class, 'show']);
            Route::post('/update', [OrganizationContentController::class, 'update']);
            Route::post('/delete', [OrganizationContentController::class, 'destroy']);
        });


        Route::group(['prefix' => 'domain'], function () {
            Route::get('/', [DomainController::class, 'show']);
            Route::get('/list', [DomainController::class, 'index']);
            Route::post('/create', [DomainController::class, 'store']);
            Route::put('/update', [DomainController::class, 'update']);
            Route::put('/delete', [DomainController::class, 'destroy']);
        });


        Route::group(['prefix' => 'keyword'], function () {
            Route::get('/', [KeywordController::class, 'show']);
            Route::get('/list', [KeywordController::class, 'index']);
            Route::post('/create', [KeywordController::class, 'store']);
            Route::put('/update', [KeywordController::class, 'update']);
            Route::put('/delete', [KeywordController::class, 'destroy']);
        });


        Route::group(['prefix' => 'source'], function () {
            Route::get('/index', [SourceController::class, 'index']);
        });

        Route::get('/keywords', [KeywordController::class, 'keywords']);
        Route::get('/sna', [LevelfourController::class, 'getSNA']);
        Route::group(['prefix' => 'dashboard-overall'], function () {
            Route::get('/', [DashboardController::class, 'overAll']);
            //Route::get('/daily-message/level-three/', [LevelthreeController::class, 'dailyMessageLevelThree']);
            //Route::get('/daily-message/level-four/', [LevelfourController::class, 'dailyMessageLevelFour']);
            Route::get('/engagement-trnsaction/', [EngagementDashboardController::class, 'EngagementTrans']);
            Route::get('/key-stats/', [DashboardController::class, 'keyStats']);
            Route::get('/keyword-summary/', [DashboardController::class, 'keywordSummary']);
            Route::get('/keyword-summary-top/', [DashboardController::class, 'keywordSummaryTop']);
            Route::get('/sentiment-score/', [DashboardController::class, 'sentimentScore']);
            Route::get('/sentiment-type/', [DashboardController::class, 'sentimentType']);
            Route::get('/share-of-voice/', [DashboardController::class, 'shareOfVoice']);
            //Route::get('/share-of-voice-number/', [DashboardController::class, 'shareOfVoiceNumber']);
            Route::get('/sentiment-level/', [DashboardController::class, 'sentimentLevel']);

            // word cloud
            Route::get('/word-clouds/', [DashboardController::class, 'wordClouds']);
            Route::get('/word-clouds-platform/', [DashboardController::class, 'wordCloudsPlateform']);
            Route::get('/word-clouds-position/', [DashboardController::class, 'wordCloudsPosition']);

            // level-three
            Route::get('/level-three/', [LevethreeOverAllDashboardController::class, 'dailyMessageLevelThree']);
        });

        Route::group(['prefix' => 'dashboard-monitoring'], function () {
            Route::get('daily-by', [MonitoringController::class, 'dailyBy']);
            Route::get('top-engagement', [MonitoringController::class, 'topEngagementOfPost']);
            Route::get('engagement-post', [MonitoringController::class, 'engagementOfPost']);
            Route::get('post-detail', [MonitoringController::class, 'detailOfPost']);
            Route::get('influencers/top', [MonitoringController::class, 'topInfluencerPost']);
            Route::get('influencers', [MonitoringController::class, 'influencerPost']);
            Route::get('engagements/export', [MonitoringController::class, 'engagementExport']);
            Route::get('influencers/export', [MonitoringController::class, 'influencerExport']);
            Route::get('influencers/author', [MonitoringController::class, 'influencerAuthor']);
            Route::get('daily/export', [MonitoringController::class, 'dailyExport']);

        });

        Route::group(['prefix' => 'dashboard-channel'], function () {
            Route::get('daily-by', [ChannelDashboardController::class, 'dailyBy']);
            Route::get('channel-by', [ChannelDashboardController::class, 'channelBy']);
            Route::get('engagement-by', [ChannelDashboardController::class, 'engagementBy']);
            Route::get('sentiment-by', [ChannelDashboardController::class, 'sentimentBy']);
            Route::get('/level-three/', [LeveltreeChannelDashboardController::class, 'dailyMessageLevelThree']);
        });

        Route::group(['prefix' => 'dashboard-voice'], function () {
            Route::get('/percentage-of-message', [VoiceDashboardController::class, 'PercentageOfMessage']);
            Route::get('/daily-message', [VoiceDashboardController::class, 'DailyMessage']);
            Route::get('message-by', [VoiceDashboardController::class, 'messageBy']);
            Route::get('/number-of-account-period-over-period', [VoiceDashboardController::class, 'NumberOfAccountPeriodOverPeriod']);
            Route::get('daytime-by', [VoiceDashboardController::class, 'DayTimeBy']);
            Route::get('channel-platform-channel-device', [VoiceDashboardController::class, 'channelPlatformChannelDevice']);
            Route::get('keyword-by', [VoiceDashboardController::class, 'keywordBy']);
            Route::get('/level-three/', [LeveltreeVoiceDashboardController::class, 'dailyMessageLevelThree']);
        });

        Route::group(['prefix' => 'dashboard-engagement'], function () {
            Route::get('/engagement-trnsaction', [EngagementDashboardController::class, 'EngagementTrans']);
            Route::get('/engagement-by', [EngagementDashboardController::class, 'EngagementBy']);
            Route::get('/engagement-type-by', [EngagementDashboardController::class, 'EngagementTypeBy']);
            Route::get('/engagement-comparison-by', [EngagementDashboardController::class, 'EngagementComparisonBy']);
            Route::get('/engagement-comparison-account', [EngagementDashboardController::class, 'EngagementComparisonByAccount']);
            Route::get('/level-three/', [LevelThreeEngagementDashboardController::class, 'report']);
        });

        Route::group(['prefix' => 'dashboard-sentiment'], function () {
            Route::get('/sentiment-daily', [SentimentDashboardController::class, 'DailySentiment']);
            Route::get('/sentiment-by', [SentimentDashboardController::class, 'SentimentBy']);
            Route::get('/sentiment-comparison', [SentimentDashboardController::class, 'SentimentComparison']);
            Route::get('/period-and-comparison', [SentimentDashboardController::class, 'periodAndComparison']);
            Route::get('/summary-by', [SentimentDashboardController::class, 'SummaryBy']);
            Route::get('/level-three/', [LevelThreeSentimentDashboardController::class, 'report']);
         });

        Route::group(['prefix' => 'dashboard-bully'], function () {
            Route::get('daily-by', [BullyDashboardController::class, 'dailyBy']);
            Route::get('bully-by', [BullyDashboardController::class, 'bullyBy']);
            Route::get('daily-type-by', [BullyDashboardController::class, 'dailyTypeBy']);
            Route::get('bully-type-by', [BullyDashboardController::class, 'bullyTypeBy']);
            //Route::get('bully-chart-by', [BullyDashboardController::class, 'bullyChartBy']);
            Route::get('/dashboard-overall/daily-message/level-four/', [LevelThreeBullyDashboardController::class, 'report']);
        });

        Route::group(['prefix' => 'level-three-table'], function () {
            Route::get('/', [LevelThreeTableController::class, 'messageLevelThree']);
        });

        Route::group(['prefix' => 'level-four-table'], function () {
            Route::get('/', [LevelFourTableController::class, 'messageLevelFour']);
        });

/*
        Route::group(['prefix' => 'export'], function () {
            Route::get('export-overall', [LevelthreeController::class, 'exportOverAll']);
            Route::get('export-voice', [LevelthreeController::class, 'exportVoice']);
            Route::get('export-channel', [LevelthreeController::class, 'exportChannel']);
            Route::get('export-engagement', [LevelthreeController::class, 'exportEngagement']);
            Route::get('export-sentiment', [LevelthreeController::class, 'exportSentiment']);
            Route::get('export-bully', [LevelthreeController::class, 'exportBully']);
            Route::get('export-excel-overall', [ExportExcelOverallController::class, 'exportOverAll']);
            Route::get('monitoring', [LevelThreeTableController::class, 'exportMonitoring']);

        });*/

        Route::group(['prefix' => 'export'], function () {
            Route::get('export-overall', [LevelThreeTableController::class, 'exportMonitoring']);
            Route::get('export-voice', [LevelThreeTableController::class, 'exportMonitoring']);
            Route::get('export-channel',[LevelThreeTableController::class, 'exportMonitoring']);
            Route::get('export-engagement', [LevelThreeTableController::class, 'exportMonitoring']);
            Route::get('export-sentiment', [LevelThreeTableController::class, 'exportMonitoring']);
            Route::get('export-bully', [LevelThreeTableController::class, 'exportMonitoring']);
            Route::get('export-excel-overall', [LevelThreeTableController::class, 'exportMonitoring']);
            Route::get('monitoring', [LevelThreeTableController::class, 'exportMonitoring']);

        });

        Route::group(['prefix' => 'activity-log'], function () {
            Route::get('/', [ActivityLogController::class, 'index']);
        });

        Route::group(['prefix' => 'delete'], function () {
            Route::post('/message', [LevelThreeTableController::class, 'deleteMessage']);
        });
               
    });
});








