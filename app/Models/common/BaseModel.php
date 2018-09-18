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
    const DGAS_REWARD = 0;
    const DGAS_CONSUME = 1;
    const DGAS_RECHARGE = 2;
    const DGAS_CONSUME_SCHARGE = 3;//exchange charge
    const DGAS_RECHARGE_SCHARGE = 2;//exchange charge
    const DGAS_NORMAL = 0;//normal operation
    const DGAS_S2S = 4;
    const DGAS_DGAME2S = 1;

    const SUB_DGAME = 0;
    const SUB_DGAS = 1;
    const SUB_DGAME2SUB = 2;
    const TRANSFER = 3;//transfer


    const  SUCCESS = 1;
    const  FAIL = 0;
    public $masterDb;
    public $slaveDb;

    public function __construct()
    {
        parent::__construct();
        $this->masterDb = $this->getMysqlPool('master');
    }

    //create uuid
    public function guid()
    {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);// "}"
            return $uuid;
        }
    }


    public static function getSign($param, $code, $sign_type = 'MD5')
    {
        $param = self::paramFilter($param);
        $param = self::paramSort($param);
        $param_str = self::createLinkstring($param);
        $param_str = $param_str . $code;
        return self::createSign($param_str, $sign_type);
    }

    /**
     * Check data signature
     *
     * @param  string $sign The signature received by the interface
     * @param  array $param Signature array
     * @param  string $code Safety check code
     * @param  string $sign_type Signature type
     * @return boolean true success，false fail
     */
    public static function checkSign($sign, $param, $code, $sign_type = 'MD5')
    {
        return $sign == self::getSign($param, $code, $sign_type);
    }

    /**
     * Remove empty values from the array
     *
     * @param  array $param Signature array
     * @return array
     */
    private static function paramFilter($param)
    {
        $param_filter = array();
        foreach ($param as $key => $val) {
            if ($key == 'sign' || $key == 'sign_type' || !strlen($val)) {
                continue;
            }
            $param_filter[$key] = $val;
        }
        return $param_filter;
    }

    /**
     * Key name ascending order array
     *
     * @param  array $param
     * @return array
     */
    private static function paramSort($param)
    {
        ksort($param);
        reset($param);
        return $param;
    }

    /**
     * Concatenate all the element values of the array into strings
     *
     * @param  array $param An array that needs to be spliced
     * @return string       The string after splicing is completed
     */
    private static function createLinkstring($param)
    {
        $str = '';
        foreach ($param as $key => $val) {
            $str .= $val;
        }
        if (get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }
        return $str;
    }

    /**
     * Create the signature string
     *
     * @param  string $param Strings that need to be encrypted
     * @param  string $type Signature type    defaults：MD5
     * @return string
     */
    private static function createSign($param, $type = 'MD5')
    {
        $type = strtolower($type);
        if ($type == 'md5') {
            return md5($param);
        }
    }

    /**
     *
     * Calculation of large number
     *
     * @param $m
     * @param $n
     * @param $x
     * @return mixed|string
     */
    public function calc($m, $n, $x, $precisions = 8)
    {
        $errors = array(
            'The divisor cannot be zero',
            'Negative Numbers have no square root'
        );
        $m = sprintf("%." . $precisions . "f", $m);
        $n = sprintf("%." . $precisions . "f", $n);
        bcscale($precisions);
        switch ($x) {
            case 'add':
                $t = bcadd($m, $n);
                break;
            case 'sub':
                $t = bcsub($m, $n);
                break;
            case 'mul':
                $t = bcmul($m, $n);
                break;
            case 'div':
                if ($n != 0) {
                    $t = bcdiv($m, $n);
                } else {
                    return $errors[0];
                }
                break;
            case 'pow':
                $t = bcpow($m, $n);
                break;
            case 'mod':
                if ($n != 0) {
                    $t = bcmod($m, $n);
                } else {
                    return $errors[0];
                }
                break;
            case 'sqrt':
                if ($m >= 0) {
                    $t = bcsqrt($m);
                } else {
                    return $errors[1];
                }
                break;
        }
//        $t=preg_replace("/\..*0+$/",'',$t);
        $t = rtrim(rtrim($t, '0'), '.');
        return $t;

    }


}