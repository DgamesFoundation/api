<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/11 0011
 * Time: 下午 12:25
 */

namespace App\Controllers\Wallet;

use App\Controllers\Common\BaseController;
use App\Models\Wallet\InfoModel;
use App\Models\Wallet\ApplicationModel;
class Callback extends BaseController
{
    private $tb=[0=>'dgame2subchain',
        1=>'dgas2subchain',
        2=>'dgame2dgas'];
    /**
     * 更新订单状态
     * type  操作类型 0=>'dgame2subchain',1=>'dgas2subchain',2=>'dgame2dgas'
     */
    public function actionUpOrderStatus(){
//        $code=json_decode($this->getContext()->getInput()->getRawContent(),true);
        $code = $this->getContext()->getInput()->post('code'); // Encrypt userinfo
        $sign = $this->getContext()->getInput()->post('sign'); // key1
        $code=json_decode($code,true);

        if(!(isset($code['dgas']) && isset($code['amount']) && isset($code['faddress']) &&  isset($code['taddress'])  && isset($code['out_trade_no']) && isset($code['type']))){
            $this->resCode = 1;
            $this->resMsg = "参数异常";
            $this->interOutputJson(403);
        }
        //$code['order']=$code['out_trade_no'];
        //获得开发商密钥
        $info=$this->getObject(InfoModel::class);
         $aplication=$this->getObject(ApplicationModel::class);
         $rel=yield $info->getAppidByOrder($code['out_trade_no'],$this->tb[$code['type']]);
         $data=yield $aplication->getDataByQuery('secretKey,account_len,White_list',['appid'=>$rel]);
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
             $this->resCode=3;
             $this->interOutputJson(200);
             return;
         }
        //验证订单是否一致
        $check=yield $this->CheckOrder($code,$data);
         var_dump('check',$check);
        if(!$check)
        {
            $this->resCode = 2;
            $this->resMsg = "订单异常";
            $this->interOutputJson(200);
            return;
        }
        //更新订单状态
        $rel=yield $this->upDataByOrder($code);
        var_dump('rel',$rel);
        if(!$rel){
            $this->resMsg='订单状态更新失败';
            $this->resCode=1;
        }else{
            $this->resMsg='订单状态更新成功';
            $this->resCode=0;
        }
        $this->interOutputJson(200);
    }

    /**验证订单是否一致
     * @param $code
     * @param $data
     * @return bool
     */
    public function CheckOrder($code,$data){
        $para=$code['type']==1 ?
            'order_sn,fromaddr,toaddr,dgas,ratio':
            'order_sn,fromaddr,address,dgas,ratio';
        $rel=yield $this->getObject(InfoModel::class)
            ->getDataByQuery($para,$this->tb[$code['type']],['order_sn'=>$code['out_trade_no']]);
        $address=$code['type']==1 ?
            $rel[0]['toaddr'] :
            $rel[0]['address'];
        $mul_len=getInstance()->config->get('dgas.mul_num',1000000000000000000);
        $dgas=$this->calc($rel[0]['dgas'],$mul_len,'mul');
        $amount=$this->calc($rel[0]['dgas']*$rel[0]['ratio'],$data[0]['account_len'],'mul');
        var_dump($code['dgas'],$dgas,$code['dgas']==$dgas);
        var_dump($code['amount'],$amount,$code['amount']==$amount);
        return ($code['faddress'] == $rel[0]['fromaddr'] && $code['dgas']==$dgas && $code['taddress']==$address && $code['amount']==$amount)  ;
    }
    /**
     * 更新订单状态
     * @param $code
     * @param $tb
     * @return bool
     */
    public function upDataByOrder($code){
        $info=$this->getObject(InfoModel::class);
        var_dump($code['out_trade_no'],$this->tb[$code['type']]);
        if($code['type']==0){
            $num=0;
            foreach ($this->tb as $k=>$v){
                $rel=yield $info->UpOrderStatus($code['out_trade_no'],$v);
                $num+=$rel ? 1 : 0;
            }
            $rel=$num==count($this->tb);
        }else
            $rel=yield $info->UpOrderStatus($code['out_trade_no'],$this->tb[$code['type']]);
        var_dump($rel);
        return $rel;
    }
}
