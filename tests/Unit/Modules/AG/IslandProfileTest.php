<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Evolution\IslandModel\IslandProfile;

it('has three cases: Conservative, Balanced and Exploratory', function (): void {
    $cases = IslandProfile::cases();

    expect($cases)->toHaveCount(3)
        ->and(array_column($cases, 'value'))->toContain('conservative', 'balanced', 'exploratory');
});

it('returns distinct graspAlpha ranges for each profile', function (): void {
    expect(IslandProfile::Conservative->graspAlphaMin())->toBeLessThan(IslandProfile::Balanced->graspAlphaMin())
        ->and(IslandProfile::Balanced->graspAlphaMin())->toBeLessThan(IslandProfile::Exploratory->graspAlphaMin());

    expect(IslandProfile::Conservative->graspAlphaMax())->toBeLessThan(IslandProfile::Exploratory->graspAlphaMax());
});

it('ensures alpha min is always less than alpha max for every profile', function (): void {
    foreach (IslandProfile::cases() as $profile) {
        expect($profile->graspAlphaMin())->toBeLessThan($profile->graspAlphaMax());
    }
});

it('returns strictly increasing mutationBaseRate from Conservative to Exploratory', function (): void {
    expect(IslandProfile::Conservative->mutationBaseRate())
        ->toBeLessThan(IslandProfile::Balanced->mutationBaseRate())
        ->toBeLessThan(IslandProfile::Exploratory->mutationBaseRate());
});

it('returns strictly increasing mutationMaxRate from Conservative to Exploratory', function (): void {
    expect(IslandProfile::Conservative->mutationMaxRate())
        ->toBeLessThan(IslandProfile::Balanced->mutationMaxRate())
        ->toBeLessThan(IslandProfile::Exploratory->mutationMaxRate());
});

it('returns a non-empty label for every profile', function (): void {
    foreach (IslandProfile::cases() as $profile) {
        expect($profile->label())->toBeString()->not->toBeEmpty();
    }
});

it('assigns island 0 to Conservative, island 1 to Exploratory and default to Balanced', function (): void {
    $resolve = fn (int $i): IslandProfile => match ($i) {
        0 => IslandProfile::Conservative,
        1 => IslandProfile::Exploratory,
        default => IslandProfile::Balanced,
    };

    expect($resolve(0))->toBe(IslandProfile::Conservative)
        ->and($resolve(1))->toBe(IslandProfile::Exploratory)
        ->and($resolve(2))->toBe(IslandProfile::Balanced)
        ->and($resolve(5))->toBe(IslandProfile::Balanced);
});
