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
       $this->log('base',$para,'参数');
        $method= $this->getContext()->getActionName();
        $this->log('base',['方法名'=>$method,'控制器'=>$this->getContext()->getControllerName()],'接口地址');
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



//判断是否余额不足
    public function balanceIsEnough($para,$num){
        $swich_time=getInstance()->config->get('app2account_time','2018-07-10 00:00:00');
        if(time()<strtotime($swich_time))
            $balance=yield $this->masterDb->select('dgasfee as dgas')->from('application')
                ->where('appid', $para['appid'])->go();
        else
            $balance=yield $this->masterDb->select('dgas')->from('account')
                ->where('appid', $para['appid'])
                ->andwhere('address',$para['address'])->go();
        return $balance['result'][0]['dgas']=$balance['result'][0]['dgas']>=$num ? true : false;
    }



    //生成dgas2subchain订单，并生成subchain_log
    public function dgas2subchain($para,$trans,$flag=self::DGAS_NORMAL,$is_deduction=true){
        //计算手续费
        $sc_para = ['appid'=>$para['appid'],
            'fromaddr'=>$para['fromaddr'],
            'toaddr'=>$para['toaddr'],
            'remark'=>'',
            'num'=>$para['subchain'],
            '2num'=>$para['dgas']];
        $charge=$this->CalSCharge($sc_para);

        //判断（手续费+dgas是否大于用户余额）
        $balance=yield $this->masterDb->select('dgas')->from('account')
            ->where('appid', $para['appid'])
            ->andwhere('address',$para['address'])->go();
        if($balance['result'][0]['dgas']<($charge+$para['dgas']))
            return ['status'=>false,'mes'=>'余额不足3'];
        if(isset($para['order_sn']))
            $orderId=$para['order_sn'];
        else
            $orderId=$this->guid();
        //插入dgas兑换子链的订单
        $arr=['ratio'=>$this->getRedio($para['appid']),
            'toaddr'=>$para['address'],  // 设置子链币地址
            'order_sn'=>$orderId,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time(),
            'Scharge'=>$charge];
        $para=array_merge($para,$arr);
        $address = $para['address'];
        unset($para['txid']);
        unset($para['address']);
        $res= yield  $this->masterDb->insert('dgas2subchain')->set($para)->go($trans);//$this->output($para);
        if(!$res['result'])
            return ['status'=>false,'mes'=>'操作失败1'];

        //增加子链日志（subchain_log）
        $type=$flag ? self::SUB_DGAME2SUB : self::SUB_DGAS;
        $res1=yield $this->addSubchain_log($para,$trans,$res['insert_id'],$type);
        if(!$res1['status'])
            return ['status'=>false,'mes'=>'操作失败2'];

        //扣除用户dgas
        $data=['appid'=>$para['appid'],
            'num'=>$para['dgas'],
            'up'=>'-',
            'address'=>$address,  // 子链地址
            'toaddr'=>$para['toaddr']];
        $account=yield $this->consumeDgas($data,$trans,'dgas');
        if(!$account['status'])
            return $account;

        //增加dgas日志--消耗(gas_log)
        $res2=yield $this->addGas_log($para,$trans,$res['insert_id'],self::DGAS_CONSUME,$flag);
        if(!$res2['status'])
            return ['status'=>false,'mes'=>'操作失败3'];

        $data['num']=$charge;
       // $swich_time=getInstance()->config->get('app2account_time','2018-07-10 00:00:00');
        //if(time()<strtotime($swich_time))
       //     $account=yield $this->consumeAppDgas($data,$trans);
      //  else
            $account=yield $this->consumeDgas($data,$trans);//扣除用户dgas
        if(!$account['status'])
            return $account;
        //修改
        if($is_deduction){
            $para['num']=$para['dgas'];
            $scharge=yield $this->getSCharge($para,$trans,$res['insert_id']);
            if(!$scharge)
                return ['status'=>false,'mes'=>'操作失败4'];
        }

        return ['status'=>true,'mes'=>'操作成功','data'=>$res];
    }

    //生成dgame2dgas订单，并生成gas_log
    public function dgame2dgas_old($para,$trans,$flag=self::DGAS_NORMAL,$is_deduction=0){
        if(isset($para['order_sn']))
            $orderId=$para['order_sn'];
        else
            $orderId=$this->guid();
        $ratio=getInstance()->config->get('dgame.ratio',1);
        $arr=['order_sn'=>$orderId,
            'ratio'=>$ratio,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time()];
        $para=array_merge($para,$arr);
        //插入订单
        $res= yield  $this->masterDb->insert('dgame2dgas')->set($para)->go($trans);
        if(!$res['result'])
            return ['status'=>false,'mes'=>'操作失败1'];

        /*=================日志===================*/
        $content=[PHP_EOL.'插入订单'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res,TRUE).PHP_EOL];
        $file=$flag ? "/tmp/dgame2subchain" : "/tmp/dgame2dgas";
        file_put_contents($file,$content,FILE_APPEND);

        //增加用户dgas
//        $this->output(1231231);
        $addDgas=$para['dgas']-$para['Scharge'];//充值dgas，扣掉手续费

        $data=['appid'=>$para['appid'],
            'num'=>$addDgas,
            'up'=>'+',
            'address'=>$para['address'],  // 扣Dgas地址
            'toaddr'=>$para['toaddr']];
        /*=================日志===================*/
        $content=[PHP_EOL.'consumeDgas'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res,TRUE).PHP_EOL];
        $file=$flag ? "/tmp/dgame2subchain" : "/tmp/dgame2dgas";
        file_put_contents($file,$content,FILE_APPEND);

        $account=yield $this->consumeDgas($data,$trans,'dgas');

        /*=================日志===================*/
        $content=[PHP_EOL.'增加用户dgas'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($account,TRUE).PHP_EOL];
        $file=$flag ? "/tmp/dgame2subchain" : "/tmp/dgame2dgas";
        file_put_contents($file,$content,FILE_APPEND);

        if(!$account['status'])
            return $account;

        //插入gas_log
        $gas_log=['appid'=>$para['appid'],
            'address'=>$para['address'],
            'exchange_id'=>$res['insert_id']];
//        if($is_deduction)
//            $gas_log['exchange_id']=$is_deduction;
        $gas_log['base']=[['type'=>self::DGAS_RECHARGE,
            'flag'=>$flag],
            ['type'=>self::DGAS_CONSUME,
                'flag'=>self::DGAS_CONSUME_SCHARGE]];
        /*=================日志===================*/
        $content=[PHP_EOL.'插入gas_log'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($gas_log,TRUE).PHP_EOL];
        $file=$flag ? "/tmp/dgame2subchain" : "/tmp/dgame2dgas";
        file_put_contents($file,$content,FILE_APPEND);

        $gas_log=yield $this->addGas_log_arr($gas_log,$trans);

        /*=================日志===================*/
        $content=[PHP_EOL.'插入gas_log'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($gas_log,TRUE).PHP_EOL];
        $file=$flag ? "/tmp/dgame2subchain" : "/tmp/dgame2dgas";
        file_put_contents($file,$content,FILE_APPEND);

        if(!$gas_log['status'])
            return $gas_log;
        return array('status'=>true,'mes'=>'操作成功','data'=>['order'=>$orderId]);
    }

    //生成dgame2dgas订单，并生成gas_log
    public function dgame2dgas($para,$trans,$flag=self::DGAS_NORMAL,$is_deduction=0){
        if(isset($para['order_sn']))
            $orderId=$para['order_sn'];
        else
            $orderId=$this->guid();
        $ratio=getInstance()->config->get('dgame.ratio',1);
        $arr=['order_sn'=>$orderId,
            'ratio'=>$ratio,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time()];
        $para=array_merge($para,$arr);
        //插入订单
        $res= yield  $this->masterDb->insert('dgame2dgas')->set($para)->go($trans);
        if(!$res['result'])
            return ['status'=>false,'mes'=>'操作失败1'];

        /*=================日志===================*/
        $content=[PHP_EOL.'插入订单'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res,TRUE).PHP_EOL];
        $file=$flag ? "/tmp/dgame2subchain" : "/tmp/dgame2dgas";
        file_put_contents($file,$content,FILE_APPEND);
        return array('status'=>true,'mes'=>'操作成功','data'=>['order'=>$orderId]);
    }

    //生成dgame2subchain订单,并生成subchain_log
    public function dgame2subchain($para,$trans){
        $orderId=$this->guid();
        $ratio=getInstance()->config->get('dgame.ratio',100000);
        $arr=['txid'=>$this->guid(),
            'order_sn'=>$orderId,
            'ratio'=>$ratio,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time()];
        $para=array_merge($para,$arr);

        //插入dgame转子链的订单
        $res= yield  $this->masterDb->insert('dgame2subchain')->set($para)->go($trans);

        if(!$res['result'])
            return ['status'=>false,'mes'=>'操作失败'];

        //插入子链日志
        $sub_log=yield $this->addSubchain_log($para,$trans,$res['insert_id'],self::DGAS_NORMAL);
        if(!$sub_log['status'])
            return ['status'=>false,'mes'=>'操作失败'];
        return array('status'=>true,'data'=>['order'=>$orderId,'id'=>$res['insert_id']]);
    }


    //生成dgas2subchain订单，并生成subchain_log  更新后方法，可替换dgas2subchain
    public function dgas2subchain_new($para,$trans,$flag=self::DGAS_NORMAL,$is_deduction=true){
        //插入dgas兑换子链的订单
        $orderId=$this->guid();
        $ratio=$this->getRedio($para['appid']);
        $arr=['ratio'=>$ratio,
            'order_sn'=>$orderId,
            'status'=>0,
            'create_time'=>time(),
            'update_time'=>time(),
            'Scharge'=>$para['Scharge']];
        $para=array_merge($para,$arr);
        var_dump('dgas2sub插入订单data',$arr);
        $res= yield  $this->masterDb->insert('dgas2subchain')->set($para)->go($trans);//$this->output($para);

        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'插入订单'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res,TRUE).PHP_EOL];
        file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
        /*=================日志END===================*/

        if(!$res['result'])
            return ['status'=>false,'mes'=>'操作失败'];
        //扣除用户dgas
        $data=['appid'=>$para['appid'],
            'num'=>$para['dgas']+$para['Scharge'],
            'up'=>'-',
            'address'=>$para['fromaddr'],
            'toaddr'=>$para['toaddr']];
        var_dump('dgas2sub扣除用户时',$data);
        $account=yield $this->consumeDgas($data,$trans,'dgas');
        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'扣除用户dgas'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($account,TRUE).PHP_EOL];
        file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
        /*=================日志END===================*/

        if(!$account['status'])
            return $account;

        //增加子链日志（subchain_log）
        $type=$flag ? self::SUB_DGAME2SUB : self::SUB_DGAS;
        $subchain_log=['appid'=>$para['appid'],
            'type'=>$type,
            'address'=>$para['toaddr'],
            'exchange_id'=> $res['insert_id'],
            'create_time'=>time()];
        var_dump('插入子链日志',$subchain_log);
        $res1=yield $this->addSubchain_log_new($subchain_log,$trans);

        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'插入子链日志'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($res1,TRUE).PHP_EOL];
        file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
        /*=================日志END===================*/

        if(!$res1['status'])
            return $res1;

        //插入gas_log
        $gas_log=['appid'=>$para['appid'],
            'address'=>$para['fromaddr'],
            'exchange_id'=>$res['insert_id']];
        $gas_log['base']=[['type'=>self::DGAS_CONSUME,
            'flag'=>self::DGAS_NORMAL],
            ['type'=>self::DGAS_CONSUME,
                'flag'=>self::DGAS_CONSUME_SCHARGE]];
        var_dump('插入gas_log',$gas_log);
        $gas_log=yield $this->addGas_log_arr($gas_log,$trans);

        /*=================日志BEGAIN===================*/
        $content=[PHP_EOL.'插入gas_log'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($gas_log,TRUE).PHP_EOL];
        file_put_contents("/tmp/dgas2subchain",$content,FILE_APPEND);
        /*=================日志END===================*/
        if(!$gas_log['status'])
            return ['status'=>false,'mes'=>'操作失败3'];
        return ['status'=>true,'mes'=>'操作成功','data'=>['order'=>$orderId]];
    }

    //生成subchain_log
    public function addSubchain_log($para,$trans,$insert_id,$type){
        $subchain_log=['appid'=>$para['appid'],
            'type'=>$type,
            'address'=>$para['toaddr'],
            'exchange_id'=> $insert_id,
            'create_time'=>time()];
        $res1=yield  $this->masterDb->insert('subchain_log')->set($subchain_log)->go($trans);
        if(!$res1['result'])
            return ['status'=>false,'mes'=>'操作失败'];
        return ['status'=>true,'mes'=>'操作成功'];
    }

    //生成subchain_log
    public function addSubchain_log_new($subchain_log,$trans){

        $res1=yield  $this->masterDb->insert('subchain_log')->set($subchain_log)->go($trans);
        if(!$res1['result'])
            return ['status'=>false,'mes'=>'操作失败'];
        return ['status'=>true,'mes'=>'操作成功'];
    }

    //生成gas_log
    public function addGas_log($para,$trans,$insert_id,$type,$flag=self::DGAS_NORMAL){
        $dgas_log=['appid'=>$para['appid'],
            'flag'=>$flag,
            'type'=>$type,
            'address'=>$para['toaddr'],
            'exchange_id'=> $insert_id,
            'create_time'=>time()];
        $res1=yield  $this->masterDb->insert('gas_log')->set($dgas_log)->go($trans);
        if(!$res1['result'])
            return ['status'=>false,'mes'=>'操作失败'];
        return ['status'=>true,'mes'=>'操作成功'];
    }

    //生成gas_log批量插入
    public function addGas_log_arr($para,$trans){
        $data=$para['base'];
        unset($para['base']);
        $num=0;
        foreach ($data as $k=>$v){
            $data[$k]=array_merge($para,$v);
            $data[$k]['create_time']=time();
            $res1=yield  $this->masterDb->insert('gas_log')->set($data[$k])->go($trans);
            if($res1['result'])
                $num++;
        }

        if($num!=count($data))
            return ['status'=>false,'mes'=>'操作失败'];
        return ['status'=>true,'mes'=>'操作成功'];
    }

    //根据参数算出手续费
    /*手续费 = 开发者appid + fromaddr + toaddr + amount+ + 备注
     *手续费 = （手续费）* 0.0001
     * Gas前期是从Applicaition表里相应的appid手续费字段减
     * */
    //扣费并添加gas_log日志
    public function getSCharge($para,$trans,$id,$type=self::DGAS_CONSUME,$flag=self::DGAS_CONSUME_SCHARGE){
        $charge=($para['appid']+$para['fromaddr']+$para['toaddr']+$para['remark']+$para['num'])*getInstance()->config->get('ServerCharge',0.0001);
        $balance=yield $this->masterDb->select('dgasfee')->from('application')
            ->where('appid', $para['appid'])->go();
        $dgasfee=$balance['result'][0]['dgasfee']-$charge;
        if($dgasfee<0)
            return false;
        $up= yield $this->masterDb->update('application')->set('dgasfee', $dgasfee)->where('appid', $para['appid'])->go($trans);
        if($up['affected_rows']==0)
            return false;
        unset($para['num']);
        unset($para['remark']);
        $gas=yield $this->addGas_log($para,$trans,$id,$type,$flag);
        if($gas['status'])
            return $gas;
        return true;
    }



    //扣除或添加用户dgas
    public function consumeDgas($para,$trans,$type='scharge'){
        $balance=yield $this->masterDb->select('dgas,fee')->from('account')
            ->where('appid', $para['appid'])
            ->andwhere('address',$para['address'])->go();
        $dgasfee= $para['up']=='-' ? $balance['result'][0]['dgas']-$para['num'] : $balance['result'][0]['dgas']+$para['num'];

        var_dump("dgas feel",$balance);
        var_dump("consumedgas",$para);
        if($dgasfee<0)
            return ['status'=>false,'mes'=>'余额不足'];
        $fee= $balance['result'][0]['fee'];
        if($type=='scharge')
            $fee= $fee+$para['num'];
        $data=array('dgas'=>$dgasfee,'fee'=>$fee);
        $up= yield $this->masterDb->update('account')->set($data)
            ->where('appid', $para['appid'])
            ->andwhere('address',$para['address'])->go($trans);
        if($type=='scharge'){
            $aplication=$this->getObject(ApplicationModel::class);
            //获得开发商累计手续费
            $ap=yield $aplication->getParaByAppid($para['appid'],'totalfee');
            $apfee=$ap[0]['totalfee']+$para['num'];
            $data=['totalfee'=>$apfee];
            $apUp=yield $aplication->UpByAppid($para['appid'],$data);
        }
        if($up['affected_rows']==0)
            return ['status'=>false,'mes'=>'操作失败'];
        return ['status'=>true,'mes'=>'操作成功'];
    }
    //扣除开发商dgas
    public function consumeAppDgas($para,$trans){
        $balance=yield $this->masterDb->select('dgasfee')->from('application')
            ->where('appid', $para['appid'])->go();
        $dgasfee=$balance['result'][0]['dgasfee']-$para['num'];
        if($dgasfee<0)
            return ['status'=>false,'mes'=>'余额不足'];
        $up= yield $this->masterDb->update('application')->set('dgasfee',$dgasfee)
            ->where('appid', $para['appid'])->go($trans);
        if($up['affected_rows']==0)
            return ['status'=>false,'mes'=>'操作失败'];
        return ['status'=>true,'mes'=>'操作成功'];
    }


//根据id和数据表得到订单编号
    public function getOrderById($id,$tb){
        var_dump("getOrderById :",$id,$tb);
        $order_sn=yield $this->masterDb->select('order_sn')->from($tb)
            ->where('id', $id)->go();
        var_dump("ordersn:",$order_sn);
        return $order_sn['result'][0]['order_sn'];
    }

    //订单生成后，发出充值子链请求，每隔一分钟请求一次，直到成功，最多三次
    public function SubRechargeLimit3($para,$appid)
    {
//        $time=1;
//        $flag=false;
//        while($time<=3 and $flag==false){
            $info= $this->getObject(ApplicationModel::class);
            $rel=yield $info->getParaByAppid($appid,'pay_callback_url,precisions');
var_dump('rel',$rel);
             $mul_len=getInstance()->config->get('dgas.mul_num',1000000000000000000);
            $para['dgas']=$this->calc($para['dgas'],$mul_len,'mul');
            var_dump('706------',$para);
            var_dump('706------',$rel[0]['precisions']);
            $para['amount']=$this->calc($para['amount'],$rel[0]['precisions'],'mul');

            $rel= yield $this->subRecharge($para,$rel[0]['pay_callback_url']);
//            if($rel=='ok')
//                $flag==true;
//            else{
//                sleep(60);
//                $this->SubRechargeLimit3($para);
//            }
//            $time++;
//        }
//        return 'ok';
        return $rel;

    }

    //发出充值子链请求
    public function subRecharge($para,$url){
        $client  = $this->getObject(Client::class);
        $result = yield $client->goSinglePost($url,$para);
        return $result['body'];
    }

//添加子链转帐记录，并扣除手续费
    public function adds2s($para,$trans){
        $arr = ['txid'=>$this->guid(),
                'status'=>0,
                'create_time'=>time(),
                'update_time'=>time()];
        $sc_para = ['appid'=>$para['appid'],
            'fromaddr'=>$para['fromaddr'],
            'toaddr'=>$para['toaddr'],
            'remark'=>'',
            'num'=>$para['subchain'],
            '2num'=>$para['subchain']];
        $scharge=$this->CalSCharge($sc_para);
        $para=array_merge($para,$arr);
        $res= yield  $this->masterDb->insert('subchain2subchain')->set($para)->go($trans);
        if(!$res['result'])
            return array('status'=>false,'mes'=>'操作失败');

        //前期扣除开发商dgas，超过配置时间改为扣用户dgas
        $data=['appid'=>$para['appid'],
            'num'=>$scharge,
            'up'=>'-',
            'address'=>$para['fromaddr'],
            'toaddr'=>$para['toaddr']];
        $swich_time=getInstance()->config->get('app2account_time','2018-07-10 00:00:00');
        if(time()<strtotime($swich_time))
            $account=yield $this->consumeAppDgas($data,$trans);
        else
            $account=yield $this->consumeDgas($data,$trans);//扣除用户dgas
        if(!$account['status'])
            return $account;

        //添加gas_log
        unset($para['num']);
        unset($para['remark']);
        unset($para['scharge']);
        $para['toaddr']=time()<strtotime($swich_time) ? $this->getAddressByAppid($para['appid']) : $para['fromaddr'];//由于子链转子链,加gas_log address应该记录操作者,暂时修改,优化时切记不要这么做
        $gas=yield $this->addGas_log($para,$trans,$res['insert_id'],self::TRANSFER,self::DGAS_CONSUME_SCHARGE);
        if(!$gas['status'])
            return $gas;
        return array('status'=>true,'mes'=>'操作成功','data'=>$res);
    }
    public function CalSCharge1($para){
        return array_sum($para);
    }
    public function CalSCharge($para){
       unset($para['2num']);
       $len=0;
       foreach ($para as $v){
           $len+=strlen($v);
       }
        return $len*getInstance()->config->get('ServerCharge',0.0001);
        //return array_sum($para)*getInstance()->config->get('ServerCharge',0.0001);
    }

    public function getAddressByAppid($appid){
        $balance=yield $this->masterDb->select('address')->from('application')->where('appid', $appid)->go();
        var_dump('开发商子链地址',$balance['result'][0]['address']);
        return $balance['result'][0]['address'];
    }

public function log($filename,$data,$description){
    $content=[PHP_EOL.$description.'================'.date("Y-m-d H:i:s",time()).PHP_EOL.var_export($data,TRUE).PHP_EOL];
    file_put_contents('/tmp/'.$filename,$content,FILE_APPEND);
}

}
