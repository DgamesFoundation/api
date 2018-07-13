<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/2 0002
 * Time: 下午 4:18
 */

namespace App\Models\common;

use PG\MSF\Models\Model;
use PG\MSF\Coroutine\File;
class BaseModel extends Model
{
    public $masterDb;
    public $slaveDb;
    public function __construct()
    {
        parent::__construct();
        $this->masterDb=$this->getMysqlPool('master');
    }

    //生成uuid
    public  function guid(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
            return $uuid;
        }
    }


    public static function getSign($param, $code, $sign_type = 'MD5'){
        //去除数组中的空值和签名参数(sign/sign_type)
        $param = self::paramFilter($param);
        //按键名升序排列数组
        $param = self::paramSort($param);
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $param_str = self::createLinkstring($param);
        //把拼接后的字符串再与安全校验码直接连接起来
        $param_str = $param_str . $code;
        //创建签名字符串
        return self::createSign($param_str, $sign_type);
    }

    /**
     * 校验数据签名
     *
     * @param  string $sign  接口收到的签名
     * @param  array  $param  签名数组
     * @param  string $code      安全校验码
     * @param  string $sign_type 签名类型
     * @return boolean true正确，false失败
     */
    public static function checkSign($sign, $param, $code, $sign_type = 'MD5'){
        return $sign == self::getSign($param, $code, $sign_type);
    }

    /**
     * 去除数组中的空值和签名参数
     *
     * @param  array $param 签名数组
     * @return array        去掉空值与签名参数后的新数组
     */
    private static function paramFilter($param){
        $param_filter = array();
        foreach ($param as $key => $val) {
            if($key == 'sign' || $key == 'sign_type' || !strlen($val)){
                continue;
            }
            $param_filter[$key] = $val;
        }
        return $param_filter;
    }

    /**
     * 按键名升序排列数组
     *
     * @param  array $param 排序前的数组
     * @return array        排序后的数组
     */
    private static function paramSort($param){
        ksort($param);
        reset($param);
        return $param;
    }

    /**
     * 把数组所有元素值，拼接成字符串
     *
     * @param  array $param 需要拼接的数组
     * @return string       拼接完成以后的字符串
     */
    private static function createLinkstring($param){
        $str = '';
        foreach ($param as $key => $val) {
//            $str .= "{$key}={$val}&";
            $str .= $val;
        }
        //去掉最后一个&字符
//        $str = substr($str, 0, strlen($str) - 1);
        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc()){
            $str = stripslashes($str);
        }
        return $str;
    }

    /**
     * 创建签名字符串
     *
     * @param  string $param 需要加密的字符串
     * @param  string $type  签名类型 默认值：MD5
     * @return string 签名结果
     */
    private static function createSign($param, $type = 'MD5'){
        $type = strtolower($type);
        if($type == 'md5'){
            return md5($param);
        }
//        if($type == 'dsa'){
//            exit('DSA 签名方法待后续开发，请先使用MD5签名方式');
//        }
//        exit("接口暂不支持" . $type . "类型的签名方式");
    }



}