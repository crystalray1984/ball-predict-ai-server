<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\RockBallOdd;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 滚球盘业务逻辑
 */
class RockBallService
{
    public function createQuery(array $params): Builder
    {
        $query = RockBallOdd::query()
            ->join('v_match', 'v_match.id', '=', 'rockball_odd.match_id')
            ->leftJoin('rockball_promoted', 'rockball_promoted.promote_id', '=', 'rockball_odd.id');

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

        if (isset($params['promoted'])) {
            switch ($params['promoted']) {
                case 0:
                    $query->whereNull('rockball_promoted.id');
                    break;
                case 1:
                    $query->whereNotNull('rockball_promoted.id');
                    break;
            }
        }

        if (isset($params['order'])) {
            if ($params['order'] === 'match_time') {
                $query->orderBy('v_match.match_time', 'DESC');
            } else if ($params['order'] === 'promote_time') {
                $query->orderBy('rockball_promoted.id', 'DESC');
            }
        }
        $query->orderBy('rockball_odd.id', 'DESC');

        $query->select([
            'rockball_odd.*',
            'v_match.match_time',
            'v_match.team1_name',
            'v_match.team2_name',
            'v_match.tournament_name',
            'rockball_promoted.is_valid',
            'rockball_promoted.value AS promoted_value',
            'rockball_promoted.result',
            'rockball_promoted.score',
            'rockball_promoted.created_at AS promoted_at',
        ]);

        return $query;
    }

    public function getOddList(array $params): array
    {
        return $this->createQuery($params)
            ->get()
            ->toArray();
    }

    public function setIsOpen(int $id, int $is_open): void
    {
        RockBallOdd::query()
            ->where('id', '=', $id)
            ->update(['is_open' => $is_open]);
    }

    public function exportList(array $params): string
    {
        return '';
    }
}