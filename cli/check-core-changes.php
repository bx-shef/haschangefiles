<?php declare(strict_types=1);

/**
 * CLI-детектор дрейфа правок ядра (`has-change_*`).
 *
 * Обходит дерево портала, для каждого зеркала `has-change_<файл>` сравнивает
 * его с оригиналом рядом и печатает статус. Та же эвристика, что и на
 * админ-странице учёта правок (`shef.haschangefiles:list`) — единый источник
 * `Shef\Haschangefiles\Main\Utils::needFix()`.
 *
 * Зачем: после обновления Битрикса (см. docs/infra/bitrix-update.md) быстро
 * увидеть из консоли/CI, какие правки ядра нужно переприменить.
 *
 * Запуск:
 *   php -f .../cli/check-core-changes.php                 # весь портал (www)
 *   BASE_DIR=/bitrix php -f .../cli/check-core-changes.php # только ядро
 *   php -f .../cli/check-core-changes.php --diff           # плюс diff по DRIFT
 *
 * Выход: 0 — дрейфа нет; 1 — есть DRIFT/NO-ORIGINAL (удобно как гейт в CI).
 * Скрипт read-only, ничего не меняет. Ядро Битрикса и БД не поднимает —
 * сравнивает только файлы, поэтому работает и на голом чекауте репозитория.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Forbidden: CLI only');
}

// Скрипт сравнивает только файлы на диске, поэтому ядро Битрикса и БД ему не
// нужны — подключаем Utils напрямую. Благодаря этому проверку можно гонять в
// CI и на любой копии репозитория, где портал не поднят.
require_once(dirname(__DIR__) . '/lib/main/utils.php');

use Shef\Haschangefiles\Main\Utils;

$root = realpath(dirname(__DIR__, 4));
if ($root === false) {
    fwrite(STDERR, "Не удалось определить корень портала (www)\n");
    exit(2);
}
$showDiff = in_array('--diff', $argv, true);

// База обхода: по умолчанию всё дерево www; BASE_DIR=/bitrix — только ядро.
$baseRel = trim((string)getenv('BASE_DIR'));
$baseDir = $baseRel !== '' ? $root . '/' . ltrim($baseRel, '/') : $root;

// Каталоги, которые не сканируем (кеши/бэкапы/выгрузки/сам модуль-инструмент).
$skipDirNames = [
    'cache', 'managed_cache', 'stack_cache', 'html_pages', 'tmp',
    'backup', 'updates', 'upload', '.git',
];

$found = 0;
$drift = 0;

/** @var RecursiveIteratorIterator<RecursiveDirectoryIterator> $it */
$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        static function ($current) use ($skipDirNames): bool {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), $skipDirNames, true);
            }
            return true;
        }
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile() || !Utils::isMirrorName($file->getFilename())) {
        continue;
    }

    $found++;
    $mirror = $file->getPathname();
    $original = Utils::originalPath($mirror);

    $oriExists = is_file($original);
    $oriMarkers = $oriExists ? Utils::countChangeMarkers((string)file_get_contents($original)) : 0;

    $needFix = Utils::needFix(
        $oriExists,
        true,
        $oriExists ? (int)filesize($original) : 0,
        (int)filesize($mirror),
        $oriMarkers
    );

    $rel = str_replace($root, '', $original);
    if (!$oriExists) {
        echo 'NO-ORIGINAL  ' . $rel . PHP_EOL;
        $drift++;
    } elseif ($needFix) {
        echo 'DRIFT        ' . $rel . PHP_EOL;
        $drift++;
        if ($showDiff) {
            $cmd = 'diff -u ' . escapeshellarg($mirror) . ' ' . escapeshellarg($original);
            echo (string)shell_exec($cmd) . "----\n";
        }
    } else {
        echo 'OK           ' . $rel . PHP_EOL;
    }
}

echo '----' . PHP_EOL;
echo 'Зеркал найдено: ' . $found . '; расхождений: ' . $drift . PHP_EOL;
if ($drift > 0) {
    echo 'После обновления Битрикса переприменить правки по docs/infra/core-changes.md.' . PHP_EOL;
    exit(1);
}
exit(0);
