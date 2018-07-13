<?php
/**
 * 示例控制器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace App\Controllers;

use App\Controllers\Common\BaseController;
use PG\I18N\I18N;
use App\Models\Demo as DemoModel;

class Auth extends BaseController
{
    /**
     *
     */
    public function actionCode()
    {
        /**
         *
        $header = $this->getContext()->getInput()->getHeader('poa');
        $code = $this->getContext()->getInput()->get('code');
        var_dump("code1:", $header, "code", $code);
         //      $code = $this->getContext()->getInput()->post('code');
         //var_dump("code2:",$code);

        $res = ['code' => 0,
            'msg' => '',
            'status' => 'OK',
            'data' => null];
        $key2 = $this->genRandStr(16);
        $privatekeyFile = getInstance()->config->get("resource.rsa_privatekey", "");
        $rsaPrivateKey = file_get_contents($privatekeyFile);
        openssl_private_encrypt($key2, $encrypted, $rsaPrivateKey);
        $key2Encode = base64_encode($encrypted);

        var_dump("urldecode", urldecode($code));
        openssl_private_decrypt(base64_decode($code), $key1, $rsaPrivateKey);//私钥解密
        */
        // $res['data'] = $this->genKeycode();

        $this->data = $this->genKeycode();
        var_dump($this->data);
        $this->interOutputJson(200);
    }

    public function actionGetok()
    {
        $code = $this->getContext()->getInput()->post('code');
        $sign = $this->getContext()->getInput()->post('sign'); // key1
        $key2 = 'o2EqBFpHxljAInpo';
        $iv = strrev($key2);

        $reqstr = openssl_decrypt(base64_decode($code), 'aes-128-cbc', $key2, true, $iv);
        $reqobj = json_decode($reqstr);

        $res = ['code' => 0,
            'msg' => '',
            'status' => 'OK',
            'data' => null];

        $jsonstr = json_encode($res);
        $resstr = base64_encode(openssl_encrypt($jsonstr, 'aes-128-cbc', $key2, true, $iv));
        var_dump("getok", $resstr);
        $this->getContext()->getOutput()->output($resstr);
    }

    public function actionGetMockIds()
    {
        $ids = $this->getConfig()->get('params.mock_ids', []);
        $this->outputJson($ids);
    }

    public function actionGetMockFromModel()
    {
        /**
         * @var DemoModel $demoModel
         */
        $demoModel = $this->getObject(DemoModel::class, [1, 2]);
        $ids = $demoModel->getMockIds();
        $this->outputJson($ids);
    }

    public function actionTplView()
    {
        $data = [
            'title' => 'MSF Demo View',
            'friends' => [
                [
                    'name' => 'Rango',
                ],
                [
                    'name' => '鸟哥',
                ],
                [
                    'name' => '小马哥',
                ],
            ]
        ];
        $this->outputView($data);
    }

    public function actionSleep()
    {
        yield $this->getObject(\PG\MSF\Coroutine\Sleep::class)->goSleep(2000);
        $this->outputJson(['status' => 200, 'msg' => 'ok']);
    }

    public function actionI18n()
    {
        // 这个最好在业务控制器基类里的构造方法初始化，这里只作为演示方便
        I18N::getInstance(getInstance()->config->get('params.i18n', []));
        $sayHi = [
            'zh_cn' => I18N::t('demo.common', 'sayHi', ['name' => '品果微服务框架'], 'zh_CN'),
            'en_us' => I18N::t('demo.common', 'sayHi', ['name' => 'msf'], 'en_US'),
        ];

        $this->outputJson(['data' => $sayHi, 'status' => 200, 'msg' => I18N::t('demo.error', 200, [], 'zh_CN')]);
    }

    public function actionShellExec()
    {
        $result = yield $this->getObject(\PG\MSF\Coroutine\Shell::class)->goExec('ps aux | grep msf');
        $this->output($result);
    }
}
