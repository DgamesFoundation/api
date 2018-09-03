<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/27
 * Time: 12:10
 */

namespace App\Controllers\Wallet;

use App\Controllers\Common\BaseController;
use App\Models\Wallet\InfoModel;
use App\Models\Wallet\ApplicationModel;
use App\Models\Wallet\FeeLogModel;
use App\Models\Wallet\DgasModel;
use App\Models\Wallet\SubchainModel;

class Info extends BaseController
{
    /**
     * 兑换比例获取, 数据从常量配置文件中获取
     *
     */
    public function actionTxratio()
    {
        $ctype = $this->getContext()->getInput()->get('ctype');
        $ratio = 0;
        switch ($ctype) {
            case 'dgame':
                $ratio = getInstance()->config->get('dgame.ratio', 100000);
                break;
            case 'dgas':
                $ratio = getInstance()->config->get('dgas.ratio', 100000);
                break;
            default:
                $this->resCode = 1;
                $this->resMsg = "参数异常";
                $this->interOutputJson(403);
        }

        $this->resMsg = "成功";
        $this->data = ['ratio' => $ratio];
        var_dump($this->data);
        $this->interOutputJson();
    }

    //生成回收地址，根据类型不同生成不同地址
    public function actionGenRecoveryAddress()
    {

    }

    //获得兑换比例
    public function actionGetRatio()
    {
        $id = $this->getContext()->getInput()->get('id');
        $sub = $this->getObject(SubchainModel::class)->getRedio($id);
        $this->data = ['ratio' => $sub, 'dgas' => getInstance()->config->get('dgas.ratio', 100000), 'dgame' => getInstance()->config->get('dgame.ratio', 100000)];
        $this->interOutputJson();
    }


    //计算手续费 0=>'dgame2subchain',1=>'dgas2subchain',2=>'dgame2dgas'
    public function actionGetScharge()
    {
        $para = json_decode($this->getContext()->getInput()->getRawContent(), true);
        $subRatio = $this->getRedio($para['appid']);//子链比例
        $dgas = getInstance()->config->get('dgas.ratio', 100000);
        if ($para['type'] == 0)
            $num = $subRatio * $para['num'];
        if ($para['type'] == 1)
            $num = $dgas * $para['num'];
        if ($para['type'] == 2)
            $num = ($para['num'] * $dgas) * $subRatio;
        $charge = ($para['appid'] + $para['fromaddr'] + $para['toaddr'] + $para['remark'] + $num + $para['num']) * getInstance()->config->get('ServerCharge', 0.0001);
        $this->data = $charge;
        $this->interOutputJson();
    }

    /**
     * 更新订单状态
     * type  操作类型 0=>'dgame2subchain',1=>'dgas2subchain',2=>'dgame2dgas'
     */
    public function actionUpOrderStatus()
    {
        $code = json_decode($this->getContext()->getInput()->getRawContent(), true);
        var_dump("code=======", $code);
//        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
//        $sign = $this->getContext()->getInput()->post('sign'); // key1
//        $code=json_decode($code,true);
        if (!(isset($code['order']) && isset($code['type']))) {
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->interOutputJson(403);
        }
        $tb = [0 => 'dgame2subchain',
            1 => 'dgas2subchain',
            2 => 'dgame2dgas'];

        //获得开发商密钥
        $info = $this->getObject(InfoModel::class);
        /* $aplication=$this->getObject(ApplicationModel::class);
         $rel=yield $info->getAppidByOrder($code['order'],$tb[$code['type']]);
         $data=yield $aplication->getDataByQuery('secretKey,White_list',['appid'=>$rel]);
         //校验签名
         $rel=$info::checkSign($sign,$code,$data[0]['secretKey']);
         if(!$rel){
           $this->resMsg='签名校验失败';
           $this->resCode=1;
           $this->interOutputJson(200);
           return;
         }

        //判断ip地址是否合法
         $ips=unserialize($data[0]['White_list']);
         $remote=$this->getContext()->getInput()->getRemoteAddr();
         if(!in_array($remote,$ips)){
             $this->resMsg='IP地址非法';
             $this->resCode=1;
             $this->interOutputJson(200);
             return;
         }*/
        //更新订单状态
//        $code['order']=$code['out_trade_no'];
        if ($code['type'] == 0) {
            $num = 0;
            foreach ($tb as $k => $v) {
                $rel = yield $info->UpOrderStatus($code['order'], $v);
                $num += $rel ? 1 : 0;
            }
            $rel = $num == count($tb);
        } else
            $rel = yield $info->UpOrderStatus($code['order'], $tb[$code['type']]);

        if (!$rel) {
            $this->resMsg = '订单状态更新失败';
            $this->resCode = 1;
        } else {
            $this->resMsg = '订单状态更新成功';
            $this->resCode = 0;
        }
        $this->interOutputJson(200);
    }


    /**
     * 将所有开发商手续费总额扣除，分成，并添加fee_log
     */
    public function actionCreateFee()
    {
        $aplication = $this->getObject(ApplicationModel::class);
        $rel = yield $aplication->getDataByQuery('appid,totalfee');
        $ratio = getInstance()->config->get('reward.ratio', 0.5);
        $fee_log = $this->getObject(FeeLogModel::class);
        //事务开始--
        // $trans = yield $this->masterDb->goBegin();
        if ($rel) {
            $num = 0;
            foreach ($rel as $k => $v) {
                if ($v['totalfee'] <= 0 && $v['totalfee'])
                    continue;
                $dgas = $v['totalfee'] * $ratio;
                $dgas_fee = $v['totalfee'] - $dgas;
                $data = ['appid' => $v['appid'],
                    'dgas' => $dgas,
                    'dgas_fee' => $dgas_fee,
                    'total_dgas' => $v['totalfee'],
                    'ratio' => $ratio,
                    'create_time' => time()];
                $fee = yield $fee_log->Insert($data);
                var_dump('fee_log', $fee);
                if (!$fee)
                    break;
                $up = yield $this->masterDb->update('application')->set('totalfee', $v['totalfee'], null, '-')
                    ->where('appid', $v['appid'])->go();//修改后批量 字段=字段+num 统一换回三层方法
                var_dump('fee_log_up', $up);
                if (!$up['result'])
                    break;
                $num += $fee ? 1 : 0;
            }
        }
        if ($num == count($rel) && $num != 0) {
            $this->resCode = 0;
            $this->resMsg = '操作成功';
        } else {
            // yield $this->masterDb->goRollback($trans);
            $this->resCode = 1;
            $this->resMsg = '操作失败';
        }
        $this->interOutputJson(200);
    }

    /**
     * 返回开发商dgame地址
     */
    public function actionDgameAddr()
    {
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1

        $key2 = yield $this->checkKeycode($sign);
        $iv = strrev($key2);
        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $para = json_decode($reqstr, true);
//        $para = json_decode($code, true);
        $this->log('application', $reqstr, '接收参数1');
        $this->log('application', $para, '接收参数');
        $id = $para['appid'];
        $fee_log = yield $this->getObject(ApplicationModel::class)
            ->getParaByAppid($id, 'dgameaddr,subchainaddr,debug_dgameaddr,debug_subchainaddr');
        $this->log('application', $fee_log, '返回数据');
        if ($fee_log) {
            $this->resCode = 0;
            $this->data = $fee_log[0];
        } else {
            $this->resCode = 1;
        }
//        $this->interOutputJson();
        $this->encryptOutput($key2, $iv, 200);


    }

}