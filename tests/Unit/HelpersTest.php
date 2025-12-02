<?php

declare(strict_types=1);

test('is_windows helper exists', function () {
    expect(function_exists('is_windows'))->toBeTrue();
});

test('is_mac helper exists', function () {
    expect(function_exists('is_mac'))->toBeTrue();
});

test('is_linux helper exists', function () {
    expect(function_exists('is_linux'))->toBeTrue();
});

test('is_windows returns boolean', function () {
    expect(is_windows())->toBeBool();
});

test('is_mac returns boolean', function () {
    expect(is_mac())->toBeBool();
});

test('is_linux returns boolean', function () {
    expect(is_linux())->toBeBool();
});

test('only one platform helper returns true', function () {
    $count = 0;
    if (is_windows()) $count++;
    if (is_mac()) $count++;
    if (is_linux()) $count++;

    expect($count)->toBe(1);
});
