<?php
namespace App\Console\Poa;

use App\Console\Common\BaseController;
use App\Models\Wallet\ApplicationModel;
use PG\MSF\Pools\Miner;
use \PG\MSF\Client\Http\Client;

class Task extends BaseController
{
    //0,奖励1，消耗2，充值
    const DGAS_REWARD=0;
    const DGAS_CONSUME=1;
    const DGAS_RECHARGE=2;
    const DGAS_CONSUME_SCHARGE=3;//兑换手续
    const DGAS_RECHARGE_SCHARGE=2;//充值手续
    const DGAS_NORMAL=0;//正常操作
    const DGAS_S2S=4;

    public function actionRun()
    {
        echo '命令行示例';
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
    /**
     * Dgame 充值 Dgas 处理任务
     *
     * @throws \PG\MSF\Client\Exception
     */
    public function actionDame2dgas(){
        $dgames2dgas = yield $this->masterDb->select("*")->from("dgame2dgas")->where("status",0)->go();
        $ehtapiHost = getInstance()->config->get("url.ethapi");
        foreach ($dgames2dgas['result'] as $dr){
            var_dump($dr);
            $txid = $dr['txid'];
            $client  = $this->getObject(Client::class);
            $result = yield $client->goSingleGet($ehtapiHost.'/api?module=transaction&action=gettxreceiptstatus&txhash='.$txid.'&apikey=YourApiKeyToken');

            $resbody = json_decode($result['body'],true);
            var_dump($resbody);
            if($resbody['result']['status']){
                $up = yield $this->masterDb->update("dgame2dgas")
                    ->set(['status'=>1])
                    ->where("txid",$txid)->go();
                if($up['affected_rows']==0){
                    continue;
                }
                $addDgas=$dr['dgas']-$dr['Scharge'];//充值dgas，扣掉手续费
                $address=$dr['address'];

                $data=['appid'=>$dr['appid'],
                    'num'=>$addDgas,
                    'up'=>'+',
                    'address'=>$address];            // 扣Dgas地址
                $account=yield $this->consumeDgas($data,'','dgas');

                //插入gas_log
                $gas_log=['appid'=>$dr['appid'],
                    'address'=>$address,
                    'exchange_id'=>$dr['id']];
                $gas_log['base']=[['type'=>self::DGAS_RECHARGE,
                    'flag'=>self::DGAS_NORMAL],
                    ['type'=>self::DGAS_CONSUME,
                        'flag'=>self::DGAS_CONSUME_SCHARGE]];
                $gas_log=yield $this->addGas_log_arr($gas_log,'');
            }
        }
    }

    public function actionDame2subchain(){
        $dgames2dgas = yield $this->masterDb->select("*")->from("dgame2subchain")->where("status",0)->go();
        $ehtapiHost = getInstance()->config->get("url.ethapi");
        foreach ($dgames2dgas['result'] as $dr){
            $txid = $dr['txid'];
            $client  = $this->getObject(Client::class);
            $result = yield $client->goSingleGet($ehtapiHost.'/api?module=transaction&action=gettxreceiptstatus&txhash='.$txid.'&apikey=YourApiKeyToken');

            $resbody = json_decode($result['body'],true);
            if($resbody['result']['status']){
                yield $this->masterDb->update("dgame2subchain")
                    ->set(['status'=>1])
                    ->where("txid",$txid)->go();
            }
        }
    }

    public function actionRecive()
    {

    }


    public function checkTask(){
        $dbres = [];

        $reqAddr = "11111";
        $priveRes = $dbres[$reqAddr]; // 上一条记录
        $priveResTime = $priveRes['time'] ;//

        $spaceTime = time() - $priveResTime; //

    }
    /**
     *
     * 5分钟上报
     *
     */
    public function checkFiveTask()
    {
        $deviceInfo = ["isroot"=>"val",
            "key1"=>"val",
            "key2"=>"val",
            "key3"=>"val"];

        $totalScore = 1;

        foreach ($deviceInfo as $k=>$v){
            switch ($k){
                case "isroot":
                    if(!$v){
                        $totalScore -= $v;
                    }
                break;
            }
            if($totalScore <= 0){
                break;
            }
        }
        //$res = $this->masterDb->select("*")->from("poa")->go();
    }

    /**
     * 奖励DGAS结算
     *
     * @param $appid
     * @param $address
     * @param $dgas
     */
    function rewardGas($appid,$address,$dgas){
        $currentRes = $this->masterDb->select("*")
            ->from("account")
            ->where("appid",$appid)
            ->where("address",$address)
            ->go();
        foreach ($currentRes as $v){
            $rewardGas = $v['dgas'] + $dgas;
            $this->masterDb->update("account")
                ->set(['dgas'=>$rewardGas,
                    'update_time'=>time()])
                ->where('appid',$v['appid'])
                ->where('address',$v['address'])
                ->go();
        }
    }


    /**
     * 计算Poa分数 应得的奖励  同时子链及主链应得(在手续扣除时计算)
     *
     * @param $poainfo
     * @return array|int
     */
    public function calcRewardWithPoa($poainfo){
        if($poainfo){
            return ['score'=>10,
                'dgas'=>10*1];
        }else{
            return ['score'=>0,
                'dgas'=>0];
        }
    }

    /**
     *
     * Poa 上报
     *
     */
    public function poaReportWithHour($appid,$address,$poainfo){
        date_default_timezone_set('PRC');  //zh area

        //$interval = 1 * 60;   // 1小时结算一次 结算周期
        $reportInterval = 5;    // 5分钟;

        $limitCount = 12;       // 12次为一个整周期;

        $currHour = date("H");                  // 当前小时
        $currdate = strtotime(date("Y-m-d"));   // 当前日期 0时0分0秒


        $res = yield $this->masterDb->select("*")
            ->from("poa_balance")
            ->where('appid',$appid)
            ->where('address',$address)
            ->where("timepre",$currdate)
            ->where('count',$limitCount,Miner::LESS_THAN_OR_EQUAL)
            ->andWhere("hour",$currHour) // 过了当前时间则不算数
            ->go();

        $poaScore = $this->calcRewardWithPoa($poainfo);  // 按需优化 poa必须是上传者的poa
        if (!$res){                                      // 不存在则新增一条记录
            // 检测漏网count = $limitCount 未结算的
            $this->masterDb->insert("poa_balance")
                ->set(['appid'=>$appid,
                    'address'=>$address,
                    'timepre'=>$currdate,
                    'hour'=>$currHour,
                    'count'=>1,
                    'score'=>$poaScore['score'],
                    'dgas'=>$poaScore['dgas'],
                    'historydgas'=>$poaScore['dgas'],
                    'create_time'=>time(),
                    'update_time'=>time()])
                ->go();
            return ;
        }
        $reso = $res['result'];
        foreach($reso as $k=>$v){
            $dgas    = $v['dgas'];
            $historydgas = $v['historydgas'];

            if ($poaScore['dgas'] < $historydgas){ // 最新的小于历史,则以最新的为准
                $historydgas = $poaScore['dgas'];
                $dgas = $historydgas;
            }

            $pk = $v['id'];
            $updatetime = $v['update_time'];

            $diffReportInterval = (time() - $updatetime)/60; // 计算分钟差

            $count = $v['count'] + 1; // 仅在正常上报时设置，及有一次上报异常设置
            // 正常上报区间
            if ($diffReportInterval <= $reportInterval){

                $this->masterDb->update("poa_balance")
                    ->set(['two_report'=>0,
                        'dgas'=>$poaScore['dgas'],      // 缺少分
                        'count'=>$count])
                    ->where('id',$pk)->go();

                if ($count >= $limitCount){
                    // 进行结算任务
                    $this->rewardGas($appid,$address,$dgas);
                }
                continue;
            }

            // 超出上报区间
            if ($v['two_report'] == 0){                  // 超出一次
                $this->masterDb->update("poa_balance")
                    ->set(['two_report'=>1,
                        'dgas'=>$poaScore['dgas'],       // 缺少分
                        'count'=>$count])
                    ->where('id',$pk)->go();

                if ($count >= $limitCount){
                    // 进行结算任务
                    $this->rewardGas($appid,$address,$dgas);
                }

            } elseif ($v['two_report'] == 1){            // 超出二次
                $this->masterDb->update("poa_balance")
                    ->set(['two_report'=>0])
                    ->where('id',$pk)->go();
            }
        }
    }
}
