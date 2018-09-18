<?php

namespace App\Controllers\Common;

use \PG\MSF\Controllers\Controller;
use \PG\MSF\Client\Http\Client;
use App\Models\Wallet\ApplicationModel;

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
    const DGAS_REWARD = 0;//Rewards
    const DGAS_CONSUME = 1;//consume
    const DGAS_RECHARGE = 2;//recharge
    const DGAS_CONSUME_SCHARGE = 3;//exchange charge
    const DGAS_RECHARGE_SCHARGE = 2;//recharge charge
    const DGAS_NORMAL = 0;//normal operation
    const DGAS_S2S = 4;//

    const SUB_DGAME = 0;
    const SUB_DGAS = 1;
    const SUB_DGAME2SUB = 2;
    const TRANSFER = 3;//transfer


    const  SUCCESS = 1;
    const  FAIL = 0;

    public $masterDb;
    public $slaveDb;

    public function __construct(string $controllerName, string $methodName)
    {
        parent::__construct($controllerName, $methodName);

        $this->masterDb = $this->getMysqlPool('master');
        $para = $this->getContext()->getInput()->getAllPostGet();
        yield $this->BeforAction($para);
    }

    public function BeforAction($para)
    {
        if (isset($para['code'])) {
            $para = json_decode($this->getContext()->getInput()->getRawContent(), true);
        }
        $method = $this->getContext()->getActionName();
        if (isset($para['appid'])) {
            $rel = yield $this->getObject(ApplicationModel::class)->getParaByAppid($para['appid'], 'is_disable');
            if ($rel[0]['is_disable'] == 1 && !in_array($method, $this->exceptMethod)) {
                $this->resCode = 1;
                $this->resMsg = 'The application is disabled';
                $this->interOutputJson(200);
                return;
            }

        }

    }

    /**
     *
     * Calculation of large number
     *
     * @param $m
     * @param $n
     * @param $x
     * @return mixed|string
     */
    protected function calc($m, $n, $x, $precisions = 8)
    {
        $errors = array(
            'The divisor cannot be zero',
            'Negative Numbers have no square root'
        );
        $m = sprintf("%." . $precisions . "f", $m);
        $n = sprintf("%." . $precisions . "f", $n);
        bcscale($precisions);
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
//        $t=preg_replace("/\..*0+$/",'',$t);
        $t = rtrim(rtrim($t, '0'), '.');
        return $t;

    }

    public function getApiResult()
    {
        return ['code' => 0,
            'msg' => '',
            'status' => 'OK',
            'data' => null];
    }

    // Encapsulate data as object internal data JSON
    public function interOutputJson($status = 200)
    {
        $jsondata = ['code' => $this->resCode,
            'msg' => $this->resMsg,
            'status' => $this->resStauts,
            'data' => $this->data];
        $this->outputJson($jsondata, $status);
    }

    // Encapsulate data as object internal data Raw
    public function interOutput($status = 200)
    {
        $this->output($this->data, $status);
    }

    // Encapsulate data as object internal data View
    public function interOutputView($view = null)
    {
        try {
            $this->outputView($this->data, $view);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @brief Check whether         the function must pass parameters exist
     * @param $params array         Parameters to check
     * @param array $mod array      The index array     The field to check
     * @param array $fields array   The index array   Additional fields to check for parameters
     * @return bool
     */
    public function checkParamsExists($params, $mod = [], $fields = [])
    {
        if (empty($params)) {
            return false;
        }
        $params = is_array($params) ? $params : [$params];

        if ($fields) {
            $fields = array_flip($fields);
            $params = array_merge($params, $fields);
        }

        foreach ($mod as $mod_key => $mod_value) {
            if (!array_key_exists($mod_value, $params)) {
                return false;
            }
        }
        return true;

    }

    /**
     * create  uuid
     * @return string
     */
    public function guid()
    {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);// "}"
            return $uuid;
        }
    }

    /**
     * Get the precision length based on the appid
     * @param $appid
     * @return int
     */
    public function getPrecisionsLen($appid)
    {
        $pre = yield $this->getObject(ApplicationModel::class)->getParaByAppid($appid, 'precisions');
        return strlen($pre[0]['precisions']);
    }

    /**
     * log
     * @param $filename
     * @param $data
     * @param $description
     */
    public function log($filename, $data, $description)
    {
        $content = [PHP_EOL . $description . '================' . date("Y-m-d H:i:s", time()) . PHP_EOL . var_export($data, TRUE) . PHP_EOL];
        file_put_contents('/tmp/' . $filename, $content, FILE_APPEND);
    }

}