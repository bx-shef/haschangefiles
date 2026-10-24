<?php declare(strict_types=1);

/**
 * install/layout.php (bx-shef/options#55): раскладка для портала из git — ровно то, что
 * AdminPage::install() пишет на пустой корень сайта; installDir пуст; файл подключается без ядра.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$make = include $root.'/install/layout.php';

$portal = sys_get_temp_dir().'/shef-hcf-layout-'.getmypid();
mkdir($portal.'/bitrix/admin', 0755, true);
$moduleDir = $portal.'/local/modules/shef.haschangefiles';
\Shef\Haschangefiles\Main\AdminPage::install($portal, $moduleDir);
$written = [];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($portal, FilesystemIterator::SKIP_DOTS)) as $file)
{
	$written[substr($file->getPathname(), strlen($portal))] = (string)file_get_contents($file->getPathname());
}
$layout = $make($portal, $moduleDir);

Check::same('раскладка = то, что пишет установщик', $layout['files'], $written);
Check::same('копий нет', $layout['copy'], []);
Check::same('заглушка ведёт в /local/modules', str_contains((string)current($layout['files']), "/local/modules/shef.haschangefiles/"), true);

exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg('var_export(is_callable(include "'.$root.'/install/layout.php"));'), $out, $code);
Check::same('без ядра', [$code, implode('', $out)], [0, 'true']);

exec('rm -rf '.escapeshellarg($portal));

Check::finish();
