<?php

declare(strict_types=1);

/*
 * URAVEX test runner (no dependencies):
 *   php tests/run.php            all suites
 *   php tests/run.php unit       unit only (no database writes)
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/Fixtures.php';

$suites = ['unit' => 'UnitTest.php', 'integration' => 'IntegrationTest.php', 'http' => 'HttpTest.php'];
$only = $argv[1] ?? null;
$start = microtime(true);

foreach ($suites as $name => $file) {
    if ($only !== null && $only !== $name) {
        continue;
    }
    try {
        require __DIR__ . '/' . $file;
    } catch (\Throwable $e) {
        T::$failures[] = "[{$name}] crashed in " . T::$current . ': ' . $e::class . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    }
}

$secs = round(microtime(true) - $start, 2);
if (T::$failures) {
    fwrite(STDOUT, "\n\033[31mFAILURES\033[0m\n");
    foreach (T::$failures as $f) {
        fwrite(STDOUT, "  ✗ {$f}\n");
    }
}
fwrite(STDOUT, sprintf("\n%d passed, %d failed in %ss\n", T::$pass, count(T::$failures), $secs));
exit(T::$failures ? 1 : 0);
