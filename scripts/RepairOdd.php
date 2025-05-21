<?php declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../support/bootstrap.php";

$service = G(\app\admin\service\MatchService::class);

$odds = \app\model\PromotedOdd::query()
    ->whereIn('type', ['over', 'under'])
    ->whereNotNull('result')
    ->where('created_at', '>=', '2025/05/20 0:00:00+08:00')
    ->get(['match_id', 'period'])
    ->toArray();

foreach ($odds as $odd) {

    $match = \app\model\Match1::query()
        ->where('id', '=', $odd['match_id'])
        ->first([
            'score1',
            'score2',
            'corner1',
            'corner2',
            'score1_period1',
            'score2_period1',
            'corner1_period1',
            'corner2_period1'
        ]);
    if(!$match) continue;
    $match = $match->toArray();

    echo '重新计算赛果 ' . $odd['match_id'] . PHP_EOL;

    $service->setMatchScore([
        'match_id' => $odd['match_id'],
        'score1' => $match['score1'],
        'score2' => $match['score2'],
        'corner1' => $match['corner1'],
        'corner2' => $match['corner2'],
        'score1_period1' => $match['score1_period1'],
        'score2_period1' => $match['score2_period1'],
        'corner1_period1' => $match['corner1_period1'],
        'corner2_period1' => $match['corner2_period1'],
        'period1' => $odd['period'] === 'period1'
    ]);
}