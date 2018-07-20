<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/25
 * Time: 12:08
 */

namespace App\Controllers\Wallet;

use App\Models\Wallet\SubchainModel;
use App\Models\Wallet\DgasModel;
use App\Models\Wallet\ApplicationModel;
use App\Controllers\Common\BaseController;
use App\Models\Wallet\InfoModel;

class Subchain extends BaseController
{
    public function actionReq(){
        $poa_header = $this->getContext()->getInput()->getHeader('poa');

    }
    //通过先生成订单的方式先获取子链的兑换比例，然后在客户端显示<用于充值子链币>
    public function actionPrepaerOrder(){

    }

    /**
     * 插入Dgas2subchain订单，并扣除手续费，记录相应日志
     * @return \Generator
     */
    public function actionAddDgasAccount(){
        /*====================  接受加密参数 =========================*/
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr,true);

        $this->log('dgas2subchain',$para,'接收参数');
        /*====================  接受加密参数  =========================*/
        $params = yield $this->checkParamsExists($para,['appid','fromaddr','toaddr','dgas']);
        if(!$params){
            $this->resMsg = '参数异常';
            $this->resCode = 1;
            $this->encryptOutput($key2,$iv,403);return;
        }
        $subchainMod = $this->getObject(SubchainModel::class);
        $subchain_ratio = yield $subchainMod->getRedio($para['appid']);
        $para['subchain'] = $para['dgas']*$subchain_ratio;
        //计算手续费
        $sc_arr = ['appid' => strlen($para['appid']),
            'fromaddr' => strlen($para['fromaddr']),
            'address' => strlen($para['toaddr']),
            'remark'=>'',
            'dgas' => strlen($para['dgas'])];
        $charge = array_sum($sc_arr);
        $sc_radio = getInstance()->config->get('ServerCharge',0.0001);
        $para['Scharge'] = bcmul($charge,$sc_radio, 5);
        $dgasMod = $this->getObject(DgasModel::class);
        //事务开始
        $trans = yield $this->masterDb->goBegin();
        //扣手续费
        $consume_rel= yield $dgasMod->consumeDgas_new($para,$trans);
        if(!$consume_rel['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg=$consume_rel['mes'];
            $this->encryptOutput($key2,$iv,200);return;
        }
        //插入订单
        $d2s=['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['toaddr'],
            'dgas' => $para['dgas'],
            'subchain'=> $para['subchain'],
            'ratio'=> $subchain_ratio,
            'Scharge'=> $para['Scharge']];
        $d2s_res = yield $subchainMod->AddDgas2subchain($d2s,$trans);
        $this->log('dgas2subchain',$d2s_res,'订单插入');
        if(!$d2s_res['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resMsg = '操作失败';
            $this->encryptOutput($key2,$iv,200);return;
        }
        $this->resMsg = '操作成功';
        $this->data = ['order'=>$d2s_res['data']['order']];
        //插入日志
        yield $dgasMod->AddLog_Dgas2s($para,$d2s_res['data']['id']);
        yield $this->masterDb->goCommit($trans);

        $subData = ['dgas' => $para['dgas'],
            'amount' => $para['subchain'],
            'faddress' => $para['fromaddr'],
            'taddress'=>$para['toaddr'],
            'out_trade_no'=>$d2s_res['data']['order'],
            'create_time'=>time(),
            'update_time'=>time(),
            'type'=>1];
        var_dump($subData);
        $result = yield $this->SubRechargeLimit3($subData,$para['appid']);
        $this->encryptOutput($key2,$iv,200);
    }

    /**子链充值（dgame）Dgame2subchain
     * @return \Generator|void
     */
    public  function actionAddDgameAccount(){
        /*====================  接受加密参数 =========================*/
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr,true);
        /*====================  接受加密参数  =========================*/
        $this->log('dgame2subchain',[$code,$sign],'接收参数');
        $params=$this->checkParamsExists($para,['appid','fromaddr','addr','toaddr','dgame','txid']);
        if(!$params){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->encryptOutput($key2,$iv,403);return;
        }
        /*=========================  参数异常   ===================================*/
        $subRatio = yield $this->getObject(SubchainModel::class)
            ->getRedio($para['appid']); //子链比例
        $dgas=getInstance()->config->get('dgas.ratio',100000);
        $para['subchain']=$para['subchain']=bcmul(bcmul($para['dgame'],$dgas,10),$subRatio,10);
        //计算手续费
        $sc_arr = ['appid' => strlen($para['appid']),
            'fromaddr' => strlen($para['fromaddr']),
            'address' => strlen($para['addr']),
            'remark'=>strlen(''),
            'dgame' => strlen($para['dgame'])];
        $charge=array_sum($sc_arr);
        $sc_radio=getInstance()->config->get('ServerCharge',0.0001);
        $para['Scharge']=bcmul($charge,$sc_radio, 5);
        $dgasMod=$this->getObject(DgasModel::class);
        //事务开始
        $trans = yield $this->masterDb->goBegin();
        //扣手续费
        $conArr=$para;
        $conArr['fromaddr']=$para['addr'];
        $consume_rel= yield $dgasMod->consumeDgas_new($conArr,$trans,true);
        $this->log('dgame2subchain',$consume_rel,'扣手续费');
        if(!$consume_rel['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg=$consume_rel['mes'];
//            $this->interOutputJson(403);return;
            var_dump("扣手续费lose");
            $this->encryptOutput($key2,$iv,200);return;
        }
        /*=========================  余额不足&扣费失败   ===================================*/
        //插入订单
        $result=yield $this->AddOrders_Dgame2Sub($para,$trans);
        $this->log('dgame2subchain',$result,'插入订单');
        if(!($result['d2s']['status'] && $result['d2d']['status'] && $result['sub']['status'])){
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg='操作失败';
            var_dump("操作失败");
            $this->encryptOutput($key2,$iv,200);return;
        }
        /*=========================  插入订单失败   ===================================*/
        //插入日志
        $ids=['sub'=>$result['sub']['data']['id'],
            'd2d'=>$result['d2d']['data']['id'],
            'd2s'=>$result['d2s']['data']['id']];
        yield $dgasMod->AddLog_Dgame2s($para,$ids);
        yield $this->masterDb->goCommit($trans);
//        //获得订单编号
        $this->resMsg='操作成功';
        $this->data=['order'=>$result['sub']['data']['order']];
        $this->encryptOutput($key2,$iv,200);

    }

    /**生成dgame转subchain需要生成的订单
     * @param $para
     * @param $trans
     * @return array
     */
    public function AddOrders_Dgame2Sub($para,$trans){
        $subchainMod = $this->getObject(SubchainModel::class);
        $subRatio = yield $subchainMod->getRedio($para['appid']); //子链比例
        //插入dgame2subchain
        $subArr=['appid'=>$para['appid'],
            'fromaddr'=>$para['fromaddr'],
            'address'=>$para['addr'],
            'toaddr'=>$para['toaddr'],
            'txid'=>$para['txid'],
            'dgame'=>$para['dgame'],
            'subchain'=>$para['subchain'],
            'Scharge'=>$para['Scharge'],
            'ratio'=>$subRatio];
        $this->log('dgame2subchain',$subArr,'$subArr');
        $sub=yield $subchainMod->AddDgame2subchain($subArr,$trans);
        //插入dgame2dgas
        $dgas=getInstance()->config->get('dgas.ratio',100000);
        $d2dArr=['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['addr'],
            'dgame' => $para['dgame'],
            'txid' => $para['txid'],
            'address' => $para['addr'],
            'dgas'=>bcmul($para['dgame'],$dgas,10),
            'ratio'=>$dgas,
            'Scharge'=>$para['Scharge'],
            'order_sn'=>$sub['data']['order']];
        $this->log('dgame2subchain',$d2dArr,'d2dArr');
        $dgasMod=$this->getObject(DgasModel::class);
        $d2d=yield $dgasMod->AddDgame2Dgas($d2dArr,$trans);
        //插入dgas2subchain
        $d2sArr=['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['addr'],
            'dgas' => $d2dArr['dgas'],
            'subchain'=>$para['subchain'],
            'ratio'=>$subRatio,
            'Scharge'=>$para['Scharge'],
            'order_sn'=>$sub['data']['order']];
        $this->log('dgame2subchain',$d2dArr,'$d2dArr');
        $d2s=yield $subchainMod->AddDgas2subchain($d2sArr,$trans);
        return ['sub'=>$sub,'d2d'=>$d2d,'d2s'=>$d2s];
    }

    /**
     * Subchain2subchain生成订单
     * @return \Generator|void
     */
    public function actionAddS2S(){
        //生成subchain充值订单（dgas2subchain）
        /*====================  接受加密参数 =========================*/
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr,true);
        /*====================  接受加密参数  =========================*/
//        $para=json_decode($this->getContext()->getInput()->getRawContent(),true);
        $params=yield $this->checkParamsExists($para,['appid','fromaddr','toaddr','subchain']);
        if(!$params){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->encryptOutput(403);return;
        }
        //计算手续费
        $sc_arr = ['appid' => strlen($para['appid']),
            'fromaddr' => strlen($para['fromaddr']),
            'tooaddr' => strlen($para['toaddr']),
            'remark'=>strlen(''),
            'subchain' => strlen($para['subchain'])];
        $charge=array_sum($sc_arr);
        $sc_radio=getInstance()->config->get('ServerCharge',0.0001);
        $para['Scharge']=bcmul($charge,$sc_radio, 5);

        //事务开始--
        $trans = yield $this->masterDb->goBegin();
        //扣手续费
        $consume_rel=yield $this->getObject(DgasModel::class)
            ->consumeDgas_new($para,$trans,true,true);
        if(!$consume_rel['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg=$consume_rel['mes'];
            $this->encryptOutput(200);return;
        }
        //插入订单
        $txid=$this->guid();
        $s2s = ['appid'=>$para['appid'],
            'fromaddr'=>$para['fromaddr'],
            'toaddr'=>$para['toaddr'],
            'subchain'=>$para['subchain'],
            'txid'=>$txid,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time()];
        $res= yield  $this->getObject(SubchainModel::class)->Inserts2s($s2s,$trans);
        if(!$res){
            $this->resCode=1;
            $this->resMsg='操作失败';
            yield $this->masterDb->goRollback($trans);
            $this->encryptOutput(200);return;
        }
        //添加gas_log
        yield $this->AddGas_s2s_log($para,$res['id']);
        //事务结束--
        yield $this->masterDb->goCommit($trans);
        $this->resCode=0;
        $this->data=['orderid'=>$txid];
        $this->resMsg='操作成功';
        $this->encryptOutput($key2,$iv,200);
    }

    /**
     * 生成S2S   gas_log日志
     * @param $para
     * @param $id
     * @return \Generator
     */
    public function AddGas_s2s_log($para,$id){
        $swich_time=getInstance()->config->get('app2account_time','2018-07-10 00:00:00');
        if(time()<strtotime($swich_time)){
            $address=$this->getObject(ApplicationModel::class)
                ->getDataByQuery('address',['appid'=>$para['appid']]);
            $address=$address[0]['address'];
        }else
            $address=$para['fromaddr'];
        $gas_log=['appid'=>$para['appid'],
            'address'=>$address,
            'exchange_id'=>$id,
            'type'=>self::TRANSFER,
            'flag'=>self::DGAS_CONSUME_SCHARGE,
            'create_time'=>time()];
        yield $this->getObject(DgasModel::class)
            ->Insert_Gaslog([$gas_log]);
    }
    /**
     * 修改子链2子链状态和txid
     */
    public function actionUpSubStatus(){
        /*====================  接受加密参数 =========================*/
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr,true);
        /*====================  接受加密参数  =========================*/
        //$para=json_decode($this->getContext()->getInput()->getRawContent(),true);
        $params=yield $this->checkParamsExists($para,['txid','orderid']);
        if(!$params){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->encryptOutput(403);return;
        }
        $data=['status'=>1,'txid'=>$para['txid']];
        $up=yield $this->getObject(SubchainModel::class)
            ->UpDataByQuery_S2S($data,['txid'=>$para['orderid']]);
        if($up){
            $this->resStauts = "操作成功";
        }else
        {
            $this->resCode = 1;
            $this->resStauts = "操作失败";
        }
        $this->encryptOutput($key2,$iv,200);
    }

    /**
     * 子链交易列表
     */
    public function actiongetSubchainList(){
        $get=$this->getContext()->getInput()->getAllPostGet();
        $params=yield $this->checkParamsExists($get,['appid','address','page','pageSize']);
        $this->log('subchainlist',$get,'参数');
        if(!$params){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->encryptOutput(403);return;
        }
        $size=$get['pageSize']>50 ? 50 : (int)$get['pageSize'];
        $size=$size<0 ? 1 : $size;
        $get['page']=$get['page']<0 ? 1 : $get['page'];
        $start=($get['page']-1)*$size;
        $result=yield $this->getObject(SubchainModel::class)
            ->getDataByQuery_subLog("type,exchange_id,id",['appid'=>$get['appid'],
                'address'=>$get['address'],
                'type'=>['2','!=']],$start,$size);
        $tb=[0=>'dgame2subchain',1=>'dgas2subchain'];
        $type=[0=>'DGAME',1=>'DGAS'];
        $arr=[];
        foreach ($result as $k=>$v){
            $result[$k]['type']=$type[$result[$k]['type']];
            $rel=yield $this->getObject(InfoModel::class)
                ->getDataByQuery('create_time,subchain,status',$tb[$v['type']],['id'=>$v['exchange_id']]);
            $result[$k]['create_time']=$rel[0]['create_time'];
            $result[$k]['subchain']=$rel[0]['subchain'];
            unset($result[$k]['exchange_id']);
            if($rel[0]['status']==1){
                $arr[]=$result[$k];
            }
        }
        $this->data = $result ? $result : null;
        $this->interOutputJson(200);
    }


    //子链详情
    public function actionGetDetail(){
        $id=$this->getContext()->getInput()->get('id');
        $rel=yield $this->masterDb->select("exchange_id,type")->from('subchain_log')->where('id',$id)->go();
        if(!$rel['result']){
            $this->data = $rel ? $rel['result'][0] : null;
            $this->interOutputJson(200) ;
        }
        $tb=array(0=>'dgame2subchain',1=>'dgas2subchain');
        $rel1=yield $this->masterDb
            ->select("id,create_time,fromaddr,txid,subchain")
            ->from($tb[$rel['result'][0]['type']])
            ->where('id',$rel['result'][0]['exchange_id'])->go();
        $rel['result'][0]['create_time']=$rel1['result'][0]['create_time'];
        $rel['result'][0]['subchain']=$rel1['result'][0]['subchain'];
        $this->data = $rel ? $rel['result'][0] : null;
        $this->interOutputJson(200) ;
    }

    public function actiongetDtailByOrder(){
        $order=$this->getContext()->getInput()->get('order');
        $type=$this->getContext()->getInput()->get('type');

        if($type=='dgas')
            $rel1=yield $this->masterDb->select("id,create_time,subchain,fromaddr")->from('dgas2subchain')->where('order_sn',$order)->go();
        elseif ($type=='dgame')
            $rel1=yield $this->masterDb->select("id,create_time,subchain,fromaddr")->from('dgame2subchain')->where('order_sn',$order)->go();
        $rel['result'][0]['create_time']=$rel1['result'][0]['create_time'];
        $rel['result'][0]['num']=$rel1['result'][0]['subchain'];
        $this->data = $rel ? $rel['result'][0] : null;
        $this->interOutputJson(200) ;
    }





}