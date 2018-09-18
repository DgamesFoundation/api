<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10 0010
 * Time: 上午 11:27
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;

class AccountModel extends BaseModel
{
    public $table = '**';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * search
     * @param $para
     * @param $query
     * @return mixed
     */
    public function getParaByData($para, $query)
    {
        $result = yield $this->masterDb->select($para)->from($this->table);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result = yield $result->go();
        return $result['result'];
    }

    /**
     * Update data according to conditions
     * @param $data
     * @param string $query
     * @param string $trans
     * @return bool
     */
    public function UpDataByQuery($data, $query = '', $trans = '')
    {
        $result = yield $this->masterDb->update($this->table)->set($data);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result = yield $result->go($trans);
        var_dump('account', $result);
        return $result['affected_rows'] > 0;
    }
}