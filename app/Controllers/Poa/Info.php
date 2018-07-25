<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/25
 * Time: 12:08
 */

namespace App\Controllers\Poa;

use App\Controllers\Common\BaseController;
use App\Models\Poa\Poa;
use \PG\MSF\Client\Http\Client;

class Info extends BaseController
{
    public function actionReq(){
        $poa_header = $this->getContext()->getInput()->getHeader('poa');

    }

    public function actionVerify(){
        $code = $this->getContext()->getInput()->post('code');
        $sign = $this->getContext()->getInput()->post('sign'); //key1

        $key2 = yield $this->checkKeycode($sign);
        $iv = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $reqobj = json_decode($reqstr,true);

        $client  = $this->getObject(Client::class);
        $subchainHost = getInstance()->config->get("url.subchain");
        $result = yield $client->goSinglePost($subchainHost.'/account/verifyTx', ['msg' => $reqobj['data']]);

        $verifyOk = false;
        $code = 1;
        $msg = "失败";
        $stauts = "faild";
        $resultObj = json_decode($result['body'],true);
        if($resultObj['data'] == 1){
            $verifyOk = true;
            $code = 0;
            $msg = "成功";
            $stauts = "ok";
        }
        $res = ['code'=>$code,
            'msg'=>$msg,
            'status'=>$stauts,
            'data'=>$verifyOk];

        $jsonstr = json_encode($res);
        $resstr = base64_encode(openssl_encrypt($jsonstr, 'aes-128-cbc', $key2, true, $iv));
        $this->getContext()->getOutput()->output($resstr);
    }
    /**
     *
     * 登录时上报的POA设备信息
     *
     */
    public function actionDeviceInfo(){
        $code = $this->getContext()->getInput()->post('code');
        $sign = $this->getContext()->getInput()->post('sign'); //key1
        $key2 = yield $this->checkKeycode($sign);
        $iv = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $reqobj = json_decode($reqstr,true);
        $userid = $reqobj['user_key'];
        $appid  = $reqobj['appid'];
        $deviceInfo = $reqobj['device_info'];

        $uinfo = yield $this->masterDb->select("*")
            ->from("account")
            ->where("appid",$appid)
            ->where("address",$userid)
            ->go();

        if (!$uinfo['result']){
            yield $this->masterDb->insert("account")
                ->set(['appid'=>$appid,
                    'address'=>$userid,
                    'deviceinfo'=>$deviceInfo,
                    'create_time'=>time(),
                    'update_time'=>time()])->go();
        }else{
            yield $this->masterDb->update("account")
                ->set(['deviceinfo'=>$deviceInfo,
                    'create_time'=>time(),
                    'update_time'=>time()])->go();
        }
        $res = ['code'=>0,
            'msg'=>'成功',
            'status'=>'OK',
            'data'=>$reqobj];

        $jsonstr = json_encode($res);
        $resstr = base64_encode(openssl_encrypt($jsonstr, 'aes-128-cbc', $key2, true, $iv));
        $this->getContext()->getOutput()->output($resstr);
    }

    public function actionUserInfo(){
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv   = strrev($key2);

        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $reqobj = json_decode($reqstr,true);

        $appid  = $reqobj['appid'];
        $userid = $reqobj['user_key'];
        $userData = trim($reqobj['user_data']);
        $poaObj =  $this->getObject(Poa::class);
        $uinfo = yield $this->masterDb->select("is_disable")->from("account")->where("address",$userid)->go();
        if($uinfo['result'][0]['is_disable']){
            $res = ['code'=>1,
                'msg'=>'失败',
                'status'=>'fail',
                'data'=>""];
        }else{
            yield $poaObj->poaReportWithHour($appid,$userid,$userData); // blacklist
            $res = ['code'=>0,
                'msg'=>'成功',
                'status'=>'OK',
                'data'=>$reqobj];
        }

        $jsonstr = json_encode($res);
        $resstr = base64_encode(openssl_encrypt($jsonstr, 'aes-128-cbc', $key2, true, $iv));
        $this->data = $resstr;
        $this->interOutput(200);
    }
}