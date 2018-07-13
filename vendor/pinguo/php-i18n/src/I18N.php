<?php

namespace PG\I18N;

/**
 * I18N provides features related with internationalization (I18N) and localization (L10N).
 *
 * @property MessageFormatter $messageFormatter The message formatter to be used to format message via ICU
 * message format. Note that the type of this property differs in getter and setter. See
 * [[getMessageFormatter()]] and [[setMessageFormatter()]] for details.
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
class I18N
{
    /** @var self */
    public static $i18n = null;

    /**
     * @var array
     */
    public $translations;

    /**
     * 实例化
     * @param array $config 配置，如：
     * [
     *     'class' => 'PhpMessageSource',
     *     'sourceLanguage' => 'en_us',
     *     'basePath' => '<DIR>/Languages', // 翻译配置文件路径
     *     'fileMap' => [
     *         'common' => 'common.php',
     *         'error' => 'error.php'
     *     ]
     * ]
     */
    public static function getInstance(array $config)
    {
        if (empty($config)) {
            throw new \Exception('i18n configuration can not be empty.');
        }
        if (self::$i18n === null) {
            self::$i18n = new self($config);
        }

        return self::$i18n;
    }

    /**
     * 释放实例，使得可以重新 new 出新对象
     */
    public static function releaseInstance()
    {
        self::$i18n = null;
    }

    /**
     * 多语言翻译，使用方法如：
     * 1) I18N::t('common', 'hot', [], 'zh_cn'); // 默认为 app.common
     * 2) I18N::t('app.common', 'hot', [], 'zh_cn'); // 结果同 1)
     * 3) I18N::t('msg.a', 'hello', ['{foo}' => 'bar', '{key}' => 'val'], 'ja_jp');
     * @param string $category
     * @param string $message
     * @param array $params
     * @param null | string $language
     * @return mixed
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        if (self::$i18n === null) {
            throw new \Exception('Has not instantiated i18n.');
        }
        if (strpos($category, '.') === false) {
            $category = 'app.' . $category;
        }
        return self::$i18n->translate($category, $message, $params, $language ?: 'en_us');
    }

    /**
     * Initializes the component by configuring the default message categories.
     * @param array $config 配置，如：
     * [
     *     'app' => [
     *         'class' => 'PhpMessageSource', <选填，默认为 PhpMessageSource>
     *         'sourceLanguage' => 'en_us', <必填>
     *         'basePath' => '<DIR>/Languages', // 翻译配置文件路径 <必填>
     *         'fileMap' => [ <必填>
     *             'common' => 'common.php',
     *             'error' => 'error.php'
     *         ]
     *     ],
     *     'other' => [
     *         'class' => 'PhpMessageSource', <选填>
     *         'sourceLanguage' => 'en_us', <必填>
     *         'basePath' => '<DIR>/other/Languages', // 翻译配置文件路径 <必填>
     *         'fileMap' => [ <必填>
     *             'a' => 'a.php',
     *             'b' => 'b.php'
     *         ]
     *     ]
     * ]
     */
    private function __construct(array $config)
    {
        foreach ($config as $key => $translation) {
            $this->translations[$key] = $translation;
        }
    }

    /**
     * @param string $category
     * @param string $message
     * @param array $params
     * @param string $language
     * @return string
     */
    public function translate($category, $message, $params, $language)
    {
        $messageSource = $this->getMessageSource($category);
        $translation = $messageSource->translate($category, $message, $language);
        if ($translation === false) {
            return $this->format($message, $params, $messageSource->sourceLanguage);
        } else {
            return $this->format($translation, $params, $language);
        }
    }

    /**
     * Formats a message using [[MessageFormatter]].
     *
     * @param string $message the message to be formatted.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the formatted message.
     */
    public function format($message, $params, $language)
    {
        $params = (array)$params;
        if ($params === []) {
            return $message;
        }

        if (preg_match('~{\s*[\d\w]+\s*,~u', $message)) {
            $formatter = $this->getMessageFormatter();
            $result = $formatter->format($message, $params, $language);
            if ($result === false) {
                // $errorMessage = $formatter->getErrorMessage();
                return $message;
            } else {
                return $result;
            }
        }

        $p = [];
        foreach ($params as $name => $value) {
            $p['{' . $name . '}'] = $value;
        }

        return strtr($message, $p);
    }

    /**
     * @var string|array|MessageFormatter
     */
    private $_messageFormatter;

    /**
     * Returns the message formatter instance.
     * @return MessageFormatter the message formatter to be used to format message via ICU message format.
     */
    public function getMessageFormatter()
    {
        if ($this->_messageFormatter === null) {
            $this->_messageFormatter = new MessageFormatter();
        } elseif (is_array($this->_messageFormatter) || is_string($this->_messageFormatter)) {
            $this->_messageFormatter = self::createObject($this->_messageFormatter);
        }

        return $this->_messageFormatter;
    }

    /**
     * @param string|array|MessageFormatter $value the message formatter to be used to format message via ICU message format.
     * Can be given as array or string configuration that will be given to [[self::createObject]] to create an instance
     * or a [[MessageFormatter]] instance.
     */
    public function setMessageFormatter($value)
    {
        $this->_messageFormatter = $value;
    }

    /**
     * Returns the message source for the given category.
     * @param string $category the category name, eg. app.errno
     * @return MessageSource the message source for the given category.
     * @throws InvalidConfigException if there is no message source available for the specified category.
     */
    public function getMessageSource($category)
    {
        $prefix = explode('.', $category)[0];
        if (isset($this->translations[$prefix])) {
            $source = $this->translations[$prefix];
            if ($source instanceof MessageSource) {
                return $source;
            } else {
                return $this->translations[$prefix] = static::createObject($source);
            }
        }

        throw new \Exception("Unable to locate message source for category '$category'.");
    }

    /**
     * 创建对象
     * @param mixed $type
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public static function createObject($type, array $params = [])
    {
        if (is_string($type)) {
            return new $type;
        } elseif (is_array($type)) {
            $class = '\\PG\\I18N\\' . ($type['class'] ?? 'PhpMessageSource');
            unset($type['class']);
            $clazz = new $class;
            foreach ($type as $prop => $val) {
                $clazz->$prop = $val;
            }
            return $clazz;
        }

        throw new \Exception('Unsupported configuration type: ' . gettype($type));
    }
}
