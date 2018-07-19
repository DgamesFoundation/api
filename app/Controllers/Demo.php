<?php
/**
 * 示例控制器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace App\Controllers;

use App\Controllers\Wallet\Subchain;
use App\Models\Wallet\RewardModel;
use App\Models\Wallet\SubchainModel;
use PG\I18N\I18N;
use App\Models\Demo as DemoModel;
use App\Models\Wallet\ApplicationModel;
use App\Models\Wallet\AccountModel;
use App\Models\Wallet\FeeLogModel;
use App\Models\Wallet\DgasModel;
use App\Models\Wallet\InfoModel;
use App\Controllers\Common\BaseController;
class Demo extends BaseController
{


    public function actionAddDgasAccount(){
        /*====================  接受加密参数 =========================*/
//        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
//        $sign = $this->getContext()->getInput()->post('sign'); // key1
//
//        $key2 = yield $this->checkKeycode($sign);
//        $iv   = strrev($key2);
//        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
//        $para = json_decode($reqstr,true);
//
//        $this->log('dgas2subchain',$para,'接收参数');
        /*====================  接受加密参数  =========================*/
        $para=json_decode($this->getContext()->getInput()->getRawContent(),true);
        $params=yield $this->checkParamsExists($para,['appid','fromaddr','toaddr','dgas']);
        if(!$params){
            $this->resMsg='参数异常';
            $this->resCode=1;
            $this->interOutputJson(403);return;
//            $this->encryptOutput($key2,$iv,403);return;
        }
        $subchainMod=$this->getObject(SubchainModel::class);
        $subchain_ratio=yield $subchainMod->getRedio($para['appid']);
        $para['subchain']=$para['dgas']*$subchain_ratio;
        //计算手续费
        $sc_arr = ['appid' => strlen($para['appid']),
            'fromaddr' => strlen($para['fromaddr']),
            'address' => strlen($para['toaddr']),
            'remark'=>'',
            'dgas' => strlen($para['dgas']),
            'subchain'=>strlen($para['subchain'])];
        $charge=array_sum($sc_arr);
        $sc_radio=getInstance()->config->get('ServerCharge',0.0001);
        $para['Scharge']=bcmul($charge,$sc_radio, 5);
        $dgasMod=$this->getObject(DgasModel::class);
        //事务开始
        $trans = yield $this->masterDb->goBegin();
        //扣手续费
        $consume_rel= yield $dgasMod->consumeDgas_new($para,$trans);
        if(!$consume_rel['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg=$consume_rel['mes'];
            $this->interOutputJson(403);return;
//            $this->encryptOutput($key2,$iv,200);return;
        }
        //插入订单
        $d2s=['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['toaddr'],
            'dgas' => $para['dgas'],
            'subchain'=>$para['subchain'],
            'ratio'=>$subchain_ratio,
            'Scharge'=>$para['Scharge']];
        $d2s_res=yield $subchainMod->AddDgas2subchain($d2s,$trans);
        $this->log('dgas2subchain',$d2s_res,'订单插入');
        if(!$d2s_res['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg='操作失败';
            $this->interOutputJson(403);return;
//            $this->encryptOutput($key2,$iv,200);return;
        }
        $this->resMsg='操作成功';
        $this->data=['order'=>$d2s_res['data']['order']];
        //插入日志
        yield $dgasMod->AddLog_Dgas2s($para,$d2s_res['data']['id']);
        yield $this->masterDb->goCommit($trans);

        $subData=['dgas' => $para['dgas'],
            'amount'=>$para['subchain'],
            'faddress'=>$para['fromaddr'],
            'taddress'=>$para['toaddr'],
            'out_trade_no'=>$d2s_res['data']['order'],
            'create_time'=>time(),
            'update_time'=>time(),
            'type'=>1];
        var_dump($subData);
        $result=yield $this->SubRechargeLimit3($subData,$para['appid']);
        var_dump($result);
        $this->interOutputJson(403);return;
//        $this->encryptOutput($key2,$iv,200);
    }

    //子链充值（dgame）
    public  function actionAddDgameAccounts(){

        /*====================  接受加密参数 =========================*/
        $para = ["appid"=>"0x1111111111",
            "fromaddr"=>"0x13dcab7acae2d03ab7b02958b089fd2131515bb5",
            "toaddr"=>"0xf064b91a396bff8dac4848eccb20813b955b13fd",
            "addr"=>"0x2222222222",
            "dgame"=>"12",
            "txid"=> "0xbb6525b9382f9652941455123be12b8230f0e1d5316b39f91dbb0cc89a085498",
            "type"=>"1"];
        $params=$this->checkParamsExists($para,['appid','fromaddr','addr','toaddr','dgame','txid']);
        if(!$params){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->interOutputJson(403);return;
        }
        $subRatio = yield $this->getObject(SubchainModel::class)
            ->getRedio($para['appid']); //子链比例
        $dgas=getInstance()->config->get('dgas.ratio',100000);
        $para['subchain']=($para['dgame']*$dgas)*$subRatio;
        //计算手续费
        $sc_arr = ['appid' => strlen($para['appid']),
            'fromaddr' => strlen($para['fromaddr']),
            'address' => strlen($para['addr']),
            'remark'=>strlen(''),
            'dgame' => strlen($para['dgame']),
            'subchain'=>strlen($para['subchain'])];
        $charge=array_sum($sc_arr);
        $sc_radio=getInstance()->config->get('ServerCharge',0.0001);
        $para['Scharge']=$charge=bcmul($charge,$sc_radio, 5);
        $dgasMod=$this->getObject(DgasModel::class);
        //事务开始
        $trans = yield $this->masterDb->goBegin();
        //扣手续费
        $conArr=$para;
        $conArr['fromaddr']=$para['addr'];
        $consume_rel= yield $dgasMod->consumeDgas_new($conArr,$trans,true);
        if(!$consume_rel['status']){
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg=$consume_rel['mes'];
            $this->interOutputJson(403);return;
            //$this->encryptOutput($key2,$iv,200);return;
        }
        //插入订单
        $result=yield $this->AddOrders_Dgame2Sub($para,$trans);

        if($result['d2s']['status'] && $result['d2d']['status'] && $result['sub']['status']){
            $this->resMsg='操作成功';
            $this->data=['order'=>$result['sub']['data']['order']];
            //插入日志
            $ids=['sub'=>$result['sub']['data']['id'],
                'd2d'=>$result['d2d']['data']['id'],
                'd2s'=>$result['d2s']['data']['id']];
            yield $dgasMod->AddLog_Dgame2s($para,$ids);
            yield $this->masterDb->goCommit($trans);
        }else{
            yield $this->masterDb->goRollback($trans);
            $this->resCode=1;
            $this->resMsg='操作失败';
        }
        $dgas=$para['dgame']*$dgas;
        $subData=['dgas' => $dgas,
            'amount'=> $para['subchain'],
            'faddress'=>$para['fromaddr'],
            'taddress'=>$para['address'],
            'out_trade_no'=>$result['sub']['data']['order'],
            'create_time'=>time(),
            'update_time'=>time(),
            'type'=>0];
        $result=yield $this->SubRechargeLimit3($subData,$para['appid']);
//        //获得订单编号
        $this->data=$result['sub']['data'];
        $this->interOutputJson(200);

    }

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
        $sub=yield $subchainMod->AddDgame2subchain($subArr,$trans);
        //插入dgame2dgas
        $dgas=getInstance()->config->get('dgas.ratio',100000);
        $d2dArr=['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['addr'],
            'dgame' => $para['dgame'],
            'txid' => $para['txid'],
            'address' => $para['addr'],
            'dgas'=>$para['dgame']*$dgas,
            'ratio'=>$dgas,
            'Scharge'=>$para['Scharge'],
            'order_sn'=>$sub['data']['order']];
        $dgasMod=$this->getObject(DgasModel::class);
        $d2d=yield $dgasMod->AddDgame2Dgas($d2dArr,$trans);
        //插入dgas2subchain
        $d2sArr=['appid' => $para['appid'],
            'fromaddr' => $para['fromaddr'],
            'toaddr' => $para['addr'],
            'dgas' => $d2dArr['dgas'],
            'subchain'=>$d2dArr['dgas']*$subRatio,
            'ratio'=>$subRatio,
            'Scharge'=>$para['Scharge'],
            'order_sn'=>$sub['data']['order']];
        $d2s=yield $subchainMod->AddDgas2subchain($d2sArr,$trans);
        return ['sub'=>$sub,'d2d'=>$d2d,'d2s'=>$d2s];
    }


        /*====================================================================================================================================================================*/
    // 测试接口
    public function actionAuthcode()
    {
        $this->data = yield $this->genKeycode();
        $this->interOutputJson(200);

      /*$header = $this->getContext()->getInput()->getHeader('poa');

      $code = $this->getContext()->getInput()->get('code');
var_dump("code1:",$header,"code",$code);
//      $code = $this->getContext()->getInput()->post('code');
//var_dump("code2:",$code);

      $res = ['code'=>0,
              'msg'=>'',
              'status'=>'OK',
              'data'=>null];
       $rawBody = $this->getContext()->getInput()->getRawContent();
       var_dump($rawBody);
       $rawBodyObj = json_decode($rawBody);

       $key2 = 'o2EqBFpHxljAInpo';   
       $rsaPrivateKey = file_get_contents("/home/king/ssl/rsa/rsa.pem");
       openssl_private_encrypt($key2,$encrypted,$rsaPrivateKey);
       $key2Encode = base64_encode($encrypted);
       
       var_dump("urldecode",urldecode($code));
       openssl_private_decrypt(base64_decode($code),$key1,$rsaPrivateKey);//私钥解密 
       $res['data'] = ['key1'=>$key1,'key2'=>$key2Encode];
       
       $this->outputJson($res);*/
       
       ////echo $private_key;  
       //$pi_key =  openssl_pkey_get_private($private_key);//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id  
       //$pu_key = openssl_pkey_get_public($public_key);//这个函数可用来判断公钥是否是可用的  
       //print_r($pi_key);echo "\n";
       //print_r($pu_key);echo "\n";
       //
       //
       //$data = "aassssasssddd";//原始数据  
       //$encrypted = "";
       //$decrypted = "";
       //
       //echo "source data:",$data,"\n\n\n\n\n\n\n\n";
       //
       //echo "private key encrypt:\n";
       //
       //openssl_private_encrypt($data,$encrypted,$pi_key);//私钥加密  
       //$encrypted = base64_encode($encrypted);//加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的  
       //echo $encrypted,"\n";
       //
       //echo "public key decrypt:\n";
       //
       //openssl_public_decrypt(base64_decode($encrypted),$decrypted,$pu_key);//私钥加密的内容通过公钥可用解密出来  
       //echo $decrypted,"\n";
       //
       //echo "---------------------------------------\n\n\n\n\n\n\n\n\n\n";
       //echo "public key encrypt:\n";
       //
       //openssl_public_encrypt($data,$encrypted,$pu_key);//公钥加密  
       //$encrypted = base64_encode($encrypted);
       //echo $encrypted,"\n";
       //
       //echo "private key decrypt:\n";
       //openssl_private_decrypt(base64_decode($encrypted),$decrypted,$pi_key);//私钥解密  
       //echo $decrypted,"\n";
 
    }

    public function actionGetok()
    {
        $code = $this->getContext()->getInput()->post('code');
        $sign = $this->getContext()->getInput()->post('sign'); // key1
        $key2 = 'o2EqBFpHxljAInpo';
        $iv = strrev($key2);
        
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $reqobj = json_decode($reqstr);
        
        $res = ['code'=>0,
              'msg'=>'',
              'status'=>'OK',
              'data'=>null];
       
        $jsonstr = json_encode($res);
        $resstr = base64_encode(openssl_encrypt($jsonstr, 'aes-128-cbc', $key2, true, $iv));
        var_dump("getok",$resstr);
        $this->getContext()->getOutput()->output($resstr);
    }

}
