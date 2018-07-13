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

class Dgas extends BaseController
{
    public function actionReq(){
        $poa_header = $this->getContext()->getInput()->getHeader('poa');

    }
//根据子链地址和子链合约标识地址获得dgas余额
    public function actionGetGasBalance(){
        $get=$this->getContext()->getInput()->getAllPostGet();
        if(!(isset($get['appid']) || isset($get['address']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->interOutputJson(403);
        }
        $result  = yield $this->masterDb->select("dgas")->from('account')->where('appid',$get['appid'])->where('address',$get['address'])->go();
        $result['result']=$result['result'][0]['dgas'] ? $result['result'][0]['dgas'] : 0;
        $this->data =['dgas'=>$result['result']] ;
        $this->interOutputJson(200);
    }
    /**
     *  显示dgas交易记录
     * 参数：appid、address、page、pageSize
     */
    public function actionRechargeList(){

        $get=$this->getContext()->getInput()->getAllPostGet();
        if(!(isset($get['appid']) && isset($get['address']) && isset($get['page'])&& isset($get['pageSize']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->interOutputJson(403);
        }

        //日志
        $this->log('gas_list',$get,'接收参数');
        //设置分页
        $size=$get['pageSize']>50 ? 50 : (int)$get['pageSize'];
        $size=$size<0 ? 1 : $size;
        $get['page']=$get['page']<0 ? 1 : $get['page'];
        $start=($get['page']-1)*$size;

        //获取gas列表
        $gas_log=$this->getObject(DgasModel::class);
        $query=['appid'=>$get['appid'],
            'address'=>$get['address']];
        $result=yield $gas_log->getDataByQuery("type,exchange_id,flag,id",$query,$start,$size);
        $result=yield $this->getListDtail($result);
        $this->log('gas_list',$result,'接收参数');
        $this->data = $result ? $result : null;
        $this->interOutputJson(200);
    }

    /**
     * 根据gas——list数组列出列表详情
     * @param $result
     * @return mixed
     */
    public function getListDtail($result){
        //获得记录详情
        $subchain=$this->getObject(SubchainModel::class);
        $info=$this->getObject(InfoModel::class);
        foreach ($result as $k=>$v) {
            if ($v['type'] == 3 && $v['flag'] == 3) {
                $rel=yield $subchain->getDataByQuery("appid,status,create_time,subchain,fromaddr,toaddr",['id'=> $v['exchange_id']]);

                //计算手续费
                $sc_para = ['appid'=>strlen($rel[0]['appid']),
                    'fromaddr'=>strlen($rel[0]['fromaddr']),
                    'toaddr'=>strlen($rel[0]['toaddr']),
                    'remark'=>0,
                    'num'=>strlen($rel[0]['subchain'])];
                $result[$k]['num'] =array_sum($sc_para);
                $result[$k]['num'] =$result[$k]['num']*getInstance()->config->get('ServerCharge',0.0001);

                $result[$k]['tran_type'] = 'DGAS';
                $rel[0]['status']=1;
            }elseif($v['type'] == 0 && $v['flag'] == 0){
                $reward=$this->getObject(RewardModel::class);
                $rel=yield $reward->getDataByQuery("appid,dgas,create_time",['id'=>$v['exchange_id']]);
                $result[$k]['tran_type'] = 'DGAS';
                $result[$k]['num'] = $rel[0]['dgas'];
                $rel[0]['status']=1;
            }else{
                $rel=yield $info->getDataByQuery('create_time,status,dgas,fromaddr,Scharge',$this->swichTB($v['type'],$v['flag']),['id'=>$v['exchange_id']]);
                $result[$k]['num']=$result[$k]['flag']=='2' ? $rel[0]['Scharge'] : $rel[0]['dgas'];
                if($result[$k]['flag']==3 && $result[$k]['type']==1)
                    $result[$k]['num']=$rel[0]['Scharge'];
                $result[$k]['tran_type']=$result[$k]['type']=='2' ? $rel[0]['fromaddr'] : 'DGAS';
            }
            $result[$k]['create_time']=$rel[0]['create_time'];
            $result[$k]['status']=$rel[0]['status'];
            unset($result[$k]['exchange_id']);
            if($rel[0]['status']!=1)
                unset($result[$k]);
        }
        return $result;
    }

    /**
     * 根据记录的type和flag值选择相对应数据表
     * @param $type 0：奖励，1：消耗，2：充值
     * @param $flag 0：正常，1：dgame2suchain，2：充值手续，3：兑换手续
     * @return mixed
     */
    public function swichTB($type,$flag){
        $tb=array(1=>'dgas2subchain',2=>'dgame2dgas');
        if($flag==2)
            return $tb[2];
        if($flag==3)
            return $tb[1];
        return $tb[$type];
    }

    //dgas充值 生成充值订单
    public function actionAddAccount(){
        /*====================  接受加密参数 =========================*/
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr,true);
        /*====================  接受加密参数  =========================*/
//        $para=json_decode($this->getContext()->getInput()->getRawContent(),true);
        /*=================日志===================*/
        $this->log("/tmp/dgame2dgas",$para,'接收参数');
        if(!(isset($para['appid']) && isset($para['fromaddr']) && isset($para['addr']) && isset($para['txid'])&& isset($para['dgame']) )){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            /*=================日志BEGAIN===================*/
            $this->log("dgame2dgas",[],'参数异常');
            $this->encryptOutput($key2,$iv,403);return;
        }
        $dgame_ratio=getInstance()->config->get('dgame.ratio',1);
        $dgas=$para['dgame']*$dgame_ratio;

        //计算手续费
        $sc_arr = ['appid' => strlen($para['appid']),
            'fromaddr' => strlen($para['fromaddr']),
            'address' => strlen($para['addr']),
            'remark'=>0,
            'dgame' => strlen($para['dgame']),
            'dgas'=>strlen($dgas)];
        $charge=array_sum($sc_arr);
        $charge=$charge*getInstance()->config->get('ServerCharge',0.0001);
        $orderId=$this->guid();
        $d2d=['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['toaddr'],
            'dgame' => $para['dgame'],
            'txid' => $para['txid'],
            'address' => $para['addr'],
            'dgas'=>$dgas,
            'ratio'=>$dgame_ratio,
            'Scharge'=>$charge,
            'order_sn'=>$orderId,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time()];
        //生成dgas充值订单（dgame2dgas）
        $rel=yield $this->getObject(DgasModel::class)->InsertByData([$d2d]);
        if($rel){
            $this->resCode = 0;
            $this->resMsg = '操作成功';
            $this->data=['order'=>$orderId];
        }else{
            $this->resCode = 1;
            $this->resMsg = '操作失败';
        }
        $this->log('dgame2dgas',$rel,'充值状态');

        $this->encryptOutput($key2,$iv,200);
    }

    //dgas充值 生成充值订单
    public function actionAddAccounts(){
        /*====================  接受加密参数 =========================*/
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr,true);
        $para['address'] = $para['addr'];
        unset($para['addr']);
        unset($para['type']);
        /*====================  接受加密参数  =========================*/
//        $para=json_decode($this->getContext()->getInput()->getRawContent(),true);

        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'接收参数'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($para,TRUE).PHP_EOL];
        file_put_contents("/tmp/dgame2dgas",$content,FILE_APPEND);
        /*=================日志END===================*/

        if(!(isset($para['appid']) && isset($para['address']) && isset($para['toaddr'])&& isset($para['txid'])&& isset($para['dgame']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";

            /*=================日志BEGAIN===================*/
            $content=[PHP_EOL.'参数异常'.date("Y-m-d H:i:s",time()).PHP_EOL];
            file_put_contents("/tmp/dgame2dgas",$content,FILE_APPEND);
            /*=================日志END===================*/

            $this->encryptOutput($key2,$iv,403);return;
        }
        $para['dgas']=$para['dgame']*(getInstance()->config->get('dgame.ratio',1));
        //计算手续费
        $sc_arr = ['remark'=>'',
            'num'=>$para['dgame'],
            '2num'=>$para['dgas']];
        $sc_para=array_merge($para,$sc_arr);
        $para['Scharge']=$this->CalSCharge($sc_para);
        //事务开始--
        $trans = yield $this->masterDb->goBegin();
        //生成dgas充值订单（dgame2dgas）
        $res=yield $this->dgame2dgas($para,$trans);
        if(!$res['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resMsg = $res['mes'];

            /*=================日志BEGAIN===================*/
            $content=[PHP_EOL.'充值失败'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res,TRUE).PHP_EOL];
            file_put_contents("/tmp/dgame2dgas",$content,FILE_APPEND);
            /*=================日志END===================*/

            $this->encryptOutput($key2,$iv,200);
        }else{
            yield $this->masterDb->goCommit($trans);
            //事务结束--
            $this->data=$res['data'];

            /*=================日志BEGAIN===================*/
            $content=[PHP_EOL.'充值成功'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res,TRUE).PHP_EOL];
            file_put_contents("/tmp/dgame2dgas",$content,FILE_APPEND);
            /*=================日志END===================*/

            $this->encryptOutput($key2,$iv,200);
        }
    }

    //获取dgas记录详情
    public function actionGetDetail(){
        $id=$this->getContext()->getInput()->get('id');
        $rel=yield $this->masterDb->select("exchange_id,type")->from('gas_log')->where('id',$id)->go();
        if(!$rel['result']){
            $this->data = $rel ? $rel['result'][0] : null;
            $this->interOutputJson(200) ;
        }
        $rel1=yield $this->masterDb->select("id,create_time,dgas,fromaddr")->from('dgame2dgas')->where('id',$rel['result'][0]['exchange_id'])->go();
        $rel['result'][0]['create_time']=$rel1['result'][0]['create_time'];
        $rel['result'][0]['dgas']=$rel1['result'][0]['dgas'];
        $this->data = $rel ? $rel['result'][0] : null;
        $this->interOutputJson(200) ;
    }

    public function actiongetDtailByOrder(){
        $order=$this->getContext()->getInput()->get('order');
        $rel1=yield $this->masterDb->select("id,create_time,dgas,fromaddr")->from('dgame2dgas')->where('order_sn',$order)->go();
        $rel['result'][0]['create_time']=$rel1['result'][0]['create_time'];
        $rel['result'][0]['dgas']=$rel1['result'][0]['dgas'];
        $this->data = $rel ? $rel['result'][0] : null;
        $this->interOutputJson(200) ;
    }







}