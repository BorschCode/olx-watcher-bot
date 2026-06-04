<?php

namespace App\Enums;

enum WatcherSource: string
{
    case Olx = 'olx';
    case AutoRia = 'auto_ria';

    public function label(): string
    {
        return match ($this) {
            self::Olx => 'OLX',
            self::AutoRia => 'Auto.ria',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Olx => 'primary',
            self::AutoRia => 'danger',
        };
    }
}
