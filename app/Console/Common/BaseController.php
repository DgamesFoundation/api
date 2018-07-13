<?php
/**
 * Web Controller控制器基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace App\Console\Common;

use \PG\MSF\Console\Controller;


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

    public $masterDb;
    public $slaveDb;
    public function __construct(string $controllerName, string $methodName)
    {
        parent::__construct($controllerName, $methodName);

        $poa_flag = $this->getContext()->getInput()->getHeader('poa_flag');
        //var_dump($poa_flag);
        $this->masterDb = $this->getMysqlPool('master');
    }

    protected function genRandStr( $length = 8 )
    {
        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l','m', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y','z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y','Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
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
    protected function calc($m,$n,$x){
        $errors=array(
            '被除数不能为零',
            '负数没有平方根'
        );

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
        $t=preg_replace("/\..*0+$/",'',$t);
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
        $rsaPrivateKey = file_get_contents("/home/king/ssl/rsa/rsa.pem");
        openssl_private_encrypt($key2,$encrypted,$rsaPrivateKey);
        $key2Encode = base64_encode($encrypted);
        openssl_private_decrypt(base64_decode($code),$key1,$rsaPrivateKey);//私钥解密

        yield $this->getRedisPool('p1')->set('authcode_'.$key1, $key2);

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
        $key2 = yield $this->getRedisPool('p1')->get('authcode_'.$key1);
        return $key2;
    }


    /**
     * 清除业务请求密钥，业务不做保留
     *
     * @param $key1
     * @return \Generator
     */
    public function clearKeycode($key1){
        yield $this->getRedisPool('p1')->del('authcode_'.$key1);
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
}
