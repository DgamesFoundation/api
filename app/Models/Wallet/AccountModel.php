<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10 0010
 * Time: 上午 11:27
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;
class AccountModel extends  BaseModel
{
    public $table='account';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 根据参数和appid返回相对应字段信息
     * @param $arr 用户appid和address
     * @param $para  要查询的字段
     * @return mixed
     */
    public function getParaByData($arr,$para){
        $balance=yield $this->masterDb->select($para)->from($this->table)
            ->where('appid', $arr['appid'])
            ->andwhere('address',$arr['address'])->go();
        return $balance['result'];
    }
}