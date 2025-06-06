<?php declare(strict_types=1);

use Webman\Route;

Route::fallback(function (\support\Request $request) {
    return G(\app\middleware\Cors::class)->process($request, fn() => response('', 404));
});

//用户端接口
Route::group('/api', function () {
    //公共接口
    Route::group('/common', function () {
        //获取验证码图片
        Route::any('/captcha', [\app\api\controller\CommonController::class, 'captcha']);
    });

    //用户接口
    Route::group('/user', function () {
        //用户登录
        Route::post('/login', [\app\api\controller\UserController::class, 'login']);
        //Luffa小程序授权登录
        Route::post('/luffa_login', [\app\api\controller\UserController::class, 'luffaLogin']);
        //获取当前登录用户的信息
        Route::post('/info', [\app\api\controller\UserController::class, 'info']);
        //获取VIP购买记录
        Route::post('/vip_records', [\app\api\controller\UserController::class, 'getVipRecords']);
    });

    //订单接口
    Route::group('/order', function () {
        //Luffa订单接口
        Route::group('/luffa', function () {
            //获取Luffa购买配置
            Route::post('/config', [\app\api\controller\OrderController::class, 'getLuffaConfig']);
            //创建Luffa订单
            Route::post('/create', [\app\api\controller\OrderController::class, 'createLuffaOrder']);
            //完成Luffa订单
            Route::post('/complete', [\app\api\controller\OrderController::class, 'completeLuffaOrder']);
        });
    });

    //首页看板接口
    Route::group('/dashboard', function () {
        //统计数据
        Route::post('/summary', [\app\api\controller\DashboardController::class, 'summary']);
        //准备中的比赛
        Route::post('/preparing', [\app\api\controller\DashboardController::class, 'preparing']);
        //已推荐的比赛
        Route::post('/promoted', [\app\api\controller\DashboardController::class, 'promoted']);
        //已推荐的比赛(带有效期判断)
        Route::post('/promoted_v2', [\app\api\controller\DashboardController::class, 'promotedV2']);
    });
});

//管理端接口
Route::group('/admin', function () {
    //管理用户接口
    Route::group('/admin', function () {
        //管理员登录
        Route::post('/login', [\app\admin\controller\AdminController::class, 'login']);
        //获取当前登录管理员的信息
        Route::post('/info', [\app\admin\controller\AdminController::class, 'info']);
    });

    //系统配置接口
    Route::group('/setting', function () {
        //管理员登录
        Route::post('/get', [\app\admin\controller\SettingController::class, 'get']);
        //获取当前登录管理员的信息
        Route::post('/set', [\app\admin\controller\SettingController::class, 'save']);
    });

    //比赛管理接口
    Route::group('/match', function () {
        //获取需要拉取赛果的比赛列表
        Route::post('/require_score_list', [\app\admin\controller\MatchController::class, 'getRequireScoreMatches']);
        //批量设置赛果
        Route::post('/multi_set_score', [\app\admin\controller\MatchController::class, 'multiSetMatchScore']);
        //设置赛果
        Route::post('/set_score', [\app\admin\controller\MatchController::class, 'setMatchScore']);
        //获取赛事列表
        Route::post('/tournament_list', [\app\admin\controller\MatchController::class, 'getTournamentList']);
        //获取赛事列表
        Route::post('/match_list', [\app\admin\controller\MatchController::class, 'getMatchList']);
        //设置赛事的异常状态
        Route::post('/set_error_status', [\app\admin\controller\MatchController::class, 'setMatchErrorStatus']);
    });

    //数据列表接口
    Route::group('/odd', function () {
        //获取盘口抓取数据列表
        Route::post('/list', [\app\admin\controller\OddController::class, 'getOddList']);
    });

    //代理管理接口
    Route::group('/agent', function () {
        //获取代理列表
        Route::post('/list', [\app\admin\controller\AgentController::class, 'list']);
        //获取代理详情
        Route::post('/get', [\app\admin\controller\AgentController::class, 'get']);
        //保存代理
        Route::post('/save', [\app\admin\controller\AgentController::class, 'save']);
    });

    //用户管理接口
    Route::group('/user', function () {
        //获取代理列表
        Route::post('/list', [\app\admin\controller\UserController::class, 'list']);
        //获取代理详情
        Route::post('/get', [\app\admin\controller\UserController::class, 'get']);
        //保存代理
        Route::post('/save', [\app\admin\controller\UserController::class, 'save']);
    });
});

//代理端接口
Route::group('/agent', function () {
    //代理端用户接口
    Route::group('/agent', function () {
        //代理登录
        Route::post('/login', [\app\admin\controller\AdminController::class, 'login']);
        //获取当前登录代理的信息
        Route::post('/info', [\app\admin\controller\AdminController::class, 'info']);
    });
});

Route::disableDefaultRoute();