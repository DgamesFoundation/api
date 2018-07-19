<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/11 0011
 * Time: 上午 10:41
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;
class SubchainModel extends  BaseModel
{
    private $s2s='subchain2subchain';
    private $dgame2s='dgame2subchain';
    private $dgas2s='dgas2subchain';
    private $log='subchain_log';
    public function __construct()
    {
        parent::__construct();
    }

    /*=============================================SUBCHAIN2SUBCHAIN===================================================================*/
    /**
     * 查询
     * @param $para 需要获取的字段
     * @param string $query 查询条件
     * @param int $start  limit 起始
     * @param int $pageSize  每页显示条数
     * @return mixed
     */
    public function getDataByQuery($para,$query='',$start=0,$pageSize=10){
        $result=$this->masterDb->select($para)->from($this->s2s);
        if($query){
            foreach ($query as $k=>$v){
                $result->where($k,$v);
            }
        }
        $result->orderby('create_time','desc')
            ->limit($pageSize,$start);
        $result=yield $result->go();
        return $result['result'];
    }

    /**插入数据Dgas2subchain
     * @param $data
     * @return bool
     */
    public function InsertDgas2s($data,$trans=''){
        $res= yield  $this->masterDb->insert($this->dgas2s)->set($data)->go($trans);
        return $res['affected_rows']>0 ? ['id'=>$res['insert_id']] : false;
    }

    /**插入数据 subchain_log
     * @param $data
     * @return bool
     */
    public function InsertLog($data,$trans=''){
        $res= yield  $this->masterDb->insert($this->log)->set($data)->go($trans);
        return $res['affected_rows']>0 ? $res['insert_id'] : false;
    }

    /**
     * Dgas2subchain订单
     * @param $data
     * @param $trans
     * @return array
     */
    public function AddDgas2subchain($data,$trans){
        $orderId=isset($data['order_sn']) ? $data['order_sn'] :  $this->guid();
        $d2s=['order_sn'=>$orderId,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time()];
        $d2s=array_merge($data,$d2s);
        $d2s_res= yield $this->InsertDgas2s($d2s,$trans);
        $d2s_res['order']=$orderId;
        return $d2s_res ? ['status'=>true,'data'=>$d2s_res] : ['status'=>false];
    }

    /**
     * Dgame2subchain订单
     * @param $data
     * @param $trans
     * @return array
     */
    public function AddDgame2subchain($data,$trans){
        $orderId=$this->guid();
        $d2s=['order_sn'=>$orderId,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time()];
        $d2s=array_merge($data,$d2s);
        $res= yield  $this->masterDb->insert($this->dgame2s)->set($d2s)->go($trans);
        return $res['affected_rows']>0 ? ['status'=>true,'data'=>['order'=>$orderId,'id'=>$res['insert_id']]] : ['status'=>false];
    }

    /**
     * 根据appid算出子链比例
     * @param $appid
     * @return int
     */
    public function getRedio($appid){
        return 1;
    }

}