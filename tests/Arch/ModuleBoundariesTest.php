<?php

use Symfony\Component\Finder\Finder;

/**
 * Modules talk to each other only through Contracts\ and Events\. Shared is open
 * to everyone, and tests may reach anywhere. Catches imports and fully qualified
 * names alike.
 */
it('keeps modules behind their contracts and events', function () {
    $violations = [];
    $files = Finder::create()->files()->name('*.php')->in(base_path('modules'))->notPath('#(^|/)tests/#');

    foreach ($files as $file) {
        $owner = explode('/', str_replace('\\', '/', $file->getRelativePathname()))[0];
        preg_match_all('/Modules\\\\(\w+)\\\\(\w+)/', $file->getContents(), $matches, PREG_SET_ORDER);

        foreach ($matches as [$reference, $module, $area]) {
            if ($module === $owner || $module === 'Shared' || in_array($area, ['Contracts', 'Events'], true)) {
                continue;
            }
            $violations[] = "{$file->getRelativePathname()} uses {$reference}";
        }
    }

    expect($violations)->toBe([]);
});

arch('php hygiene')->preset()->php();
arch('security hygiene')->preset()->security();
