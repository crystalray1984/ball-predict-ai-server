<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\PromotedOdd;
use app\model\SurebetV2Promoted;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;

class SurebetV2Service
{
    public function getList(array $params): array
    {
        $query = SurebetV2Promoted::query()
            ->join('v_match', 'v_match.id', '=', 'surebet_v2_promoted.match_id');

        if (!empty($params['start_date'])) {
            $query->where(
                'v_match.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'v_match.match_time',
                '<',
                Carbon::parse($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        $list = $query
            ->orderBy('surebet_v2_promoted.id', 'DESC')
            ->get([
                'surebet_v2_promoted.*',
                'v_match.match_time',

            ])
            ->toArray();

        return $list;
    }
}