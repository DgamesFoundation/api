<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/25
 * Time: 12:08
 */

namespace App\Controllers\Wallet;

use App\Controllers\Common\BaseController;

class Dgame extends BaseController
{
    public function actionReq(){
        $poa_header = $this->getContext()->getInput()->getHeader('poa');

    }
    // 充值列表
    public function actionRechargeList(){
        $limit = 10;
        $page = $this->getContext()->getInput()->get('p') ?? 0;
        $offset = 0 * $limit;


        $result		=	yield	$this->masterDb->select( "*")->from('app_order')->limit($limit,$offset)->go();
        $this->data = $result['result'];
        //$aaa = $this->getMysqlPool('master');
        //var_dump($aaa);
        $this->interOutputJson(200);
    }
    // 交易列表
    public function actionTransList(){

    }
}