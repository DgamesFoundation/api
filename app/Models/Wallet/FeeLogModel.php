<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10 0010
 * Time: 下午 12:13
 */

namespace App\Models\Wallet;
use App\Models\common\BaseModel;

class FeeLogModel extends BaseModel
{
    public $table='fee_log';

    public function __construct()
    {
        parent::__construct();
    }

    /**插入数据
     * @param $data
     * @return bool
     */
    public function Insert($data,$trans=''){
        $res= yield  $this->masterDb->insert($this->table)->set($data)->go($trans);
        return $res['affected_rows']==1;
    }
}