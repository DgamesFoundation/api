<?php
/**
 * Web Controller控制器基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace App\Console\Common;

use \PG\MSF\Console\Controller;


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

    public $masterDb;
    public $slaveDb;

    public function __construct(string $controllerName, string $methodName)
    {
        parent::__construct($controllerName, $methodName);

        $poa_flag = $this->getContext()->getInput()->getHeader('poa_flag');
        //var_dump($poa_flag);
        $this->masterDb = $this->getMysqlPool('master');
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
    protected function calc($m, $n, $x)
    {
        $errors = array(
            '被除数不能为零',
            '负数没有平方根'
        );

        switch ($x) {
            case 'add':
                $t = bcadd($m, $n);
                break;
            case 'sub':
                $t = bcsub($m, $n);
                break;
            case 'mul':
                $t = bcmul($m, $n);
                break;
            case 'div':
                if ($n != 0) {
                    $t = bcdiv($m, $n);
                } else {
                    return $errors[0];
                }
                break;
            case 'pow':
                $t = bcpow($m, $n);
                break;
            case 'mod':
                if ($n != 0) {
                    $t = bcmod($m, $n);
                } else {
                    return $errors[0];
                }
                break;
            case 'sqrt':
                if ($m >= 0) {
                    $t = bcsqrt($m);
                } else {
                    return $errors[1];
                }
                break;
        }
        $t = preg_replace("/\..*0+$/", '', $t);
        return $t;

    }

    public function getApiResult()
    {
        return ['code' => 0,
            'msg' => '',
            'status' => 'OK',
            'data' => null];
    }

    // 封装data为对象内部数据 JSON
    public function interOutputJson($status = 200)
    {
        $jsondata = ['code' => $this->resCode,
            'msg' => $this->resMsg,
            'status' => $this->resStauts,
            'data' => $this->data];
        $this->outputJson($jsondata, $status);
    }

    // 封装data为对象内部数据 Raw
    public function interOutput($status = 200)
    {
        $this->output($this->data, $status);
    }

    // 封装data为对象内部数据 视图
    public function interOutputView($view = null)
    {
        try {
            $this->outputView($this->data, $view);
        } catch (\Throwable $e) {
        }
    }
}
