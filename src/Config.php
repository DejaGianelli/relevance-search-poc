<?php

namespace App;

class Config
{
    private static array $flattened;

    private function __construct(array $map)
    {
        foreach ($map as $k => $node) {
            $path = $k;
            $result = $this->path($node, $path);
            $this::$flattened[$result['path']] = $result['value'];
        }
    }

    public static function init(array $map) {
        new Config($map);
    }

    public static function flatConfig() {
        return self::$flattened;
    }

    private function path(mixed $node, string $path)
    {
        if (!is_array($node)) {
            return [
                'path' => $path,
                'value' => $node
            ];
        }
        foreach ($node as $k => $v) {
            $path = $path . '.' . $k;
            return $this->path($v, $path);
        }
    }
}
