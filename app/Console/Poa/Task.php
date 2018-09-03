<?php
namespace App\Console\Poa;

use App\Console\Common\BaseController;
use App\Models\Poa\Poa;
use App\Models\Wallet\DgasModel;
use App\Models\Wallet\SubchainModel;
use \PG\MSF\Client\Http\Client;
use App\Models\Wallet\AccountModel;

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

    private $poaMode ;

    public function actionRun()
    {
        echo '命令行示例';
    }

    public function actionCalcpoa(){
        $this->poaMode = $this->getObject(Poa::class);
        date_default_timezone_set('PRC');
        $currdate = strtotime(date("Y-m-d"));   // 当前日期 0时0分0秒
        $preHour = date("H") - 1;
        if ($preHour < 0) {
            $preHour = 23;
        }
        var_dump(date("H-m-s"),$preHour,$currdate);
        $res = yield $this->masterDb->select("*")->from("poa_balance")->where("timepre", $currdate)
            ->where("hour", $preHour)
            ->limit(10)
            ->go();
        var_dump("ffff",$res);

        $poaInfos = $res['result'];
        if(!$poaInfos){
            return;
        }
        foreach ($poaInfos as $r){
            var_dump("poainfos:".$r);
            $appid = $r['appid'];
            $address = $r['address'];
            $scoreInfoObj = json_decode($r['scoreinfo'], true);
            $poaScore = yield $this->poaMode->calcRewardWithPoa($appid, $address, $scoreInfoObj);
            $dgas = $poaScore['dgas'];
            yield $this->poaMode->rewardGas($appid, $address, $dgas);
        }
    }

    //添加用户dgas
    public function consumeDgas($para){
        $data=['dgas'=>[$para['num'],$para['up']]];
        $query=['appid'=>$para['appid'],
            'address'=>$para['address']];
        $rel=yield $this->getObject(AccountModel::class)->UpDataByQuery($data,$query,'');
        if($rel)
            return ['status'=>true,'mes'=>'操作成功'];
        else
            return ['status'=>false,'mes'=>'操作失败'];
    }
    /**
     * Dgame 充值 Dgas 处理任务
     *
     * @throws \PG\MSF\Client\Exception
     */
    public function actionDame2dgas(){
        $ehtapiHost = getInstance()->config->get("url.ethapi");
        while (true){
            $dgames2dgas = yield $this->masterDb->select("*")->from("dgame2dgas")->where("status",0)->go();

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
                    $account=yield $this->consumeDgas($data);

                    //插入gas_log
                    $gas_log=['appid'=>$dr['appid'],
                        'address'=>$address,
                        'exchange_id'=>$dr['id'],
                        'type'=>self::DGAS_RECHARGE,
                        'flag'=>self::DGAS_NORMAL];
                    $this->getObject(DgasModel::class)
                        ->Insert_Gaslog($gas_log);
                    //$gas_log=yield $this->addGas_log_arr($gas_log,'');
                }
            }
            unset($dgames2dgas);
            echo "Sleep 5!!!!!\r\n";
            sleep(5);
        }

    }

    public function actionDame2subchain(){
        $ehtapiHost = getInstance()->config->get("url.ethapi");
        while (true) {
            $dgames2dgas = yield $this->masterDb->select("*")->from("dgame2subchain")->where("status", 0)->go();

            foreach ($dgames2dgas['result'] as $dr) {
                $txid = $dr['txid'];
                $client = $this->getObject(Client::class);
                $result = yield $client->goSingleGet($ehtapiHost . '/api?module=transaction&action=gettxreceiptstatus&txhash=' . $txid . '&apikey=YourApiKeyToken');
                var_dump($ehtapiHost . '/api?module=transaction&action=gettxreceiptstatus&txhash=' . $txid . '&apikey=YourApiKeyToken');
                $resbody = json_decode($result['body'], true);
                var_dump($resbody);
                if ($resbody['result']['status']) {
                    $res = yield $this->masterDb->update("dgame2subchain")
                        ->set(['status' => 1])
                        ->where("txid", $txid)->go();

                    if ($res['affected_rows'] > 0) {
                        $subData = ['dgas' => $dr['dgame'],
                            'amount' => $dr['subchain'],
                            'faddress' => $dr['fromaddr'],
                            'taddress' => $dr['address'],
                            'out_trade_no' => $dr['order_sn'],
                            'create_time' => time(),
                            'update_time' => time(),
                            'type' => 0];
                        $subModel = $this->getObject(SubchainModel::class);
                        $result = yield $subModel->SubRecharge($subData, $dr['appid']);
                        var_dump($subData, $result);
                    }
                }
            }
            unset($dgames2dgas);
            echo "sleep 5!!!!!\r\n";
            sleep(5);
        }
    }
}
