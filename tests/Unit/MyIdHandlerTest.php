<?php

use SergiX44\Nutgram\Telegram\Properties\ChatType;

test('chat type enum must use value for string messages', function () {
    expect(ChatType::PRIVATE->value)->toBe('private');

    expect(fn () => (string) (ChatType::PRIVATE))
        ->toThrow(Error::class);
});
