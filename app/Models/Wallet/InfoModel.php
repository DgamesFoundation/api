<?php
/**
 * Demo模型
 */
namespace App\Models\Wallet;

use PG\MSF\Models\Model;
use App\Models\common\BaseModel;
use App\Models\Wallet\ApplicationModel;
use App\Models\Wallet\AccountModel;

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
        return  $up['affected_rows']>0;
    }

    /**
     * 根据根据订单号和表名得到Appid
     * @param $order
     * @param $table
     * @return mixed
     */
    public function getAppidByOrder($order,$table){
        $balance=yield $this->masterDb->select('appid')->from($table)
            ->where('order_sn', $order)->go();
        return $balance['result'][0]['appid'];
    }
    /**
     * 根据表名和查询条件返回对应字段的数据
     * @param $para
     * @param $table
     * @param string $query
     * @return mixed
     */
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

    /**
     * 判断余额是否不足
     * @param $query
     * @param $num
     * @return bool
     */
    public function balanceIsEnough($query,$num){
        $swich_time=getInstance()->config->get('app2account_time','2018-07-10 00:00:00');
       // if(time()<strtotime($swich_time))
         //   $balance =yield $this->getObject(ApplicationModel::class)->getDataByQuery('dgasfee as dgas',['appid'=>$query['appid']]);
       // else
            $balance =yield $this->getObject(AccountModel::class)->getParaByData('dgas',['appid'=>$query['appid'],'address'=>$query['address']]);
        var_dump('比较余额',$balance,$num);
        return $balance[0]['dgas']=$balance[0]['dgas']>=$num ? true : false;
    }


}