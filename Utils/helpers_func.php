<?php

function array_disp(string|array|object ...$data)
{
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
}
