<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/11 0011
 * Time: 上午 10:56
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;

class RewardModel extends BaseModel
{
    private $table = '**';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * search
     * @param $para
     * @param string $query
     * @param int $start limit
     * @param int $pageSize
     * @return mixed
     */
    public function getDataByQuery($para, $query = '', $start = 0, $pageSize = 10)
    {
        $result = $this->masterDb->select($para)->from($this->table);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result->orderby('create_time', 'desc')
            ->limit($pageSize, $start);
        $result = yield $result->go();
        return $result['result'];
    }
}