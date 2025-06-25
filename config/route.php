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
        //绑定邀请关系
        Route::post('/bind_inviter', [\app\api\controller\UserController::class, 'bindInviter']);
    });

    //佣金接口
    Route::group('/commission', function () {
        //获取佣金配置
        Route::post('/config', [\app\api\controller\CommissionController::class, 'config']);
        //获取用户佣金金额
        Route::post('/get', [\app\api\controller\CommissionController::class, 'get']);
        //获取用户佣金变更记录
        Route::post('/records', [\app\api\controller\CommissionController::class, 'getRecords']);
        //获取用户佣金收益记录
        Route::post('/list', [\app\api\controller\CommissionController::class, 'getList']);
        //佣金提现
        Route::post('/withdrawal', [\app\api\controller\CommissionController::class, 'withdrawal']);
    });

    //订单接口
    Route::group('/order', function () {
        //Luffa订单接口
        Route::group('/luffa', function () {
            //获取Luffa购买配置
            Route::post('/config', [\app\api\controller\LuffaOrderController::class, 'config']);
            //创建Luffa订单
            Route::post('/create', [\app\api\controller\LuffaOrderController::class, 'create']);
            //完成Luffa订单
            Route::post('/complete', [\app\api\controller\LuffaOrderController::class, 'complete']);
        });
        //获取VIP购买配置
        Route::post('/config', [\app\api\controller\OrderController::class, 'config']);
        //创建订单
        Route::post('/create', [\app\api\controller\OrderController::class, 'create']);
        //查询订单
        Route::post('/query', [\app\api\controller\OrderController::class, 'query']);
        //订单回调
        Route::group('/callback', function () {
            //Plisio订单回调
            Route::post('/plisio', [\app\api\controller\OrderController::class, 'plisioCallback']);
        });
        //空白返回页
        Route::any('/finish', [\app\api\controller\OrderController::class, 'finish']);
    });

    //首页看板接口
    Route::group('/dashboard', function () {
        //统计数据
        Route::post('/summary', [\app\api\controller\DashboardController::class, 'summary']);
        //准备中的比赛
        Route::post('/preparing', [\app\api\controller\DashboardController::class, 'preparing']);
        //已推荐的比赛
//        Route::post('/promoted', [\app\api\controller\DashboardController::class, 'promoted']);
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
        //切换赛事的开启和关闭
        Route::post('/tournament_toggle_open', [\app\admin\controller\MatchController::class, 'toggleTournamentOpen']);
        //获取比赛列表
        Route::post('/match_list', [\app\admin\controller\MatchController::class, 'getMatchList']);
        //获取单个比赛
        Route::post('/match', [\app\admin\controller\MatchController::class, 'getMatch']);
        //设置赛事的异常状态
        Route::post('/set_error_status', [\app\admin\controller\MatchController::class, 'setMatchErrorStatus']);
    });

    //数据列表接口
    Route::group('/odd', function () {
        //获取盘口抓取数据列表
        Route::post('/list', [\app\admin\controller\OddController::class, 'getOddList']);
        //通过比赛id获取盘口列表
        Route::post('/list_by_match', [\app\admin\controller\OddController::class, 'getOddsByMatch']);
        //导出盘口列表
        Route::post('/export', [\app\admin\controller\OddController::class, 'exportOddList']);
        //根据现有的原始盘口，补充推荐盘口
        Route::post('/add', [\app\admin\controller\OddController::class, 'addOdd']);
        //删除已经推荐的记录
        Route::post('/remove_promoted', [\app\admin\controller\OddController::class, 'removePromoted']);
        //切换盘口的开启和关闭
        Route::post('/toggle_open', [\app\admin\controller\OddController::class, 'toggleOpen']);
    });

    //用户管理接口
    Route::group('/user', function () {
        //获取代理列表
        Route::post('/list', [\app\admin\controller\UserController::class, 'list']);
        //设置用户的状态
        Route::post('/set_status', [\app\admin\controller\UserController::class, 'setStatus']);
        //设置用户的VIP有效期
        Route::post('/set_expire_time', [\app\admin\controller\UserController::class, 'setExpireTime']);
    });

    //概览页统计接口
    Route::group('/dashboard', function () {
        //概览数据统计
        Route::post('/summary', [\app\admin\controller\DashboardController::class, 'summary']);
        //VIP购买数据统计
        Route::post('/vip_summary', [\app\admin\controller\DashboardController::class, 'vipSummary']);
    });

    //订单接口
    Route::group('/order', function () {
        //订单列表
        Route::post('/list', [\app\admin\controller\OrderController::class, 'list']);
    });

    //手动推荐接口
    Route::group('/manual_promote', function () {
        //创建手动推荐
        Route::post('/create', [\app\admin\controller\ManualPromoteController::class, 'create']);
        //删除手动推荐
        Route::post('/remove', [\app\admin\controller\ManualPromoteController::class, 'remove']);
        //手动推荐列表
        Route::post('/list', [\app\admin\controller\ManualPromoteController::class, 'list']);
    });

    //Surebet推送记录
    Route::group('/surebet_record', function () {
        //导出
        Route::post('/export', [\app\admin\controller\SurebetRecordController::class, 'export']);
    });
});

Route::disableDefaultRoute();