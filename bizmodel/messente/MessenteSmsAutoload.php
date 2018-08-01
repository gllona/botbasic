<?php

function MessenteSmsAutoload($classname)
{
    $filename = dirname(__FILE__).DIRECTORY_SEPARATOR.'class.'.strtolower($classname).'.php';
    if (is_readable($filename)) {
        require $filename;
    }
}

spl_autoload_register('MessenteSmsAutoload', true, true);
