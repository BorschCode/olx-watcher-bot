<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Testing\FakeNutgram;

test('bot responds to start command', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);

    $bot->hearText('/start')
        ->reply()
        ->assertCalled('sendMessage');
});

test('bot responds to help command', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);

    $bot->hearText('/help')
        ->reply()
        ->assertCalled('sendMessage');
});

test('bot responds to saved command', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);

    $bot->hearText('/saved')
        ->reply()
        ->assertCalled('sendMessage');
});

test('bot responds to myid command', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);

    $bot->hearText('/myid')
        ->reply()
        ->assertCalled('sendMessage');
});

test('bot responds to save callback query', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);

    $bot->hearCallbackQueryData('save_12345')
        ->reply()
        ->assertCalled('answerCallbackQuery');
});
