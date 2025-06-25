<?php declare(strict_types=1);

use WebmanTech\CrontabTask\Commands\CrontabTaskListCommand;
use WebmanTech\CrontabTask\Commands\MakeTaskCommand;

return [
    CrontabTaskListCommand::class,
    MakeTaskCommand::class,
];
