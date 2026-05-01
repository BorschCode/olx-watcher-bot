<?php

namespace App\Enums;

enum HttpMethod: string
{
    case Get = 'get';
    case Post = 'post';

    public function label(): string
    {
        return match ($this) {
            self::Get => 'GET – REST API',
            self::Post => 'POST – GraphQL',
        };
    }
}
