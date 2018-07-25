<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10 0010
 * Time: 上午 11:27
 */

namespace App\Models\Wallet;

use App\Models\common\BaseModel;

class DgasModel extends BaseModel
{
    private $log = 'gas_log';
    private $dgas = 'dgame2dgas';

    public function __construct()
    {
        parent::__construct();
    }

    /*========================================================GAS_LOG===========================================================================================*/
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
        $result = $this->masterDb->select($para)->from($this->log);
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

    /**
     * 批量插入gas_log
     * @param $data
     * @param string $trans
     * @return bool
     */
    public function Insert_Gaslog($data, $trans = '')
    {
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
     * 扣除用户dgas&手续费
     * @param $para
     * @param string $trans
     * @return array
     */
    public function consumeDgas_new($para, $trans = '', $isCharge = false, $isapplication = false)
    {
        //判断（手续费+dgas是否大于用户余额）
        var_dump('测试-------------------', $para);
        $isEnough = yield $this->getObject(InfoModel::class)
            ->balanceIsEnough(['appid' => $para['appid'], 'address' => $para['fromaddr']], $para['Scharge']);
        var_dump($isEnough);
        if (!$isEnough) {
            $this->log('dgas2subchain', [$para['Scharge'] + $para['dgas']], '余额不足');
            return ['status' => false, 'msg' => '余额不足'];
        }

        //扣手续费
//        前期扣除开发商dgas，超过配置时间改为扣用户dgas
        $application = yield $this->getObject(ApplicationModel::class);
        $swich_time = getInstance()->config->get('app2account_time', '2018-07-10 00:00:00');
        if (time() < strtotime($swich_time) && $isapplication) {
            //扣除开发商dgas
            $result = $application->UpDataByQuery(['dgas' => [$para['Scharge'], '-']], ['appid' => $para['appid']], $trans);
        } else {
            $num = $isCharge ? $para['Scharge'] : $para['dgas'] + $para['Scharge'];
            $data = ['dgas' => [$num, '-'],
                'fee' => [$para['Scharge'], '+']];
            $query = ['appid' => $para['appid'],
                'address' => $para['fromaddr']];
            $rel = yield $this->getObject(AccountModel::class)->UpDataByQuery($data, $query, $trans);
            //累加商商手续费总和
            $apl = yield $this->getObject(ApplicationModel::class)
                ->UpDataByQuery(['totalfee' => [$para['Scharge'], '+']], ['appid' => $para['appid']], $trans);
            $result = $rel && $apl;
        }
        return ['status' => $result, 'mes' => $result ? '操作成功' : '操作失败'];
    }

    /**
     * 添加子链和gas日志 Dgas2subchain
     * @param $para
     * @param $id
     */
    public function AddLog_Dgas2s($para, $id)
    {
        //增加子链日志（subchain_log）
        $subchain_log = ['appid' => $para['appid'],
            'type' => self::SUB_DGAS,
            'address' => $para['toaddr'],
            'exchange_id' => $id,
            'create_time' => time()];
        $s_log = yield $this->getObject(SubchainModel::class)->InsertLog($subchain_log, '');
//        增加gas_log
        $gas_log = [['appid' => $para['appid'],
            'address' => $para['fromaddr'],
            'exchange_id' => $id,
            'type' => self::DGAS_CONSUME,
            'flag' => self::DGAS_NORMAL],
            ['appid' => $para['appid'],
                'address' => $para['fromaddr'],
                'exchange_id' => $id,
                'type' => self::DGAS_CONSUME,
                'flag' => self::DGAS_CONSUME_SCHARGE]];
        $d_log = yield $this->Insert_Gaslog($gas_log);

    }


    /**
     * 添加子链和gas日志 Dgame2subchain
     * @param $para
     * @param $id
     */
    public function AddLog_Dgame2s($para, $ids)
    {
        //增加子链日志（subchain_log）
        $subchain_log = ['appid' => $para['appid'],
            'type' => self::SUB_DGAME,
            'address' => $para['addr'],
            'exchange_id' => $ids['sub'],
            'create_time' => time()];
        $s_log = yield $this->getObject(SubchainModel::class)->InsertLog($subchain_log, '');
        $subchain_log2 = ['appid' => $para['appid'],
            'type' => self::SUB_DGAME2SUB,
            'address' => $para['addr'],
            'exchange_id' => $ids['d2s'],
            'create_time' => time()];
        $s_log2 = yield $this->getObject(SubchainModel::class)->InsertLog($subchain_log2, '');
//        增加gas_log
        $gas_log = [['appid' => $para['appid'],
            'address' => $para['addr'],
            'exchange_id' => $ids['d2d'],
            'type' => self::DGAS_RECHARGE,
            'flag' => self::DGAS_DGAME2S],
            ['appid' => $para['appid'],
                'address' => $para['addr'],
                'exchange_id' => $ids['d2s'],
                'type' => self::DGAS_CONSUME,
                'flag' => self::DGAS_DGAME2S],
            ['appid' => $para['appid'],
                'address' => $para['addr'],
                'exchange_id' => $ids['d2s'],
                'type' => self::DGAS_CONSUME,
                'flag' => self::DGAS_CONSUME_SCHARGE]];
        $d_log = yield $this->Insert_Gaslog($gas_log);

    }


    /*==========================================DGAS============================================================*/

    public function getDataByQuery_D2D($para, $query = '', $start = 0, $pageSize = 10)
    {
        $result = $this->masterDb->select($para)->from($this->dgas);
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

    /**
     * 批量插入订单
     * @param $data 订单数组
     * @param string $trans 事务
     * @return bool
     */
    public function InsertByData($data, $trans = '')
    {
        $num = 0;
        foreach ($data as $k => $v) {
            $res = yield  $this->masterDb->insert($this->dgas)->set($data[$k])->go($trans);
            if ($res['result'])
                $num++;
        }
        if ($num != count($data))
            return false;
        return true;
    }

    /**
     * Dgame2subchain订单
     * @param $data
     * @param $trans
     * @return array
     */
    public function AddDgame2Dgas($data, $trans)
    {
        $orderId = isset($data['order_sn']) ? $data['order_sn'] : $this->guid();
        $d2s = ['order_sn' => $orderId,
            'status' => 0,
            'create_time' => time(),
            'update_time' => time()];
        $d2s = array_merge($data, $d2s);
        $res = yield  $this->masterDb->insert($this->dgas)->set($d2s)->go($trans);
        return $res['affected_rows'] > 0 ? ['status' => true, 'data' => ['order' => $orderId, 'id' => $res['insert_id']]] : ['status' => false];
    }


}