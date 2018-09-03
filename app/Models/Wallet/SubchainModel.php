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
    private $s2s = 'subchain2subchain';
    private $dgame2s = 'dgame2subchain';
    private $dgas2s = 'dgas2subchain';
    private $log = 'subchain_log';

    public function __construct()
    {
        parent::__construct();
    }

    /*=============================================SUBCHAIN2SUBCHAIN===================================================================*/
    /**
     * 查询
     * @param $para 需要获取的字段
     * @param string $query 查询条件
     * @param int $start limit 起始
     * @param int $pageSize 每页显示条数
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

    /**插入数据subchain2subchain
     * @param $data
     * @return bool
     */
    public function Inserts2s($data, $trans = '')
    {
        $res = yield  $this->masterDb->insert($this->s2s)->set($data)->go($trans);
        return $res['affected_rows'] > 0 ? ['id' => $res['insert_id']] : false;
    }

    /**
     * 根据条件更新数据
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
    /*====================================DGAS2SUBCHAIN==================================================================*/
    /**插入数据Dgas2subchain
     * @param $data
     * @return bool
     */
    public function InsertDgas2s($data, $trans = '')
    {
        $res = yield  $this->masterDb->insert($this->dgas2s)->set($data)->go($trans);
        return $res['affected_rows'] > 0 ? ['id' => $res['insert_id']] : false;
    }

    /**
     * Dgas2subchain订单
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
    /**插入数据 subchain_log
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
     * 查询
     * @param $para 需要获取的字段
     * @param string $query 查询条件
     * @param int $start limit 起始
     * @param int $pageSize 每页显示条数
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
    /*======================================DGAME2SUBCHAIN=============================================================*/
    /**
     * Dgame2subchain订单
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
     * 根据appid算出子链比例
     * @param $appid
     * @return int
     */
    public function getRedio($appid)
    {
        return 1;
    }

    /**
     *  订单生成后，发出充值子链请求
     * @param $para
     * @param $appid
     * @return mixed
     * @throws \PG\MSF\Client\Exception
     */
    public function SubRecharge($para, $appid)
    {
        $info = $this->getObject(ApplicationModel::class);
        $rel = yield $info->getParaByAppid($appid, 'pay_callback_url,precisions');
        $mul_len = getInstance()->config->get('dgas.mul_num', 1000000000000000000);
        $para['dgas'] = $this->calc($para['dgas'], $mul_len, 'mul');
        $para['amount'] = $this->calc($para['amount'], $rel[0]['precisions'], 'mul');
        $client = $this->getObject(Client::class);
        $result = yield $client->goSinglePost($rel[0]['pay_callback_url'], $para);
        return $result['body'];
    }


    /**
     * 根据条件更新数据
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
//        var_dump($data['txid'], 'ttttt');
//        if ($query) {
//            foreach ($query as $k => $v) {
//                $result->where($k, $v);
//                $result2->where($k, $v);
//                $result3->where($k, $v);
//            }
//        }

        return ($result['affected_rows'] > 0 && $result2['affected_rows'] > 0 && $result3['affected_rows'] > 0);
    }

}