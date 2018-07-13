<?php

namespace PG\I18N;

/**
 * MessageSource is the base class for message translation repository classes.
 *
 * A message source stores message translations in some persistent storage.
 *
 * Child classes should override [[loadMessages()]] to provide translated messages.
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
class MessageSource
{
    /**
     * @var boolean whether to force message translation when the source and target languages are the same.
     * Defaults to false, meaning translation is only performed when source and target languages are different.
     */
    public $forceTranslation = false;
    /**
     * @var string the language that the original messages are in. If not set, it will use the value of
     */
    public $sourceLanguage = 'en-us';

    private $_messages = [];

    /**
     * 加载语言
     *
     * @param string $category 分类
     * @param string $language 语言
     * @return array
     */
    protected function loadMessages($category, $language)
    {
        return [];
    }

    /**
     * 执行翻译
     *
     * @param string $category 分类
     * @param string $message 要翻译的信息
     * @param string $language 要翻译成的语言
     * @return bool|string
     */
    public function translate($category, $message, $language)
    {
        if ($this->forceTranslation || $language !== $this->sourceLanguage) {
            return $this->translateMessage($category, $message, $language);
        } else {
            return false;
        }
    }

    /**
     * 执行翻译
     *
     * @param string $category 分类
     * @param string $message 要翻译的信息
     * @param string $language 要翻译成的语言
     * @return bool|string
     */
    protected function translateMessage($category, $message, $language)
    {
        $cates = explode('.', $category);
        $key = $cates[0] . '/' . $language . '/' . $cates[1]; // eg: app/en_us/errno
        if (!isset($this->_messages[$key])) {
            $this->_messages[$key] = $this->loadMessages($category, $language);
        }
        if (isset($this->_messages[$key][$message]) && $this->_messages[$key][$message] !== '') {
            return $this->_messages[$key][$message];
        } else {
            // ...
        }

        return $this->_messages[$key][$message] = false;
    }
}
