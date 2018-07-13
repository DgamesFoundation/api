<?php
/**
 * Created by PhpStorm.
 * User: LSS
 * Date: 2018/6/25
 * Time: 12:08
 */

namespace App\Controllers\Sdk;

use App\Controllers\Common\BaseController;

class App extends BaseController
{
    public function actionReq(){
        $poa_header = $this->getContext()->getInput()->getHeader('poa');

    }

    /**
     *
     * Poa库的版本信息获取接口
     *
     */
    public function actionVersion(){
        $platMap = ['armeabi'=>0,
            'armeabi-v7a'=>1,
            'x86'=>2];
        $platKey = $this->getContext()->getInput()->get("plat");
        $plat = $platMap[$platKey];
        $poaRes = yield $this->masterDb->select("*")->from("sdk_version")
            ->where('plat',$plat)
            ->orderBy("vercode","DESC")
            ->limit(1)->go();

        $res = $poaRes['result'][0];
        $this->data = ['versionName'=>$res['vername'],
            'versionCode'=>$res['vercode'],
            'hash'=>$res['hash'],
            'url'=>$res['url']];
        $this->interOutputJson(200);
    }
}