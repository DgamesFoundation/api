<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/25
 * Time: 12:08
 */

namespace App\Controllers\Account;

use App\Controllers\Common\BaseController;

class Info extends BaseController
{
    /**
     * 获取指定货币余额
     *
     */
    public function actionGetBalance(){
        $ctype = $this->getContext()->getInput()->get('ctype');
        $addr = $this->getContext()->getInput()->get('addr');
        switch ($ctype){
            case 'dgame':
                $balance = 100000;
                // Dgame 请求资源接口
                break;
            case 'dgas':
                $balance = 100000;
                // Dgas 请求资源接口
                break;
            default:
                $this->resCode = 1;
                $this->resMsg = "参数异常";
                $this->interOutputJson(403);
        }
        $this->data = ['balance'=>$balance];
        $this->interOutputJson(200);
    }
}