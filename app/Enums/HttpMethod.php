<?php

namespace App\Enums;

enum HttpMethod: string
{
    case Get = 'get';
    case GetHtml = 'get_html';
    case Post = 'post';

    public function label(): string
    {
        return match ($this) {
            self::Get => 'GET – REST API',
            self::GetHtml => 'GET – HTML / LD+JSON',
            self::Post => 'POST – GraphQL',
        };
    }
}
