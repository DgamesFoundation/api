<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/25
 * Time: 12:08
 */

namespace App\Controllers\Wallet;

use App\Controllers\Common\BaseController;
use App\Models\Wallet\DgasModel;
use App\Models\Wallet\InfoModel;
use App\Models\Wallet\SubchainModel;
use App\Models\Wallet\RewardModel;
use App\Models\Wallet\AccountModel;

class Dgas extends BaseController
{

    /**
     *  dgas transaction records
     * Parameters：appid、address、page、pageSize
     */
    public function actionRechargeList()
    {

        $get = $this->getContext()->getInput()->getAllPostGet();
        if (!(isset($get['appid']) && isset($get['address']) && isset($get['page']) && isset($get['pageSize']))) {
            $this->resCode = 1;
            $this->resMsg = "Parameters of the abnormal";
            $this->interOutputJson(403);
        }

        //Set the paging
        $size = $get['pageSize'] > 50 ? 50 : (int)$get['pageSize'];
        $size = $size < 0 ? 1 : $size;
        $get['page'] = $get['page'] < 0 ? 1 : $get['page'];
        $start = ($get['page'] - 1) * $size;

        //get dgas list
        $gas_log = $this->getObject(DgasModel::class);
        $query = ['appid' => $get['appid'],
            'address' => $get['address']];
        $result = yield $gas_log->getDataByQueryList("type,exchange_id,flag,id", $query, $start, $size);
        $result = yield $this->getListDtail($result, $get['appid']);
        $this->data = $result ? $result : null;
        $this->interOutputJson(200);
    }

    /**
     * dgameToDgas
     */
    public function actionAddAccount()
    {
        $para = json_decode($this->getContext()->getInput()->getRawContent(), true);
        $params = yield $this->checkParamsExists($para, ['appid', 'fromaddr', 'addr', 'dgame']);
        if (!$params) {
            $this->resCode = 1;
            $this->resMsg = "Parameters of the abnormal";
            $this->encryptOutput(403);
            return;
        }
        $dgas_ratio = getInstance()->config->get('dgas.ratio', 100000);
        $dgas = $para['dgame'] * $dgas_ratio;

        //deduct service charge
        $sc_arr = ['appid' => strlen($para['appid']),
            'fromaddr' => strlen($para['fromaddr']),
            'address' => strlen($para['addr']),
            'remark' => 0,
            'dgame' => strlen($para['dgame']),
            'dgas' => strlen($dgas)];
        $charge = array_sum($sc_arr);
        $sc_radio = getInstance()->config->get('ServerCharge', 0.0001);
        $pre_len = yield $this->getPrecisionsLen($para['appid']);
        $charge = $this->calc($charge, $sc_radio, 'mul', $pre_len);//bcmul($charge,$sc_radio, 5);
        $orderId = $this->guid();
        $d2d = ['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['toaddr'],
            'dgame' => $para['dgame'],
//            'txid' => $para['txid'],
            'address' => $para['addr'],
            'dgas' => $dgas,
            'ratio' => $dgas_ratio,
            'Scharge' => $charge,
            'order_sn' => $orderId,
            'status' => 0,
            'create_time' => time(),
            'update_time' => time()];
        //create dgameToDgas Order
        $rel = yield $this->getObject(DgasModel::class)->InsertByData([$d2d]);
        if ($rel) {
            $this->resCode = 0;
            $this->resMsg = 'Successful operation';
            $this->data = ['order' => $orderId];
        } else {
            $this->resCode = 1;
            $this->resMsg = 'Operation failure';
        }

        $this->interOutputJson(200);
    }

    public function actionAddAccount_CallBack()
    {
        $para = json_decode($this->getContext()->getInput()->getRawContent(), true);
        $params = yield $this->checkParamsExists($para, ['txid', 'orderid']);
        if (!$params) {
            $this->resCode = 1;
            $this->resMsg = "Parameters of the abnormal";
            $this->encryptOutput(403);
            return;
        }
        $data = ['txid' => $para['txid']];
        $up = yield $this->getObject(DgasModel::class)
            ->UpDataByQuery_D2D($data, ['order_sn' => $para['orderid']]);
        if ($up) {
            $this->resStauts = "Successful operation";
        } else {
            $this->resCode = 1;
            $this->resStauts = "Operation failure";
        }
        $this->interOutputJson();
    }

    /**
     * dgas_log detail
     * @param $result
     * @return mixed
     */
    public function getListDtail($result, $appid)
    {
        $subchain = $this->getObject(SubchainModel::class);
        $info = $this->getObject(InfoModel::class);
        $pre_len = yield $this->getPrecisionsLen($appid);
        $arr = [];
        foreach ($result as $k => $v) {
            if ($v['type'] == 3 && $v['flag'] == 3) {
                $rel = yield $subchain->getDataByQuery("appid,status,create_time,subchain,fromaddr,toaddr", ['id' => $v['exchange_id']]);

                $sc_para = ['appid' => strlen($rel[0]['appid']),
                    'fromaddr' => strlen($rel[0]['fromaddr']),
                    'toaddr' => strlen($rel[0]['toaddr']),
                    'remark' => strlen(''),
                    'num' => strlen($rel[0]['subchain'])];
                $result[$k]['num'] = array_sum($sc_para);
                $radio = getInstance()->config->get('ServerCharge', 0.0001);
                $result[$k]['num'] = $this->calc($result[$k]['num'], $radio, 'mul', $pre_len);//$result[$k]['num']*$radio;

                $result[$k]['tran_type'] = 'DGAS';
                $rel[0]['status'] = 1;
            } elseif ($v['type'] == 0 && $v['flag'] == 0) {
                $reward = $this->getObject(RewardModel::class);
                $rel = yield $reward->getDataByQuery("appid,dgas,create_time", ['id' => $v['exchange_id']]);
                $result[$k]['tran_type'] = 'DGAS';
                $result[$k]['num'] = $rel[0]['dgas'];
                $rel[0]['status'] = 1;
            } else {
                $rel = yield $info->getDataByQuery('create_time,status,dgas,fromaddr,Scharge', $this->swichTB($v['type'], $v['flag']), ['id' => $v['exchange_id']]);
                $result[$k]['num'] = $result[$k]['flag'] == '2' ? $rel[0]['Scharge'] : $rel[0]['dgas'];
                if ($result[$k]['flag'] == 3 && $result[$k]['type'] == 1)
                    $result[$k]['num'] = $rel[0]['Scharge'];
                $result[$k]['tran_type'] = $result[$k]['type'] == '2' ? $rel[0]['fromaddr'] : 'DGAS';
            }
            $result[$k]['create_time'] = $rel[0]['create_time'];
            $result[$k]['status'] = $rel[0]['status'];
            unset($result[$k]['exchange_id']);
            if ($rel[0]['status'] == 1)
                $arr[] = $result[$k];
//                unset($result[$k]);
        }
        return $arr;
    }

    /**
     * Select the corresponding data table based on the recorded type and flag values
     * @param $type 0：reward，1：consume，2：recharge
     * @param $flag 0：normal，1：dgame2suchain，2：recharge charge，3：exchange charge
     * @return mixed
     */
    public function swichTB($type, $flag)
    {
        //todo:
    }

    /**
     * The dgas balance is obtained according to the subchain address and subchain contract identification address
     */
    public function actionGetGasBalance()
    {
        $get = $this->getContext()->getInput()->getAllPostGet();
        $params = $this->checkParamsExists($get, ['appid', 'address']);
        if (!$params) {
            $this->resCode = 1;
            $this->resMsg = "Parameters of the abnormal";
            $this->interOutputJson(403);
            return;
        }
        $result = yield $this->getObject(AccountModel::class)
            ->getParaByData('dgas', ['appid' => $get['appid'], 'address' => $get['address']]);
        $result = $result[0]['dgas'] ? $result[0]['dgas'] : 0;
        $this->data = ['dgas' => $result];
        $this->interOutputJson(200);
    }

    //Get the details of the dgas log
    public function actionGetDetail()
    {
        $id = $this->getContext()->getInput()->get('id');
        $rel = yield $this->masterDb->select("exchange_id,type")->from('gas_log')->where('id', $id)->go();
        if (!$rel['result']) {
            $this->data = $rel ? $rel['result'][0] : null;
            $this->interOutputJson(200);
        }
        $rel1 = yield $this->masterDb
            ->select("id,create_time,dgas,fromaddr")
            ->from('dgame2dgas')
            ->where('id', $rel['result'][0]['exchange_id'])
            ->go();
        $rel['result'][0]['create_time'] = $rel1['result'][0]['create_time'];
        $rel['result'][0]['dgas'] = $rel1['result'][0]['dgas'];
        $this->data = $rel ? $rel['result'][0] : null;
        $this->interOutputJson(200);
    }

    public function actiongetDtailByOrder()
    {
        $order = $this->getContext()->getInput()->get('order');
        $rel1 = yield $this->masterDb
            ->select("id,create_time,dgas,fromaddr")
            ->from('dgame2dgas')
            ->where('order_sn', $order)
            ->go();
        $rel['result'][0]['create_time'] = $rel1['result'][0]['create_time'];
        $rel['result'][0]['dgas'] = $rel1['result'][0]['dgas'];
        $this->data = $rel ? $rel['result'][0] : null;
        $this->interOutputJson(200);
    }


}