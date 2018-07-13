<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10 0010
 * Time: 上午 11:27
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;
class DgasModel extends  BaseModel
{
    private $log='gas_log';
    private $dgas='dgame2dgas';
    public function __construct()
    {
        parent::__construct();
    }

    /*========================================================GAS_LOG===========================================================================================*/
    /**
     * 查询
     * @param $para 需要获取的字段
     * @param string $query 查询条件
     * @param int $start  limit 起始
     * @param int $pageSize  每页显示条数
     * @return mixed
     */
    public function getDataByQuery($para,$query='',$start=0,$pageSize=10){
        $result=$this->masterDb->select($para)->from($this->log);
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




    /*==========================================DGAS============================================================*/

    public function getDataByQuery_D2D($para,$query='',$start=0,$pageSize=10){
        $result=$this->masterDb->select($para)->from($this->dgas);
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

    /**
     * 批量插入订单
     * @param $data 订单数组
     * @param string $trans 事务
     * @return bool
     */
    public function InsertByData($data,$trans=''){
        $num=0;
        foreach ($data as $k=>$v){
            $res=yield  $this->masterDb->insert($this->dgas)->set($data[$k])->go($trans);
            if($res['result'])
                $num++;
        }
        if($num!=count($data))
            return false;
        return true;
    }


}