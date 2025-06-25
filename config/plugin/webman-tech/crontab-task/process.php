<?php declare(strict_types=1);

use WebmanTech\CrontabTask\Schedule;

return (new Schedule())
    // 添加单个定时任务，独立进程
//    ->addTask('task1', '*/1 * * * * *', \WebmanTech\CrontabTask\Tasks\SampleTask::class)
    // 添加多个定时任务，在同个进程中（注意会存在阻塞）
//    ->addTasks('task2', [
//        ['*/1 * * * * *', \WebmanTech\CrontabTask\Tasks\SampleTask::class],
//        ['*/1 * * * * *', \WebmanTech\CrontabTask\Tasks\SampleTask::class],
//    ])

    //每月佣金结算，每月1日0点0分10秒
    ->addTask('CommissionSettlement', '10 0 0 1 * *', \tasks\CommissionSettlementTask::class)
    ->buildProcesses();
