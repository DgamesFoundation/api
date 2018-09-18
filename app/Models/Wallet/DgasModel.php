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
    private $log = '**';
    private $dgas = '**';

    public function __construct()
    {
        parent::__construct();
    }

    /*========================================================GAS_LOG===========================================================================================*/
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

    public function getDataByQueryList($para, $query = '', $start = 0, $pageSize = 10)
    {
        $result = $this->masterDb->select($para)->from($this->log);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result->where('flag', 1, '!=');
        $result->orderby('create_time', 'desc')
            ->limit($pageSize, $start);
        $result = yield $result->go();
        return $result['result'];
    }

    /**
     * Bulk insert gas_log
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
     * Deduct customer dgas& service fee
     * @param $para
     * @param string $trans
     * @return array
     */
    public function consumeDgas_new($para, $trans = '', $isCharge = false, $isapplication = false)
    {
        //todo:
    }

    /**
     * add log dgas2s
     * @param $para
     * @param $id
     */
    public function AddLog_Dgas2s($para, $id)
    {
        //（subchain log）
        $subchain_log = ['appid' => $para['appid'],
            'type' => self::SUB_DGAS,
            'address' => $para['toaddr'],
            'exchange_id' => $id,
            'create_time' => time()];
        $s_log = yield $this->getObject(SubchainModel::class)->InsertLog($subchain_log, '');
//        gas log
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
     *  DgameTosubchain
     * @param $para
     * @param $id
     */
    public function AddLog_Dgame2s($para, $ids)
    {
        //（subchain  log）
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
//        gas_log
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
     * Bulk insert order
     * @param $data
     * @param string $trans
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
     * Dgame2subchain Order
     * @param $data
     * @param $trans
     * @return array
     */
    public function AddDgame2Dgas($data, $trans)
    {
        //todo:
    }

    /**
     * Dgame2subchain order
     * @param $data
     * @param $trans
     * @return array
     */
    public function AddDgame2Dgas_D2S($data, $trans)
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
    public function UpDataByQuery_D2D($data, $query = '', $trans = '')
    {
        $result = yield $this->masterDb->update($this->dgas)->set($data);
        if ($query) {
            foreach ($query as $k => $v) {
                $result->where($k, $v);
            }
        }
        $result = yield $result->go($trans);
        return $result['affected_rows'] > 0;
    }


}