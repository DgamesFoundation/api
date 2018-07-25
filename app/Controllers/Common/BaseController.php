<?php
/**
 * Web Controller控制器基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace App\Controllers\Common;

use \PG\MSF\Controllers\Controller;
use \PG\MSF\Client\Http\Client;
use App\Models\Wallet\ApplicationModel;

/**
 * Class Controller
 * @package PG\MSF\Controllers
 */
class BaseController extends Controller
{
    public $resCode = 0;
    public $resMsg = '';
    public $resStauts = 'OK';
    public $data = null;
//0,奖励1，消耗2，充值
    const DGAS_REWARD=0;
    const DGAS_CONSUME=1;
    const DGAS_RECHARGE=2;
    const DGAS_CONSUME_SCHARGE=3;//兑换手续
    const DGAS_RECHARGE_SCHARGE=2;//充值手续
    const DGAS_NORMAL=0;//正常操作
    const DGAS_S2S=4;

    const SUB_DGAME=0;
    const SUB_DGAS=1;
    const SUB_DGAME2SUB=2;
    const TRANSFER=3;//转帐

    const  DGAS2SUBCHAIN='dgas2subchain';

    const  SUCCESS=1;
    const  FAIL=0;

    public $masterDb;
    public $slaveDb;
    public function __construct(string $controllerName, string $methodName)
    {
        parent::__construct($controllerName, $methodName);

        $poa_flag = $this->getContext()->getInput()->getHeader('poa_flag');
        //var_dump($poa_flag);
        $this->masterDb = $this->getMysqlPool('master');
        $para=$this->getContext()->getInput()->getAllPostGet();
        yield $this->BeforAction($para);
    }

    public function BeforAction($para){
        if(isset($para['code'])&& isset($para['sign'])){
            $key2 = yield $this->checkKeycode($para['sign']);
            $iv   = strrev($key2);
            $reqstr = openssl_decrypt(base64_decode($para['code']), 'aes-128-cbc', $key2, true, $iv);
            $para = json_decode($reqstr,true);
        }
        $method= $this->getContext()->getActionName();
        if(isset($para['appid'])){
            $rel=yield $this->getObject(ApplicationModel::class)->getParaByAppid($para['appid'],'is_disable');
            $this->log('base',$rel,'黑名单');
            if($rel[0]['is_disable']==1 && !in_array($method,$this->exceptMethod)){
                $this->resCode=1;
                $this->resMsg='该应用已禁用';
                $this->encryptOutput($key2,$iv,200);return;
            }

        }

    }

    protected function genRandStr( $length = 8 )
    {
        $chars = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l','m', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y','z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y','Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $keys = array_rand($chars, $length);
        $str = '';
        for($i = 0; $i < $length; $i++)
        {
            $str .= $chars[$keys[$i]];
        }
        return $str;
    }
    /**
     *
     * 计算大数
     *
     * @param $m
     * @param $n
     * @param $x
     * @return mixed|string
     */
    protected function calc($m,$n,$x,$precisions=8){
        $errors=array(
            '被除数不能为零',
            '负数没有平方根'
        );
        $m =  sprintf("%.".$precisions."f",$m);
        $n =  sprintf("%.".$precisions."f",$n);
        bcscale($precisions);
        switch($x){
            case 'add':
                $t=bcadd($m,$n);
                break;
            case 'sub':
                $t=bcsub($m,$n);
                break;
            case 'mul':
                $t=bcmul($m,$n);
                break;
            case 'div':
                if($n!=0){
                    $t=bcdiv($m,$n);
                }else{
                    return $errors[0];
                }
                break;
            case 'pow':
                $t=bcpow($m,$n);
                break;
            case 'mod':
                if($n!=0){
                    $t=bcmod($m,$n);
                }else{
                    return $errors[0];
                }
                break;
            case 'sqrt':
                if($m>=0){
                    $t=bcsqrt($m);
                }else{
                    return $errors[1];
                }
                break;
        }
//        $t=preg_replace("/\..*0+$/",'',$t);
        $t=rtrim(rtrim($t, '0'), '.');
        return $t;

    }
    public function getApiResult()
    {
        return ['code'=>0,
            'msg'=>'',
            'status'=>'OK',
            'data'=>null];
    }

    /**
     * 生成对称加密密钥 key2
     *
     * @return array|\Generator
     */
    public function genKeycode()
    {
        $code = $this->getContext()->getInput()->get('code');
        $key2 = $this->genRandStr(16);
        $rsaPrivateKey = file_get_contents(getInstance()->config->get("resource.rsa_privatekey", ""));
        $rsa = openssl_private_encrypt($key2,$encrypted,$rsaPrivateKey);
        $key2Encode = base64_encode($encrypted);
        openssl_private_decrypt(base64_decode($code),$key1,$rsaPrivateKey);//私钥解密
        $this->getRedisPool('tw')->set('authcode_'.$key1, $key2,10 * 60);

        return ['key1'=>$key1,'key2'=>$key2Encode];
    }

    /**
     * 每次业务接口请求时执行操作
     *
     * @param $key1
     * @return mixed
     */
    public function checkKeycode($key1)
    {
        $key2 = $this->getRedisPool('tw')->get('authcode_'.$key1);
        return $key2;
    }


    /**
     * 清除业务请求密钥，业务不做保留
     *
     * @param $key1
     * @return \Generator
     */
    public function clearKeycode($key1){
        yield $this->getRedisPool('tw')->del('authcode_'.$key1);
    }

    /**
     * 解密请求数据
     *
     * @param $data   // 请求接口数据
     * @param $key1
     * @return string
     */
    public function decryptData($data,$key2)
    {
        /*$key2 = $this->checkKeycode($key1);
        if(!$key2){
            return "";
        }*/
        $iv = strrev($key2);
        return openssl_decrypt(base64_decode($data), 'aes-128-cbc', $key2, true, $iv);
    }

    /**
     * 加密响应数据
     *
     * @param $jsonstr
     * @param $key2
     */
    public function encryptData($jsonstr,$key2)
    {
        $iv = strrev($key2);
        base64_encode(openssl_encrypt($jsonstr, 'aes-128-cbc', $key2, true, $iv));
    }
    /**
     *
     * 加密输出
     *
     * @param $key2
     * @param $iv
     * @param int $status
     */
    public function encryptOutput($key2,$iv,$status = 200){

        $jsondata = ['code' => $this->resCode,
            'msg' => $this->resMsg,
            'status' => $this->resStauts,
            'data' => $this->data];
        $jsonstr = json_encode($jsondata);
        $resstr = base64_encode(openssl_encrypt($jsonstr, 'aes-128-cbc', $key2, true, $iv));
        $this->output($resstr,$status);
    }

    // 封装data为对象内部数据 JSON
    public function interOutputJson($status = 200)
    {
        $jsondata = ['code' => $this->resCode,
            'msg' => $this->resMsg,
            'status' => $this->resStauts,
            'data' => $this->data];
        $this->outputJson($jsondata,$status);
    }

    // 封装data为对象内部数据 Raw
    public function interOutput($status = 200){
        $this->output($this->data,$status);
    }

    // 封装data为对象内部数据 视图
    public function interOutputView($view = null){
        try {
            $this->outputView($this->data, $view);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @brief 检测函数必传参数是否存在
     * @param $params array 关联数组 要检查的参数
     * @param array $mod array 索引数组 要检查的字段
     * @param array $fields array 索引数组 额外要检查参数的字段
     * @return bool
     */
    public function checkParamsExists($params, $mod = [], $fields = [])
    {
        if (empty($params)) {
            return false;
        }
        $params = is_array($params) ? $params : [$params];

        if ($fields) {
            $fields = array_flip($fields);
            $params = array_merge($params, $fields);
        }

        foreach ($mod as $mod_key => $mod_value) {
            if (!array_key_exists($mod_value, $params)) {
                return false;
            }
        }
        return true;

    }

    /**
     * 生成uuid
     * @return string
     */
    public  function guid(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);// "}"
            return $uuid;
        }
    }

    /**
     * 根据appid获得精度长度
     * @param $appid
     * @return int
     */
    public function getPrecisionsLen($appid){
        $pre=yield $this->getObject(ApplicationModel::class)->getParaByAppid($appid,'precisions');
        return strlen($pre[0]['precisions']);
    }

    /**
     * 日志
     * @param $filename
     * @param $data
     * @param $description
     */
    public function log($filename,$data,$description){
        $content=[PHP_EOL.$description.'================'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($data,TRUE).PHP_EOL];
        file_put_contents('/tmp/'.$filename,$content,FILE_APPEND);
    }

}