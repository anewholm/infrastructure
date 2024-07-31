<?php namespace Acorn;

trait PostGreSQLFieldTypeUtilities {
    static protected function dec2binArray(int $dec)
    {
        $result = array();
        $bin    = decbin($dec);
        $len    = strlen($bin);
        for ($i = 0; $i < $len; $i++)
        {
            if ($bin[$len -$i-1] == '1') array_push($result, 2**$i);
        }
        return $result;
    }

    static protected function integerArrayToPHPArray(?string $integerArray, ?bool $forceArray = TRUE)
    {
        // {2,3,4}
        return ($integerArray
            ? array_map('intval', explode(',', preg_replace('/^{|}$/', '', $integerArray)))
            : ($forceArray ? array() : NULL)
        );
    }

    static protected function phpArrayToIntegerArray(array $phpArray, ?bool $forceArray = TRUE)
    {
        // {2,3,4}
        return ($phpArray
            ? '{' . implode(',', $phpArray) . '}'
            : ($forceArray ? "{}" : NULL)
        );
    }
}

