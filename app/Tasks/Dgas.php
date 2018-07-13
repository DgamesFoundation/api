<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/29 0029
 * Time: 下午 3:43
 */

namespace App\Tasks;

use \PG\MSF\Tasks\Task;
class Dgas extends  Task
{
    /**
     * 连接池执行同步查询
     *
     * @return array
     */
    public function syncMySQLPool()
    {
        $user = $this->getMysqlPool('master')->select("*")->from("test")->go();
        return $user;
    }

    /**
     * 代理执行同步查询
     *
     * @return array
     */
    public function syncMySQLProxy()
    {
        $user = $this->getMysqlProxy('master_slave')->select("*")->from("test")->go();
        return $user;
    }

    public static function dgame2dgas($pool){
        $arr=array('name'=>'haha');
        $res1=yield  $pool->insert('test')->set($arr)->go();
        return $res1;
    }
}