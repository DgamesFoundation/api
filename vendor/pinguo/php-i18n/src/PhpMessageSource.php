<?php

namespace PG\I18N;

/**
 * PhpMessageSource represents a message source that stores translated messages in PHP scripts.
 *
 * PhpMessageSource uses PHP arrays to keep message translations.
 *
 * - Each PHP script contains one array which stores the message translations in one particular
 *   language and for a single message category;
 * - Each PHP script is saved as a file named as `[[basePath]]/LanguageID/CategoryName.php`;
 * - Within each PHP script, the message translations are returned as an array like the following:
 *
 * ~~~
 * return [
 *     'original message 1' => 'translated message 1',
 *     'original message 2' => 'translated message 2',
 * ];
 * ~~~
 *
 * You may use [[fileMap]] to customize the association between category names and the file names.
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
class PhpMessageSource extends MessageSource
{
    /**
     * @var string the base path for all translated messages. Defaults to '<DIR>/messages'.
     */
    public $basePath = '';
    /**
     * @var array mapping between message categories and the corresponding message file paths.
     * The file paths are relative to [[basePath]]. For example,
     *
     * ~~~
     * [
     *     'core' => 'core.php',
     *     'ext' => 'extensions.php',
     * ]
     * ~~~
     */
    public $fileMap;

    /**
     * 加载语言
     *
     * @param string $category 分类 eg app.errno
     * @param string $language 语言
     * @return array|mixed|null
     */
    protected function loadMessages($category, $language)
    {
        $messageFile = $this->getMessageFilePath($category, $language);
        $messages = $this->loadMessagesFromFile($messageFile);

        $fallbackLanguage = substr($language, 0, 2);
        if ($fallbackLanguage !== $language) {
            $fallbackMessageFile = $this->getMessageFilePath($category, $fallbackLanguage);
            $fallbackMessages = $this->loadMessagesFromFile($fallbackMessageFile);

            if ($messages === null && $fallbackMessages === null && $fallbackLanguage !== $this->sourceLanguage) {
                // ...
            } elseif (empty($messages)) {
                return $fallbackMessages;
            } elseif (! empty($fallbackMessages)) {
                foreach ($fallbackMessages as $key => $value) {
                    if (! empty($value) && empty($messages[$key])) {
                        $messages[$key] = $fallbackMessages[$key];
                    }
                }
            }
        } else {
            if ($messages === null) {
                // ...
            }
        }

        return (array)$messages;
    }

    /**
     * 获取文件路径
     *
     * @param string $category 分类
     * @param string $language 语言
     * @return string
     */
    protected function getMessageFilePath($category, $language)
    {
        $suffix = explode('.', $category)[1];
        $messageFile = $this->basePath . "/$language/";
        if (isset($this->fileMap[$suffix])) {
            $messageFile .= $this->fileMap[$suffix];
        } else {
            $messageFile .= str_replace('\\', '/', $suffix) . '.php';
        }

        return $messageFile;
    }

    /**
     * 从翻译配置文件中加载
     *
     * @param $messageFile
     * @return array|mixed|null
     */
    protected function loadMessagesFromFile($messageFile)
    {
        if (is_file($messageFile)) {
            $messages = include($messageFile);
            if (! is_array($messages)) {
                $messages = [];
            }

            return $messages;
        } else {
            return null;
        }
    }
}
