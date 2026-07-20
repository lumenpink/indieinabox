<?php
$options = [];
foreach ($argv as $arg) {
    if ($arg === '-s') $options['s'] = false;
    if ($arg === '-d') $options['d'] = false;
    if ($arg === '-f') $options['f'] = false;
    if ($arg === '-a') $options['a'] = false;
    if ($arg === '-M') $options['M'] = false;
    if ($arg === '-m') $options['m'] = false;
}
var_dump($options);
