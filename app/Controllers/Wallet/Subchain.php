<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/25
 * Time: 12:08
 */

namespace App\Controllers\Wallet;

use App\Controllers\Common\BaseController;
use App\Tasks\Dgas as DgasTask;

class Subchain extends BaseController
{
    public function actionReq(){
        $poa_header = $this->getContext()->getInput()->getHeader('poa');

    }
    //通过先生成订单的方式先获取子链的兑换比例，然后在客户端显示<用于充值子链币>
    public function actionPrepaerOrder(){

    }

    //子链充值-dgas
    public function actionAddDgasAccount(){
        //生成subchain充值订单（dgas2subchain）
        /*====================  接受加密参数 =========================*/
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr,true);

        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'接收参1111数'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($para,TRUE).PHP_EOL];
        file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
        /*=================日志END===================*/

        var_dump($reqstr);
        /*====================  接受加密参数  =========================*/
        //$para=json_decode($this->getContext()->getInput()->getRawContent(),true);
        $para['subchain']=$para['dgas']*($this->getRedio($para['appid']));

        var_dump('dgas2sub传值',$para);
        //计算手续费
        $sc_para = ['appid'=>$para['appid'],
            'fromaddr'=>$para['fromaddr'],
            'toaddr'=>$para['toaddr'],
            'remark'=>'',
            'num'=>$para['subchain'],
            '2num'=>$para['dgas']];
        $para['Scharge']=$this->CalSCharge($sc_para);
        //判断（手续费+dgas是否大于用户余额）
        $balance=yield $this->masterDb->select('dgas')->from('account')
            ->where('appid', $para['appid'])
            ->andwhere('address',$para['fromaddr'])->go();
       var_dump('判断'.$para['fromaddr'].'余额',$balance);
       var_dump('要扣的手续费',$balance);
        if($balance && $balance['result'][0]['dgas']<( $para['Scharge']+$para['dgas'])){
            $this->resCode = 1;
            $this->resMsg='余额不足';
            var_dump('-------------------------'.$this->resMsg.'----------------------');
            var_dump("余额不足",( $para['Scharge']+$para['dgas']));

            /*=================日志BEGAIN===================*/
            $content=[PHP_EOL.'判断余额'.date("Y-m-d H:i:s",time()).PHP_EOL. ($balance['result'][0]['dgas']<( $para['Scharge']+$para['dgas'])) .PHP_EOL];
            file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
            /*=================日志END===================*/
            $this->encryptOutput($key2,$iv,200);
            return;
        }
        //事务开始--
        $trans = yield $this->masterDb->goBegin();
        $res= yield $this->dgas2subchain_new($para,$trans);
        if(!$res['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resMsg=$res['mes'];

            /*=================日志BEGAIN===================*/
            $content=[PHP_EOL.'操作失败'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res,TRUE).PHP_EOL];
            file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
            /*=================日志END===================*/

            $this->encryptOutput($key2,$iv,200);
            return;
        }
        yield $this->masterDb->goCommit($trans);
//        $order_sn  = yield $this->masterDb->select("order_sn")->from('dgas2subchain')->where('id',$res['data']['insert_id'])->go();
        //0=>'dgame2subchain',1=>'dgas2subchain',2=>'dgame2dgas'
        $subData=['dgas' => $para['dgas'],
            'amount'=>$para['subchain'],
            'faddress'=>$para['fromaddr'],
            'taddress'=>$para['toaddr'],
            'out_trade_no'=>$res['data']['order'],
            'create_time'=>time(),
            'update_time'=>time(),
            'type'=>1];
        var_dump($subData);
        $result=yield $this->SubRechargeLimit3($subData,$para['appid']);

        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'提交'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($result,TRUE).PHP_EOL];
        file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
        /*=================日志END===================*/

        $this->resMsg=$res['mes'];
        var_dump('-------------------------'.$this->resMsg.'----------------------');
        $this->data=['order'=>$res['data']['order']];
        $this->encryptOutput($key2,$iv,200);

    }



    //子链充值（dgame）
    public  function actionAddDgameAccounts(){

        /*====================  接受加密参数 =========================*/
        $para = ["appid"=>"V431rSWOMpq3xGGJYSQTGH5oxlMBiXjJRw",
            "fromaddr"=>"0x13dcab7acae2d03ab7b02958b089fd2131515bb5",
            "toaddr"=>"0xf064b91a396bff8dac4848eccb20813b955b13fd",
            "addr"=>"Vwy52IzENTSKebT94yIWG9_Wr8Iqyjh6Vw",
            "dgame"=>"0.001",
            "txid"=> "0xbb6525b9382f9652941455123be12b8230f0e1d5316b39f91dbb0cc89a085498",
            "type"=>"1"];
        $para['address'] = $para['addr'];
        unset($para['addr']);
        unset($para['type']);
        /*====================  接受加密参数  =========================*/

        //$param[type] 0 dgame2dgame 1 dgame2dgas 2 dgame2subchain
        var_dump('AddDgameAccount:',$para);
        /*if(!(isset($para['appid']) && isset($para['address']) && isset($para['toaddr'])&& isset($para['txid'])&& isset($para['dgame']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->interOutputJson(403);return;
        }*/

        $subRatio=$this->getRedio($para['appid']);                          //子链比例
        $dgas=getInstance()->config->get('dgas.ratio',100000);

        //生成subchain充值订单（dgame2subchain）
        $subArr['subchain']=($para['dgame']*$dgas)*$subRatio;

        //计算手续费
        $sc_arr = ['num'=>$para['dgame'],
            '2num'=>$subArr['subchain'],
            'remark'=>''];
        $sc_para=array_merge($para,$sc_arr);
        $para['Scharge']=$this->CalSCharge($sc_para);
        $subArr=$para;

        //事务开始--
        $trans = yield $this->masterDb->goBegin();
        $sub= yield $this->dgame2subchain($subArr,$trans);

        if(!$sub['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resMsg = $sub['mes'];
            $this->interOutputJson(200);
            return;
        }

        //dgame2dgas---------
        $d2dArr=$para;
        $d2dArr['dgas']=$para['dgame']*$dgas;
        $d2dArr['order_sn']=$sub['data']['order'];
        $d2dArr['toaddr']=$d2dArr['address'];
        $d2d= yield $this->dgame2dgas_old($d2dArr,$trans,1,$sub['data']['id']);

        if(!$d2d['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resStauts = $d2d['mes'];
            $this->interOutputJson(201);
            return;
        }

        //dgas2subchain
        $d2sArr=$para;
        $d2sArr['dgas']=$d2dArr['dgas'];
        $d2sArr['subchain']=$d2dArr['dgas']*$subRatio;
        unset($d2sArr['dgame']);
        $d2sArr['order_sn']=$sub['data']['order'];
        $d2s= yield $this->dgas2subchain($d2sArr,$trans,1,false);
        if(!$d2s['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resStauts = $d2s['mes'];
            $this->interOutputJson(200);
            return;
        }
        yield $this->masterDb->goCommit($trans);

        $subData=['dgas' => $d2sArr['dgas'],
            'amount'=>$d2sArr['subchain'],
            'faddress'=>$para['fromaddr'],
            'taddress'=>$para['address'],
            'out_trade_no'=>$sub['data']['order'],
            'create_time'=>time(),
            'update_time'=>time(),
            'type'=>0];

        var_dump('tijiao',$para['appid']);
        $result=yield $this->SubRechargeLimit3($subData,$para['appid']);
//        //获得订单编号
        $this->data=$sub['data'];
        $this->interOutputJson(200);

    }


    //子链充值（dgame）
    public  function actionAddDgameAccount(){

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

        //$param[type] 0 dgame2dgame 1 dgame2dgas 2 dgame2subchain
        var_dump('AddDgameAccount:',$para);
        if(!(isset($para['appid']) && isset($para['address']) && isset($para['toaddr'])&& isset($para['txid'])&& isset($para['dgame']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->encryptOutput($key2,$iv,403);return;
        }

        $subRatio=$this->getRedio($para['appid']);                          //子链比例
        $dgas=getInstance()->config->get('dgas.ratio',100000);

        //生成subchain充值订单（dgame2subchain）
        $subArr['subchain']=($para['dgame']*$dgas)*$subRatio;

        //计算手续费
        $sc_arr = ['num'=>$para['dgame'],
            '2num'=>$subArr['subchain'],
            'remark'=>''];
        $sc_para=array_merge($para,$sc_arr);
        $para['Scharge']=$this->CalSCharge($sc_para);
        $subArr=$para;

        //事务开始--
        $trans = yield $this->masterDb->goBegin();
        $sub= yield $this->dgame2subchain($subArr,$trans);

        if(!$sub['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resMsg = $sub['mes'];
            $this->encryptOutput($key2,$iv,200);
            return;
        }

        //dgame2dgas---------
        $d2dArr=$para;
        $d2dArr['dgas']=$para['dgame']*$dgas;
        $d2dArr['order_sn']=$sub['data']['order'];
//        $d2dArr['address']=$d2dArr['address'];
        $d2d= yield $this->dgame2dgas_old($d2dArr,$trans,1,$sub['data']['id']);
        var_dump("dagme2subchainnew----------------------------------------");
        if(!$d2d['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resStauts = $d2d['mes'];
            $this->encryptOutput($key2,$iv,200);
            return;
        }

        //dgas2subchain
        $d2sArr=$para;
        $d2sArr['dgas']=$d2dArr['dgas'];
        $d2sArr['subchain']=$d2dArr['dgas']*$subRatio;
        unset($d2sArr['dgame']);
        $d2sArr['order_sn']=$sub['data']['order'];
        $d2s= yield $this->dgas2subchain($d2sArr,$trans,1,false);
        if(!$d2s['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resStauts = $d2s['mes'];
//            $this->interOutputJson(200);
            $this->encryptOutput($key2,$iv,200);
            return;
        }
        yield $this->masterDb->goCommit($trans);

        $subData=['dgas' => $d2sArr['dgas'],
            'amount'=>$d2sArr['subchain'],
            'faddress'=>$para['fromaddr'],
            'taddress'=>$para['address'],
            'out_trade_no'=>$sub['data']['order'],
            'create_time'=>time(),
            'update_time'=>time(),
            'type'=>0];
        var_dump($subData);
        $result=yield $this->SubRechargeLimit3($subData,$para['appid']);

//        //获得订单编号
        $this->data=$sub['data'];
        $this->encryptOutput($key2,$iv,200);
//        $this->interOutputJson(200);

    }

    //子链交易列表
    public function actiongetSubchainList(){
        $get=$this->getContext()->getInput()->getAllPostGet();
        if(!(isset($get['appid']) && isset($get['address']) && isset($get['page'])&& isset($get['pageSize']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->interOutputJson(403);
        }

        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'接收参数'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($get,TRUE).PHP_EOL];
        file_put_contents("/tmp/subchainlist",$content,FILE_APPEND);
        /*=================日志END===================*/
        $size=$get['pageSize']>50 ? 50 : (int)$get['pageSize'];
        $size=$size<0 ? 1 : $size;
        $get['page']=$get['page']<0 ? 1 : $get['page'];
        $start=($get['page']-1)*$size;
        $result  = yield $this->masterDb->select("type,exchange_id,id")->from('subchain_log')->where('appid',$get['appid'])->andwhere('address',$get['address'])->andwhere('type' ,'2','!=')->orderby('create_time','desc')->limit($size,$start)->go();
        $tb=array(0=>'dgame2subchain',1=>'dgas2subchain');
        $type=[0=>'DGAME',1=>'DGAS'];
        foreach ($result['result'] as $k=>$v){
            $result['result'][$k]['type']=$type[$result['result'][$k]['type']];
            $rel=yield $this->masterDb->select("create_time,subchain")->from($tb[$v['type']])->where('id',$v['exchange_id'])->go();
            $result['result'][$k]['create_time']=$rel['result'][0]['create_time'];
            $result['result'][$k]['subchain']=$rel['result'][0]['subchain'];
            unset($result['result'][$k]['exchange_id']);
        }
        $this->data = $result['result'] ? $result['result'] : null;
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
        $rel1=yield $this->masterDb->select("id,create_time,fromaddr,txid,subchain")->from($tb[$rel['result'][0]['type']])->where('id',$rel['result'][0]['exchange_id'])->go();
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
//id	appid	txid	from	to	dgas	status	create_time	update_time
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
//        var_dump($reqstr);
        var_dump($para);
        //$para=json_decode($this->getContext()->getInput()->getRawContent(),true);
        if(!(isset($para['appid']) && isset($para['fromaddr']) && isset($para['toaddr'])&& isset($para['subchain']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->encryptOutput(403);
        }
        //$para1=json_decode($this->getContext()->getInput()->getAllPostGet(),true);
        //事务开始--
        $trans = yield $this->masterDb->goBegin();
        $res= yield $this->adds2s($para,$trans);
        if(!$res['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resMsg=$res['mes'];
            $this->encryptOutput(200);
        }
        yield $this->masterDb->goCommit($trans);

        //事务结束--
        //获得订单编号
        $order_sn  = yield $this->masterDb->select("txid")->from('subchain2subchain')->where('id',$res['data']['insert_id'])->go();
        $this->resCode=0;
        $this->data=['orderid'=>$order_sn['result'][0]['txid']];
        $this->encryptOutput($key2,$iv,200);
    }


    //修改子链2子链状态和txid
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
        $data=array('status'=>1,'txid'=>$para['txid']);
        var_dump($para,$this->getContext()->getInput()->getRawContent());
        $up= yield $this->masterDb->update('subchain2subchain')->set($data)->where('txid', $para['orderid'])->go();
        if($up['affected_rows']==0){
            $this->resCode = 1;
            $this->resStauts = "FAIL";
            $this->encryptOutput($key2,$iv,200);
        }
        $this->resCode=1;
        $this->encryptOutput($key2,$iv,200);
    }


}