<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\PromotedView;
use Illuminate\Database\Eloquent\Builder;

/**
 * 模型3业务逻辑
 */
class Model3Service
{
    /**
     * 获取推荐列表
     * @param array $params
     * @return array
     */
    public function getList(array $params): array
    {
        return $this->createQuery($params)->get()->toArray();
    }

    protected function createQuery(array $params): Builder
    {
        $query = PromotedView::query()
            ->where('channel', '=', 'model3');

        if (!empty($params['start_date'])) {
            $query->where(
                'match_time',
                '>=',
                crown_time($params['start_date'])->toISOString(),
            );
        }

        if (!empty($params['end_date'])) {
            $query->where(
                'match_time',
                '<',
                crown_time($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        if (isset($params['sort'])) {
            $query->orderByDesc($params['sort']);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query;
    }
}