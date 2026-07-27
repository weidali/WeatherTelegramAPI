<?php

namespace App\Support\Concerns;

trait PadsStrings
{
    /**
     * Дополняет строку пробелами справа до заданной длины с учётом
     * многобайтных символов (кириллица, °, эмодзи).
     *
     * Аналог mb_str_pad(), которого нет в PHP до версии 8.3.
     *
     * @param  string  $str     Исходная строка
     * @param  int     $length  Желаемая длина в символах
     * @param  string  $pad     Символ-заполнитель (по умолчанию пробел)
     * @return string
     */
    protected function mbStrPad(string $str, int $length, string $pad = ' '): string
    {
        $diff = $length - mb_strlen($str);

        return $diff > 0 ? $str . str_repeat($pad, $diff) : $str;
    }
}