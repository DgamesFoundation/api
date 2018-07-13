<?php
/**
 * Demo模型
 */
namespace App\Models\Wallet;

use PG\MSF\Models\Model;
use App\Models\common\BaseModel;

class InfoModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    /**根据参数，appid获得相应值
     * @param $appid 子链合约标识地址
     * @param $para  ApplicationModel 相应字段
     * @return mixed 相应字段值
     */
    public function getParaByAppid($appid,$para){
        $balance=yield $this->masterDb->select($para)->from('application')
            ->where('appid', $appid)->go();
        return $balance['result'][0][$para];
    }

    /**
     * @param $order 订单号
     * @param $table 数据表名
     * @return mixed 密钥
     */
    public function getKeyByOrder($order,$table){
        $balance=yield $this->masterDb->select('appid')->from($table)
            ->where('order_sn', $order)->go();
        $key=yield $this->getParaByAppid($balance['result'][0]['appid'],'secretKey');
        return $key;
    }

    /**
     * @param $order 订单号
     * @param $table 需要更新的表名
     * @return bool
     */
    public function UpOrderStatus($order,$table){
        $up= yield $this->masterDb->update($table)->set('status',1)->where('order_sn', $order)->go();
        var_dump($up);
        return  $up['affected_rows']>0;
    }
    public function getAppidByOrder($order,$table){
        $balance=yield $this->masterDb->select('appid')->from($table)
            ->where('order_sn', $order)->go();
        return $balance['result'][0]['appid'];
    }
    public function getAppidByOrder1($order,$table){
        return $order;
        $balance=yield $this->masterDb->select('appid')->from($table)
            ->where('order_sn', $order)->go();
        return $balance;
        return $balance['result'][0]['appid'];
    }
    public function getParaByAppid1($appid,$para){
        $balance=yield $this->masterDb->select($para)->from('application')
            ->where('appid', $appid)->go();
        return $balance['result'][0];
    }

    public function getDataByQuery($para,$table,$query=''){
        $result=$this->masterDb->select($para)->from($table);
        if($query){
            foreach ($query as $k=>$v){
                $result->where($k,$v);
            }
        }
        $result=yield $result->go();
        return $result['result'];
    }


}