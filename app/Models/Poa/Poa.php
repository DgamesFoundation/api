<?php

namespace App\Models\Poa;

use App\Models\common\BaseModel;
use PG\MSF\Pools\Miner;

class Poa extends BaseModel
{

    public $ITEMS = ['IS_ROOT' => true,
        'CPU_ABI' => true,
        'Trans' => 0,            // 交易数量
        'FRONT_TIME' => 0,       // 在游戏中时长
        'WIFI_RSSI' => 0,        // wifi信号
        'BATTERY' => 0];         // 电量
    //private $totalRatio = 5000000 * rand(1,100);
    private $totalRatio = 2000000 / 200; // 0.0001 = 每小时的所有用户活跃指数  算出单个人的所得，乘上poa权重获取 200人 200分


    /**
     * 交易数量
     */
    public function calcTransactionScore($appid, $addr)
    {
        $transScore = 0.2;
        $currtime = strtotime(date("Y-m-d"));    // 当前小时

        $startHour = $currtime - 3600;
        $endHour = $currtime;

        $res = yield $this->masterDb->select("id")->from("gas_log")
            ->where("appid", $appid)
            ->where("address", $addr)
            ->where("type", 1)
            ->whereBetween("create_time", $startHour, $endHour)->go();

        $resCount = $res['result'];

        if ($resCount >= 1 and $resCount < 3) {
            $transScore = 0.1;
        } else if (!$resCount) {
            $transScore = 0;
        }
        return $transScore;
    }

    /**
     *
     *  获取指定用户的算分信息
     *
     *  每次上报时获取算分信息，第一次不存在则记录新的
     *
     * @param $appid
     * @param $address
     * @param $day
     * @param $hour
     *
     * @return mixed
     */
    public function getItemInfo($appid, $address, $day, $hour)
    {
        $scoreInfo = yield $this->masterDb->select("scoreinfo")
            ->from("poa_balance")
            ->where('appid', $appid)
            ->where('address', $address)
            ->where('timepre', $day)
            ->where('hour', $hour)
            ->go();
        return $scoreInfo['result'];
    }

    /**
     * 仅更新Userinfo信息分数
     *
     * @param $appid
     * @param $address
     * @param $poainfo
     *
     * @return array
     */
    public function extractItemInfo($appid, $address, $poainfo, $scoreinfoStr = "")
    {
        $currItemInfo = [];
        // 1, 获取Deviceinfo
        $uinfo = yield $this->masterDb->select("deviceinfo")
            ->from("account")
            ->where("appid", $appid)
            ->where("address", $address)
            ->go();
        if ($uinfo) {
            $uinfoRes = $uinfo['result'][0];
        }
        // 2, 更新poa_balance
        if ($uinfoRes && $uinfoRes['deviceinfo']) {
            $formatDeviceData = $this->parseDeviceInfo($uinfoRes['deviceinfo']);
            $formatUserData = $this->parseUserInfo($poainfo);
            $scoreinfo = json_decode($scoreinfoStr, true);
            $isroot = ($formatDeviceData['IS_ROOT'] === true) ? 1 : 0;
            $cpuabi = ($formatDeviceData['CPU_ABI'] === false) ? 1 : 0;   // 非x86为1 , x86 为0
            if ($scoreinfo) {
                $wifi = $formatUserData['WIFI_RSSI'] ? $scoreinfo['WIFI_RSSI'] + 1 : $scoreinfo['WIFI_RSSI'];
                $frontime = isset($formatUserData['FRONT_TIME']) ? $scoreinfo['FRONT_TIME'] + 1 : $scoreinfo['FRONT_TIME'];
                $longtude = $formatUserData['LONGITUDE'] ? $scoreinfo['LONGITUDE'] + 1 : $scoreinfo['LONGITUDE'];
                $gravityx = $formatUserData['GRAVITY_X'] ? $scoreinfo['GRAVITY_X'] + 1 : $scoreinfo['GRAVITY_X'];
                $battery = $formatUserData['BATTERY'] ? $scoreinfo['BATTERY'] + 1 : $scoreinfo['BATTERY'];
                $microphone = isset($formatUserData['MICROPHONE']) ? $scoreinfo['MICROPHONE'] + 1 : $scoreinfo['MICROPHONE'];
                $light = $formatUserData['LIGHT'] ? $scoreinfo['LIGHT'] + 1 : $scoreinfo['LIGHT'];
            } else {
                $wifi = 0;
                $frontime = 0;
                $longtude = 0;
                $gravityx = 0;
                $battery = 0;
                $microphone = 0;
                $light = 0;
            }

            $currItemInfo = [
                'IS_ROOT' => $isroot,
                'CPU_ABI' => $cpuabi,
                'WIFI_RSSI' => $wifi,
                //'Trans'=>$this->calcTransactionScore($appid,$address),            // 交易数量
                'FRONT_TIME' => $frontime,       // 在游戏中
                'LONGITUDE' => $longtude,        // wifi信号
                'GRAVITY_X' => $gravityx,
                'BATTERY' => $battery,
                'MICROPHONE' => $microphone,
                'LIGHT' => $light];
        }
        var_dump($currItemInfo);
        return $currItemInfo;
    }


    /**
     *
     * 解析用户上报的Userinfo
     *
     * @param $userinfo
     * @return array
     */
    public function parseUserInfo($userinfo)
    {
        $userInfo = explode("\n", $userinfo);
        $res = [];
        foreach ($userInfo as $uinfo) {
            list($k, $v) = explode("=", $uinfo);
            $res[$k] = $v ? true : false;
        }
        return $res;
    }

    /**
     *
     * 解析用户上报Deviceinfo
     *
     * @param $deviceinfo
     * @return array
     */
    public function parseDeviceInfo($deviceinfo)
    {
        $deviceInfo = explode("\n", $deviceinfo);
        $res = [];
        foreach ($deviceInfo as $dinfo) {
            list($k, $v) = explode("=", $dinfo);
            $res[$k] = $v;
            if ($k == 'CPU_ABI') {
                $res[$k] = ($v == 'X86') ? true : false;
            } elseif ($k == 'IS_ROOT') {
                $res[$k] = ($v == 'true') ? true : false;
            }
        }
        var_dump("parase", $res);
        return $res;
    }

    public function addBlacklist($address){
        $res = yield $this->masterDb->update("account")
            ->set(['is_disable'=>1])
            ->where("address",$address)
            ->go();
        return $res;
    }

    /**
     *
     * 获取Userinfo的比分  暂无用
     *
     */
    public function getUserInfoScore($appid, $address, $deviceInfo)
    {
        $transNum = yield $this->calcTransactionScore($appid, $address);
        $totalScore = 0;
        foreach ($deviceInfo as $k => $v) {
            if ($k == "IS_ROOT") {
                if ($v === 1) {
                    $this->addBlacklist($address);
                    return 0;
                }
            } elseif ($k == "CPU_ABI") {
                if ($v === 0) {  // 0 为x86
                    $this->addBlacklist($address);
                    return 0;
                }
            } elseif ($k == "FRONT_TIME") {
                if ($v >= 8) {
                    $totalScore += 0.2;
                } else if ($v >= 1 && $v < 8) {
                    $totalScore += 0.1;
                }
            } elseif ($k == "LONGITUDE") {
                if ($v >= 3) {
                    $totalScore += 0.1;
                } else if ($v >= 1 && $v < 3) {
                    $totalScore += 0.05;
                }
            } elseif ($k == "GRAVITY_X") {
                if ($v >= 8) {
                    $totalScore += 0.1;
                } else if ($v >= 1 && $v < 8) {
                    $totalScore += 0.05;
                }
            } elseif ($k == "BATTERY") {
                if ($v >= 8) {
                    $totalScore += 0.1;
                } else if ($v >= 1 && $v < 8) {
                    $totalScore += 0.05;
                }
            } elseif ($k == "MICROPHONE") {
                if ($v >= 8) {
                    $totalScore += 0.1;
                } else if ($v >= 1 && $v < 8) {
                    $totalScore += 0.05;
                }
            } elseif ($k == "LIGHT") {
                if ($v >= 8) {
                    $totalScore += 0.1;
                } else if ($v >= 1 && $v < 8) {
                    $totalScore += 0.05;
                }
            } elseif ($k == "WIFI_RSSI") {
                if ($v >= 8) {
                    $totalScore += 0.2;
                } else if ($v >= 1 && $v < 8) {
                    $totalScore += 0.1;
                }
            }

            if ($totalScore < 0) {
                break;
            }
        }
        if ($transNum >= 3) {
            $totalScore += 0.2;
        } else if ($transNum >= 1 && $transNum < 3) {
            $totalScore += 0.1;
        }
        return $totalScore;
    }

    //生成gas_log
    public function addGas_log($para, $trans, $insert_id, $type, $flag = 0)
    {
        $dgas_log = ['appid' => $para['appid'],
            'flag' => $flag,
            'type' => $type,
            'address' => $para['address'],
            'exchange_id' => $insert_id,
            'create_time' => time()];
        $res1 = yield  $this->masterDb->insert('gas_log')->set($dgas_log)->go($trans);
        if (!$res1['result'])
            return false;
        return true;
    }

    /**
     * 奖励DGAS结算
     *
     * @param $appid
     * @param $address
     * @param $dgas
     * @return \Generator
     */
    function rewardGas($appid, $address, $dgas)
    {
        $currentRes = yield $this->masterDb->select("*")
            ->from("account")
            ->where("appid", $appid)
            ->where("address", $address)
            ->go();
        var_dump("rewardGas-------------------", $currentRes);
        foreach ($currentRes['result'] as $v) {
            $rewardGas = $v['dgas'] + $dgas;
            yield $this->masterDb->update("account")
                ->set(['dgas' => $rewardGas,
                    'update_time' => time()])
                ->where('appid', $appid)
                ->where('address', $address)
                ->go();

            $res = yield $this->masterDb->insert("reward")
                ->set(['appid' => $appid,
                    'address' => $address,
                    'type' => 0,
                    'dgas' => $dgas,
                    'create_time' => time()])
                ->go();
            $lastInsertId = $res['insert_id'];
            $param = ['appid' => $appid,
                'address' => $address];
            yield $this->addGas_log($param, null, $lastInsertId, 0, 0);
        }
    }


    /**
     *
     * 计算Poa分数 应得的奖励  同时子链及主链应得(在手续扣除时计算)
     *
     * @param $appid
     * @param $address
     * @param $poainfo
     * @return array
     */
    public function calcRewardWithPoa($appid, $address, $poainfo)
    {
        if ($poainfo) {
            $score = yield $this->getUserInfoScore($appid, $address, $poainfo);
            $dgas = $score * $this->totalRatio; //$score 区间[0-1]
            //var_dump("calcRew", $score, $dgas);
            return ['score' => $score,
                'dgas' => $dgas];
        } else {
            return ['score' => 0,
                'dgas' => 0];
        }
    }

    /**
     *
     * poa 上报
     *
     *
     * @param $appid
     * @param $address
     * @param $poainfo
     * @return \Generator|void
     */
    public function poaReportWithHour($appid, $address, $poainfo)
    {
        date_default_timezone_set('PRC');  //zh area

        //$interval = 1 * 60;   // 1小时结算一次 结算周期
        $reportInterval = 5;    // 5分钟;

        $limitCount = 12;       // 12次为一个整周期;

        $currHour = date("H");                  // 当前小时
        $currdate = strtotime(date("Y-m-d"));   // 当前日期 0时0分0秒


        $res = yield $this->masterDb->select("*")
            ->from("poa_balance")
            ->where('appid', $appid)
            ->where('address', $address)
            ->where("timepre", $currdate)
            //->where('count', $limitCount, Miner::LESS_THAN_OR_EQUAL)
            ->where("hour", $currHour)// 过了当前时间则不算数
            ->go();

        $reso = $res['result'];
        if (!$reso) {                                      // 不存在则新增一条记录, 以此为poa结算标记
            /*=============   奖励 ==================*/
            $preHour = date("H") - 1;
            if ($preHour < 0) {
                $preHour = 23;
            }
            $previoursRes = yield $this->masterDb->select("*")
                ->from("poa_balance")
                ->where('appid', $appid)
                ->where('address', $address)
                ->where("timepre", $currdate)
                //->where('count', $limitCount, Miner::LESS_THAN_OR_EQUAL)
                ->where("hour", $preHour)// 结算前一小时的奖励
                ->go();
            var_dump("previourres=======================", $appid, $address, $currdate, $preHour, $previoursRes);
            if ($previoursRes['result']) { // 结算Gas的计算
                $previoursScoreInfo = $previoursRes['result'][0]['scoreinfo'];
                $previoursScoreInfoObj = json_decode($previoursScoreInfo, true);
                $poaScore = yield $this->calcRewardWithPoa($appid, $address, $previoursScoreInfoObj);
                $dgas = $poaScore['dgas'];
                yield $this->rewardGas($appid, $address, $dgas);
            }
            /*=============   奖励 ==================*/

            $scoreInfo = yield $this->extractItemInfo($appid, $address, $poainfo);
            // 检测漏网count = $limitCount 未结算的
            yield $this->masterDb->insert("poa_balance")
                ->set(['appid' => $appid,
                    'poainfo' => $poainfo,
                    'scoreinfo' => json_encode($scoreInfo),
                    'address' => $address,
                    'timepre' => $currdate,
                    'hour' => $currHour,
                    'count' => 1,
                    'score' => 0,
                    'dgas' => 0,
                    'historydgas' => 0,
                    'create_time' => time(),
                    'update_time' => time()])
                ->go();
            return;
        }
        foreach ($reso as $k => $v) {
            $oldscore = $v['scoreinfo'];
            $newscoreInfo = yield $this->extractItemInfo($appid, $address, $poainfo, $oldscore);

            $poaScore = yield $this->calcRewardWithPoa($appid, $address, $newscoreInfo);  // 按需优化 poa必须是上传者的poa

            $pk = $v['id'];
            $updatetime = $v['update_time'];

            $diffReportInterval = (time() - $updatetime) / 60; // 计算分钟差
            $count = $v['count'] + 1; // 仅在正常上报时设置，及有一次上报异常设置
            // 正常上报区间
            if ($diffReportInterval <= $reportInterval) {
                yield $this->masterDb->update("poa_balance")
                    ->set([
                        'scoreinfo' => json_encode($newscoreInfo),
                        'two_report' => 0,
                        'dgas' => $poaScore['dgas'],      // 缺少分
                        'count' => $count,
                        'update_time' => time()])
                    ->where('id', $pk)->go();
                continue;
            }

            // 超出上报区间
            if ($v['two_report'] == 0) {                  // 超出一次
                //var_dump("poascore", $poaScore);
                yield $this->masterDb->update("poa_balance")
                    ->set([
                        'scoreinfo' => json_encode($newscoreInfo),
                        'two_report' => 1,
                        'dgas' => $poaScore['dgas'],       // 缺少分
                        'count' => $count,
                        'update_time' => time()])
                    ->where('id', $pk)->go();
            } elseif ($v['two_report'] == 1) {            // 超出二次
                yield $this->masterDb->update("poa_balance")
                    ->set(['two_report' => 0,
                        'update_time' => time()])
                    ->where('id', $pk)->go();
            }
        }
    }
}
