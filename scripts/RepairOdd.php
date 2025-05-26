<?php declare(strict_types=1);

namespace scripts;

use app\model\Match1;
use app\model\Odd;
use app\model\PromotedOdd;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../support/bootstrap.php";

class RepairOdd
{
    public function run(): void
    {
        //为标记为skip的盘口生成对应的推荐
        $this->processSkipOdds();
    }

    /**
     * 为标记为skip的盘口生成对应的推荐
     * @return void
     */
    protected function processSkipOdds(): void
    {
        $matches = [];

        $odds = Odd::query()
            ->where('status', '=', 'skip')
            ->get()
            ->toArray();

        foreach ($odds as $odd) {
            if (empty($matches[$odd['match_id']])) {
                $match = Match1::query()
                    ->where('id', '=', $odd['match_id'])
                    ->first()
                    ->toArray();
                if (!$match['has_score']) {
                    Odd::query()
                        ->where('id', '=', $odd['id'])
                        ->update([
                            'status' => 'promoted',
                        ]);
                    continue;
                }
                $matches[$odd['match_id']] = $match;
            } else {
                $match = $matches[$odd['match_id']];
            }

            $promoted = [
                'match_id' => $odd['match_id'],
                'odd_id' => $odd['id'],
                'skip' => 'setting',
                'variety' => $odd['variety'],
                'period' => $odd['period'],
                'type' => match ($odd['type']) {
                    'ah1' => 'ah2',
                    'ah2' => 'ah1',
                    'over' => 'under',
                    'under' => 'over',
                },
                'condition' => match ($odd['type']) {
                    'ah1', 'ah2' => bcsub('0', $odd['condition']),
                    default => $odd['condition'],
                },
                'back' => 1,
            ];

            $result = get_odd_score($match, $promoted);
            $promoted += [
                'result' => $result['result'],
                'result1' => $result['result'],
                'score' => $result['score'],
                'score1' => $result['score1'],
                'score2' => $result['score2'],
            ];

            PromotedOdd::insert($promoted);
            Odd::query()
                ->where('id', '=', $odd['id'])
                ->update([
                    'status' => 'promoted',
                ]);

            echo "添加推荐 odd_id=" . $odd['id'] . PHP_EOL;
        }
    }
}

(new RepairOdd())->run();