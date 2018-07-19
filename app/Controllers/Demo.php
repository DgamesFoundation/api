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
