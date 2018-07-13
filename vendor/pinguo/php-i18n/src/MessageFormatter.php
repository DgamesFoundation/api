<?php

namespace PG\I18N;

/**
 * 翻译信息格式化
 *
 * Class MessageFormatter
 * @package PG\I18N
 */
class MessageFormatter
{
    private $_errorCode = 0;
    private $_errorMessage = '';

    /**
     * Get the error code from the last operation
     * @link http://php.net/manual/en/messageformatter.geterrorcode.php
     * @return string Code of the last error.
     */
    public function getErrorCode()
    {
        return $this->_errorCode;
    }

    /**
     * Get the error text from the last operation
     * @link http://php.net/manual/en/messageformatter.geterrormessage.php
     * @return string Description of the last error.
     */
    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    /**
     * 格式化
     *
     * @param string $pattern 规则模式
     * @param array $params 参数
     * @param string $language 语言
     * @return bool|string
     */
    public function format($pattern, $params, $language)
    {
        $this->_errorCode = 0;
        $this->_errorMessage = '';

        if ($params === []) {
            return $pattern;
        }

        if (! class_exists('MessageFormatter', false)) {
            return $this->fallbackFormat($pattern, $params, $language);
        }

        // replace named arguments (https://github.com/yiisoft/yii2/issues/9678)
        $pattern = $this->replaceNamedArguments($pattern, $params, $newParams);
        $params = $newParams;

        $formatter = new \MessageFormatter($language, $pattern);
        if ($formatter === null) {
            $this->_errorCode = intl_get_error_code();
            $this->_errorMessage = 'Message pattern is invalid: ' . intl_get_error_message();

            return false;
        }
        $result = $formatter->format($params);
        if ($result === false) {
            $this->_errorCode = $formatter->getErrorCode();
            $this->_errorMessage = $formatter->getErrorMessage();

            return false;
        } else {
            return $result;
        }
    }

    /**
     * 解析
     *
     * @param string $pattern
     * @param string $message
     * @param string $language
     * @return array|bool
     * @throws \Exception
     */
    public function parse($pattern, $message, $language)
    {
        $this->_errorCode = 0;
        $this->_errorMessage = '';

        if (! class_exists('MessageFormatter', false)) {
            throw new \Exception('You have to install PHP intl extension to use this feature.');
        }

        // replace named arguments
        if (($tokens = self::tokenizePattern($pattern)) === false) {
            $this->_errorCode = -1;
            $this->_errorMessage = 'Message pattern is invalid.';

            return false;
        }
        $map = [];
        foreach ($tokens as $i => $token) {
            if (is_array($token)) {
                $param = trim($token[0]);
                if (! isset($map[$param])) {
                    $map[$param] = count($map);
                }
                $token[0] = $map[$param];
                $tokens[$i] = '{' . implode(',', $token) . '}';
            }
        }
        $pattern = implode('', $tokens);
        $map = array_flip($map);

        $formatter = new \MessageFormatter($language, $pattern);
        if ($formatter === null) {
            $this->_errorCode = -1;
            $this->_errorMessage = 'Message pattern is invalid.';

            return false;
        }
        $result = $formatter->parse($message);
        if ($result === false) {
            $this->_errorCode = $formatter->getErrorCode();
            $this->_errorMessage = $formatter->getErrorMessage();

            return false;
        } else {
            $values = [];
            foreach ($result as $key => $value) {
                $values[$map[$key]] = $value;
            }

            return $values;
        }
    }

    /**
     * 替换字符串中的参数
     *
     * @param string $pattern
     * @param array $givenParams
     * @param array $resultingParams
     * @param array $map
     * @return bool|string
     */
    private function replaceNamedArguments($pattern, $givenParams, &$resultingParams, &$map = [])
    {
        if (($tokens = self::tokenizePattern($pattern)) === false) {
            return false;
        }
        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                continue;
            }
            $param = trim($token[0]);
            if (isset($givenParams[$param])) {
                // if param is given, replace it with a number
                if (! isset($map[$param])) {
                    $map[$param] = count($map);
                    // make sure only used params are passed to format method
                    $resultingParams[$map[$param]] = $givenParams[$param];
                }
                $token[0] = $map[$param];
                $quote = '';
            } else {
                // quote unused token
                $quote = "'";
            }
            $type = isset($token[1]) ? trim($token[1]) : 'none';
            // replace plural and select format recursively
            if ($type === 'plural' || $type === 'select') {
                if (! isset($token[2])) {
                    return false;
                }
                if (($subtokens = self::tokenizePattern($token[2])) === false) {
                    return false;
                }
                $c = count($subtokens);
                for ($k = 0; $k + 1 < $c; $k++) {
                    if (is_array($subtokens[$k]) || ! is_array($subtokens[++$k])) {
                        return false;
                    }
                    $subpattern = $this->replaceNamedArguments(implode(',', $subtokens[$k]), $givenParams,
                        $resultingParams, $map);
                    $subtokens[$k] = $quote . '{' . $quote . $subpattern . $quote . '}' . $quote;
                }
                $token[2] = implode('', $subtokens);
            }
            $tokens[$i] = $quote . '{' . $quote . implode(',', $token) . $quote . '}' . $quote;
        }

        return implode('', $tokens);
    }

    /**
     * Fallback implementation for MessageFormatter::formatMessage
     * @param string $pattern The pattern string to insert things into.
     * @param array $args The array of values to insert into the format string
     * @param string $locale The locale to use for formatting locale-dependent parts
     * @return string|boolean The formatted pattern string or `FALSE` if an error occurred
     */
    protected function fallbackFormat($pattern, $args, $locale)
    {
        if (($tokens = self::tokenizePattern($pattern)) === false) {
            $this->_errorCode = -1;
            $this->_errorMessage = 'Message pattern is invalid.';

            return false;
        }
        foreach ($tokens as $i => $token) {
            if (is_array($token)) {
                if (($tokens[$i] = $this->parseToken($token, $args, $locale)) === false) {
                    $this->_errorCode = -1;
                    $this->_errorMessage = 'Message pattern is invalid.';

                    return false;
                }
            }
        }

        return implode('', $tokens);
    }

    /**
     * Tokenizes a pattern by separating normal text from replaceable patterns
     * @param string $pattern patter to tokenize
     * @return array|boolean array of tokens or false on failure
     */
    private static function tokenizePattern($pattern)
    {
        $charset = 'UTF-8';
        $depth = 1;
        if (($start = $pos = mb_strpos($pattern, '{', 0, $charset)) === false) {
            return [$pattern];
        }
        $tokens = [mb_substr($pattern, 0, $pos, $charset)];
        while (true) {
            $open = mb_strpos($pattern, '{', $pos + 1, $charset);
            $close = mb_strpos($pattern, '}', $pos + 1, $charset);
            if ($open === false && $close === false) {
                break;
            }
            if ($open === false) {
                $open = mb_strlen($pattern, $charset);
            }
            if ($close > $open) {
                $depth++;
                $pos = $open;
            } else {
                $depth--;
                $pos = $close;
            }
            if ($depth === 0) {
                $tokens[] = explode(',', mb_substr($pattern, $start + 1, $pos - $start - 1, $charset), 3);
                $start = $pos + 1;
                $tokens[] = mb_substr($pattern, $start, $open - $start, $charset);
                $start = $open;
            }
        }
        if ($depth !== 0) {
            return false;
        }

        return $tokens;
    }

    /**
     * Parses a token
     * @param array $token the token to parse
     * @param array $args arguments to replace
     * @param string $locale the locale
     * @return bool|string parsed token or false on failure
     * @throws \Exception when unsupported formatting is used.
     */
    private function parseToken($token, $args, $locale)
    {
        // parsing pattern based on ICU grammar:
        // http://icu-project.org/apiref/icu4c/classMessageFormat.html#details
        $charset = 'UTF-8';
        $param = trim($token[0]);
        if (isset($args[$param])) {
            $arg = $args[$param];
        } else {
            return '{' . implode(',', $token) . '}';
        }
        $type = isset($token[1]) ? trim($token[1]) : 'none';
        switch ($type) {
            case 'date':
            case 'time':
            case 'spellout':
            case 'ordinal':
            case 'duration':
            case 'choice':
            case 'selectordinal':
                throw new \Exception("Message format '$type' is not supported. You have to install PHP intl extension to use this feature.");
            case 'number':
                if (is_int($arg) && (! isset($token[2]) || trim($token[2]) === 'integer')) {
                    return $arg;
                }
                throw new \Exception("Message format 'number' is only supported for integer values. You have to install PHP intl extension to use this feature.");
            case 'none':
                return $arg;
            case 'select':
                /** http://icu-project.org/apiref/icu4c/classicu_1_1SelectFormat.html
                 *  selectStyle = (selector '{' message '}')+
                 */
                if (! isset($token[2])) {
                    return false;
                }
                $select = self::tokenizePattern($token[2]);
                $c = count($select);
                $message = false;
                for ($i = 0; $i + 1 < $c; $i++) {
                    if (is_array($select[$i]) || ! is_array($select[$i + 1])) {
                        return false;
                    }
                    $selector = trim($select[$i++]);
                    if ($message === false && $selector === 'other' || $selector == $arg) {
                        $message = implode(',', $select[$i]);
                    }
                }
                if ($message !== false) {
                    return $this->fallbackFormat($message, $args, $locale);
                }
                break;
            case 'plural':
                /* http://icu-project.org/apiref/icu4c/classicu_1_1PluralFormat.html
                pluralStyle = [offsetValue] (selector '{' message '}')+
                offsetValue = "offset:" number
                selector = explicitValue | keyword
                explicitValue = '=' number  // adjacent, no white space in between
                keyword = [^[[:Pattern_Syntax:][:Pattern_White_Space:]]]+
                message: see MessageFormat
                */
                if (! isset($token[2])) {
                    return false;
                }
                $plural = self::tokenizePattern($token[2]);
                $c = count($plural);
                $message = false;
                $offset = 0;
                for ($i = 0; $i + 1 < $c; $i++) {
                    if (is_array($plural[$i]) || ! is_array($plural[$i + 1])) {
                        return false;
                    }
                    $selector = trim($plural[$i++]);

                    if ($i == 1 && strncmp($selector, 'offset:', 7) === 0) {
                        $offset = (int)trim(mb_substr($selector, 7,
                            ($pos = mb_strpos(str_replace(["\n", "\r", "\t"], ' ', $selector), ' ', 7, $charset)) - 7,
                            $charset));
                        $selector = trim(mb_substr($selector, $pos + 1, null, $charset));
                    }
                    if ($message === false && $selector === 'other' ||
                        $selector[0] === '=' && (int)mb_substr($selector, 1, null, $charset) === $arg ||
                        $selector === 'one' && $arg - $offset == 1
                    ) {
                        $message = implode(',', str_replace('#', $arg - $offset, $plural[$i]));
                    }
                }
                if ($message !== false) {
                    return $this->fallbackFormat($message, $args, $locale);
                }
                break;
        }

        return false;
    }
}
