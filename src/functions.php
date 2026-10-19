<?php
function env(string $name, mixed $default) {
    $var = getenv($name);
    if (!$var) {
        return $default;
    }
    return $var;
}