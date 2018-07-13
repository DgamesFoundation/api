<?php
/**
 * 处理请求的上下文接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\Context;

use PG\Log\PGLog;

abstract class AbstractContext implements \ArrayAccess
{
    /**
     * @var
     */
    public $useCount;

    /**
     * @var
     */
    public $genTime;

    /**
     * @var string
     */
    protected $logId;

    /**
     * @var PGLog
     */
    protected $PGLog;

    public function __sleep()
    {
        return ['logId'];
    }

    /**
     * 设置日志对象
     *
     * @param $log
     * @return $this
     */
    public function setLog($log)
    {
        $this->PGLog = $log;
        return $this;
    }

    /**
     * 获取日志对象
     *
     * @return PGLog
     */
    public function getLog()
    {
        return $this->PGLog;
    }


    /**
     * 设置日志ID
     *
     * @param $logId
     * @return $this
     */
    public function setLogId($logId)
    {
        $this->logId = $logId;
        return $this;
    }

    /**
     * 获取日志ID
     *
     * @return string
     */
    public function getLogId()
    {
        return $this->logId;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * @param mixed $offset
     * @return null
     */
    public function offsetGet($offset)
    {
        return isset($this->{$offset}) ? $this->{$offset} : null;
    }

    abstract public function destroy();
}
