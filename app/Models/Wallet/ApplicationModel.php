<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10 0010
 * Time: 上午 11:07
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;
class ApplicationModel extends  BaseModel
{
    public $table='application';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 更新记录
     * @param $appid 子链合约标识
     * @param $data   更新数组
     * @return bool
     */
    public function UpByAppid($appid,$data){
        $up= yield $this->masterDb->update($this->table)->set($data)->where('appid', $appid)->go();
        return $up['affected_rows']==1;
    }


    /**
     * 根据参数和appid返回相对应字段信息
     * @param $appid 子链合约标识
     * @param $para  要查询的字段
     * @return mixed
     */
    public function getParaByAppid($appid,$para){
        $balance=yield $this->masterDb->select($para)->from($this->table)
            ->where('appid', $appid)->go();
        return $balance['result'];
    }

    /**
     * 查询
     * @param $para 需要查询的字段
     * @param string $query  查询条件
     * @return mixed
     */
    public function getDataByQuery($para,$query=''){
        $result=$this->masterDb->select($para)->from($this->table);
        if($query){
            foreach ($query as $k=>$v){
                $result->where($k,$v);
            }
        }
        $result=yield $result->go();
        return $result['result'];
    }
}