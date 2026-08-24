<?php

use Symfony\Component\Yaml\Yaml;

test('raspberry compose bind-mounts storage onto the host workspace', function () {
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/compose.raspberry.yml');

    expect($compose['services']['app']['volumes'])->toEqual([
        './storage/logs:/var/www/html/storage/logs',
        './storage/app:/var/www/html/storage/app',
    ])->and($compose)->not->toHaveKey('volumes');
});

test('raspberry deploy keeps host storage across workspace checkout', function () {
    $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy-raspi.yml');

    expect($workflow)
        ->toContain('! -name "storage"')
        ->toContain('clean: false')
        ->toContain('${PROJECT}_storage_logs')
        ->toContain('storage/logs');
});
