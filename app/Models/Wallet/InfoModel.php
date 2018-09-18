<?php

namespace App\Models\Wallet;

use App\Models\common\BaseModel;


class InfoModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    /**According to the parameters, appid gets the corresponding value
     * @param $appid
     * @param $para  ApplicationModel
     * @return mixed
     */
    public function getParaByAppid($appid, $para)
    {
        $balance = yield $this->masterDb->select($para)->from('**')
            ->where('appid', $appid)->go();
        return $balance['result'][0][$para];
    }

    /**
     * @param $order
     * @param $table
     * @return mixed
     */
    public function getKeyByOrder($order, $table)
    {
        $balance = yield $this->masterDb->select('appid')->from($table)
            ->where('order_sn', $order)->go();
        $key = yield $this->getParaByAppid($balance['result'][0]['appid'], 'secretKey');
        return $key;
    }

    /**
     * @param $order
     * @param $table
     * @return bool
     */
    public function UpOrderStatus($order, $table)
    {
        $up = yield $this->masterDb->update($table)->set('status', 1)->where('order_sn', $order)->go();
        return $up['affected_rows'] > 0;
    }

    /**
     * Get the Appid based on the order number and table name
     * @param $order
     * @param $table
     * @return mixed
     */
    public function getAppidByOrder($order, $table)
    {
        $balance = yield $this->masterDb->select('appid')->from($table)
            ->where('order_sn', $order)->go();
        return $balance['result'][0]['appid'];
    }

    /**
     * Returns data for the corresponding field based on the table name and query criteria
     * @param $para
     * @param $table
     * @param string $query
     * @return mixed
     */
    public function getDataByQuery($para, $table, $query = '')
    {
        $result = $this->masterDb->select($para)->from($table);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result = yield $result->go();
        return $result['result'];
    }

    /**
     * Determine if the balance is insufficient
     * @param $query
     * @param $num
     * @return bool
     */
    public function balanceIsEnough($query, $num)
    {
        //todo:
    }


}