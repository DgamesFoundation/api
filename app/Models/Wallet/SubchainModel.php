<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/11 0011
 * Time: 上午 10:41
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;
use \PG\MSF\Client\Http\Client;
use App\Models\Wallet\ApplicationModel;

class SubchainModel extends BaseModel
{
    private $s2s = '**';
    private $dgame2s = '**';
    private $dgas2s = '**';
    private $log = '**';

    public function __construct()
    {
        parent::__construct();
    }

    /*=============================================SUBCHAINTOSUBCHAIN===================================================================*/
    /**
     * search
     * @param $para
     * @param string $query
     * @param int $start limit
     * @param int $pageSize
     * @return mixed
     */
    public function getDataByQuery($para, $query = '', $start = 0, $pageSize = 10)
    {
        $result = $this->masterDb->select($para)->from($this->s2s);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result->orderby('create_time', 'desc')
            ->limit($pageSize, $start);
        $result = yield $result->go();
        return $result['result'];
    }

    /**Insert subchainTosubchain
     * @param $data
     * @return bool
     */
    public function Inserts2s($data, $trans = '')
    {
        $res = yield  $this->masterDb->insert($this->s2s)->set($data)->go($trans);
        return $res['affected_rows'] > 0 ? ['id' => $res['insert_id']] : false;
    }

    /**
     * Update data according to conditions
     * @param $data
     * @param string $query
     * @param string $trans
     * @return bool
     */
    public function UpDataByQuery_S2S($data, $query = '', $trans = '')
    {
        $result = yield $this->masterDb->update($this->s2s)->set($data);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result = yield $result->go($trans);
        return $result['affected_rows'] > 0;
    }
    /*====================================DGASTOSUBCHAIN==================================================================*/
    /**Insert DgasTosubchain
     * @param $data
     * @return bool
     */
    public function InsertDgas2s($data, $trans = '')
    {
        $res = yield  $this->masterDb->insert($this->dgas2s)->set($data)->go($trans);
        return $res['affected_rows'] > 0 ? ['id' => $res['insert_id']] : false;
    }

    /**
     * DgasTosubchain Order
     * @param $data
     * @param $trans
     * @return array
     */
    public function AddDgas2subchain($data, $trans)
    {
        $orderId = isset($data['order_sn']) ? $data['order_sn'] : $this->guid();
        $d2s = ['order_sn' => $orderId,
            'status' => 0,
            'create_time' => time(),
            'update_time' => time()];
        $d2s = array_merge($data, $d2s);
        $d2s_res = yield $this->InsertDgas2s($d2s, $trans);
        $d2s_res['order'] = $orderId;
        return $d2s_res ? ['status' => true, 'data' => $d2s_res] : ['status' => false];
    }
    /*=====================================SUBCHAIN_LOG============================================================================*/
    /**subchain log
     * @param $data
     * @return bool
     */
    public function InsertLog($data, $trans = '')
    {
        $res = yield  $this->masterDb->insert($this->log)->set($data)->go($trans);
        return $res['affected_rows'] > 0 ? $res['insert_id'] : false;

        $num = 0;
        foreach ($data as $k => $v) {
            $data[$k]['create_time'] = time();
            $res1 = yield  $this->masterDb->insert($this->log)->set($data[$k])->go($trans);
            if ($res1['result'])
                $num++;
        }
        return $num != count($data);

    }

    /**
     * search
     * @param $para
     * @param string $query
     * @param int $start limit
     * @param int $pageSize
     * @return mixed
     */
    public function getDataByQuery_subLog($para, $query = '', $start = 0, $pageSize = 10)
    {
        $result = $this->masterDb->select($para)->from($this->log);
        if ($query) {
            foreach ($query as $k => $v) {
                if (is_array($v)) {
                    $result->where($k, $v[0], $v[1]);
                } else
                    $result->where($k, $v);
            }
        }
        $result->orderby('create_time', 'desc')
            ->limit($pageSize, $start);
        $result = yield $result->go();
        return $result['result'];
    }
    /*======================================DGAMETOSUBCHAIN=============================================================*/
    /**
     * Dgame2subchain order
     * @param $data
     * @param $trans
     * @return array
     */
    public function AddDgame2subchain($data, $trans)
    {
        $orderId = $this->guid();
        $d2s = ['order_sn' => $orderId,
            'status' => 0,
            'create_time' => time(),
            'update_time' => time()];
        $d2s = array_merge($data, $d2s);
        $res = yield  $this->masterDb->insert($this->dgame2s)->set($d2s)->go($trans);
        return $res['affected_rows'] > 0 ? ['status' => true, 'data' => ['order' => $orderId, 'id' => $res['insert_id']]] : ['status' => false];
    }

    /**
     * Calculate the subchain proportion according to appid
     * @param $appid
     * @return int
     */
    public function getRedio($appid)
    {
        return 1;
    }

    /**
     *
     * @param $para
     * @param $appid
     * @return mixed
     * @throws \PG\MSF\Client\Exception
     */
    public function SubRecharge($para, $appid)
    {
        //todo:
    }


    /**
     * Update data according to conditions
     * @param $data
     * @param string $query
     * @param string $trans
     * @return bool
     */
    public function UpDataByQuery_D2S($data, $query = '', $trans = '')
    {

        $result = yield $this->masterDb->update($this->dgame2s)
            ->set($data)
            ->where('order_sn', $query['order_sn'])
            ->go();
        $result2 = yield $this->masterDb->update($this->dgas2s)
            ->set(['order_sn' => $data['txid']])
            ->where('order_sn', $query['order_sn'])
            ->go();
        $result3 = yield $this->masterDb->update('dgame2dgas')
            ->set($data)
            ->where('order_sn', $query['order_sn'])
            ->go();

        return ($result['affected_rows'] > 0 && $result2['affected_rows'] > 0 && $result3['affected_rows'] > 0);
    }

}