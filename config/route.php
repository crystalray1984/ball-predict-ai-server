<?php declare(strict_types=1);

use Webman\Route;

//用户端接口
Route::group('/api', function () {

    //用户接口
    Route::group('/user', function () {
        //用户登录
        Route::post('/login', [\app\api\controller\UserController::class, 'login']);
    });

    //首页看板接口
    Route::group('/dashboard', function () {
        //统计数据
        Route::post('/summary', [\app\api\controller\DashboardController::class, 'summary']);
        //准备中的比赛
        Route::post('/preparing', [\app\api\controller\DashboardController::class, 'preparing']);
        //已推荐的比赛
        Route::post('/promoted', [\app\api\controller\DashboardController::class, 'promoted']);
    });
});

Route::disableDefaultRoute();