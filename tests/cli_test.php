<?php declare(strict_types=1);

/**
 * Консольная проверка: код возврата — это и есть её ответ.
 *
 * Её ставят шагом после обновления Битрикса и в CI: «0» пропускает дальше,
 * «1» останавливает. Перепутанный код возврата молча пропустил бы
 * перезаписанную правку.
 *
 * Скрипт запускается настоящий, отдельным процессом, на сайте-песочнице.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$site = sys_get_temp_dir().'/shef-haschangefiles-cli-'.getmypid();

$put = static function(string $path, string $content) use ($site): void
{
	$path = $site.$path;
	if(!is_dir(dirname($path)))
	{
		mkdir(dirname($path), 0777, true);
	}
	file_put_contents($path, $content);
};

/** @return array{code: int, out: string} */
$run = static function(array $env, string $args = '') use ($root): array
{
	$prefix = '';
	foreach($env as $name => $value)
	{
		$prefix .= $name.'='.escapeshellarg($value).' ';
	}

	exec(
		$prefix.escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/cli/check-core-changes.php').' '.$args.' 2>&1',
		$output,
		$code
	);

	return ['code' => $code, 'out' => implode(PHP_EOL, $output)];
};

$edited = "<?php\n// change ////\n\$a = 2;\n// change stop ////\n";

Check::group('всё на месте');

$put('/bitrix/modules/crm/a.php', $edited);
$put('/bitrix/modules/crm/has-change_a.php', $edited);
$put('/crm/b.php', $edited);
$put('/crm/has-change_b.php', $edited);

$result = $run(['DOCUMENT_ROOT' => $site]);
Check::same('код 0', $result['code'], 0);
Check::same('OK по каждой правке', substr_count($result['out'], 'OK '), 2);
Check::same('путь — от корня сайта', str_contains($result['out'], 'OK           /bitrix/modules/crm/a.php'), true);
Check::same('итог', str_contains($result['out'], 'Зеркал найдено: 2; восстановить: 0'), true);

Check::group('обновление перезаписало файл');

$put('/bitrix/modules/crm/a.php', "<?php\n\$a = 1;\n");

$result = $run(['DOCUMENT_ROOT' => $site]);
Check::same('код 1', $result['code'], 1);
Check::same('DRIFT', str_contains($result['out'], 'DRIFT        /bitrix/modules/crm/a.php'), true);

$result = $run(['DOCUMENT_ROOT' => $site], '--diff');
Check::same('--diff показывает, что вернуть', str_contains($result['out'], '+$a = 1;') || !is_executable('/usr/bin/diff'), true);

exec(
	'DOCUMENT_ROOT='.escapeshellarg($site).' '.escapeshellarg(PHP_BINARY).' -d disable_functions=shell_exec '.escapeshellarg($root.'/cli/check-core-changes.php').' --diff 2>&1',
	$output,
	$code
);
Check::same('shell_exec отключён — --diff молчит, код по делу, а не fatal', $code, 1);

// Тот же размер, другое содержимое — обновление заменило символ на символ.
$put('/bitrix/modules/crm/a.php', str_replace('$a = 2', '$a = 3', $edited));
$result = $run(['DOCUMENT_ROOT' => $site]);
Check::same('тот же размер — тоже DRIFT, код 1', [$result['code'], str_contains($result['out'], 'DRIFT        /bitrix/modules/crm/a.php')], [1, true]);

Check::group('восстановить — не только DRIFT');

$put('/bitrix/js/ui/has-change_gone.js', "// change ////\n");
$result = $run(['DOCUMENT_ROOT' => $site]);
Check::same('NO-ORIGINAL — код 1', [$result['code'], str_contains($result['out'], 'NO-ORIGINAL  /bitrix/js/ui/gone.js')], [1, true]);
unlink($site.'/bitrix/js/ui/has-change_gone.js');

$put('/bitrix/templates/main/h.php', "<?php\n\$a = 1;\n");
$put('/bitrix/templates/main/has-change_h.php', "<?php\n\$a = 1;\n");
$result = $run(['DOCUMENT_ROOT' => $site]);
Check::same('NO-MARKERS — код 1', [$result['code'], str_contains($result['out'], 'NO-MARKERS   /bitrix/templates/main/h.php')], [1, true]);
unlink($site.'/bitrix/templates/main/has-change_h.php');

Check::group('границы обхода — те же, что у страницы');

$put('/local/php_interface/init.php', "<?php\n");
$put('/local/php_interface/has-change_init.php', $edited);
$put('/upload/has-change_x.php', $edited);
$put('/bitrix/modules/main/lib/cache/engine.php', $edited);
$put('/bitrix/modules/main/lib/cache/has-change_engine.php', $edited);
$result = $run(['DOCUMENT_ROOT' => $site]);
Check::same('/local и /upload не обходятся', [str_contains($result['out'], '/local/'), str_contains($result['out'], '/upload/')], [false, false]);
Check::same('каталог cache глубоко в ядре — обходится', str_contains($result['out'], 'OK           /bitrix/modules/main/lib/cache/engine.php'), true);
unlink($site.'/local/php_interface/has-change_init.php');
unlink($site.'/upload/has-change_x.php');

Check::group('имя файла с управляющими символами');

$put("/crm/\033[2Jx.php", $edited);
$put("/crm/has-change_\033[2Jx.php", $edited);
$result = $run(['DOCUMENT_ROOT' => $site]);
Check::same('в терминал — без ESC', [str_contains($result['out'], "\033"), str_contains($result['out'], '/crm/?[2Jx.php')], [false, true]);
unlink($site."/crm/has-change_\033[2Jx.php");

Check::group('BASE_DIR');

$result = $run(['DOCUMENT_ROOT' => $site, 'BASE_DIR' => '/crm']);
Check::same('только публичная часть — всё на месте', [$result['code'], substr_count($result['out'], 'OK ')], [0, 1]);

$result = $run(['DOCUMENT_ROOT' => $site, 'BASE_DIR' => '/нет']);
Check::same('нет каталога — код 2', $result['code'], 2);

mkdir($site.'-outside/sub', 0777, true);
file_put_contents($site.'-outside/sub/has-change_f.php', $edited);
$result = $run(['DOCUMENT_ROOT' => $site, 'BASE_DIR' => '../'.basename($site).'-outside']);
Check::same('BASE_DIR за пределами корня — код 2, чужое не обходится', [$result['code'], str_contains($result['out'], 'has-change_f')], [2, false]);
exec('rm -rf '.escapeshellarg($site.'-outside'));

Check::group('корень сайта');

$result = $run(['DOCUMENT_ROOT' => $site.'/нет']);
Check::same('нет корня — код 2, а не «всё хорошо»', $result['code'], 2);

mkdir($site.'-empty');
$result = $run(['DOCUMENT_ROOT' => $site.'-empty']);
Check::same('каталог без /bitrix — не сайт: код 2, а не «зеркал 0, код 0»', $result['code'], 2);
rmdir($site.'-empty');

// Без DOCUMENT_ROOT корень — на четыре уровня выше cli/: модуль стоит в
// <корень>/bitrix/modules/shef.haschangefiles.
$module = $site.'/bitrix/modules/shef.haschangefiles';
mkdir($module.'/cli', 0777, true);
mkdir($module.'/lib/main', 0777, true);
copy($root.'/cli/check-core-changes.php', $module.'/cli/check-core-changes.php');
foreach(glob($root.'/lib/main/*.php') as $file)
{
	copy($file, $module.'/lib/main/'.basename($file));
}

$output = [];
exec(
	'env -u DOCUMENT_ROOT '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($module.'/cli/check-core-changes.php').' 2>&1',
	$output,
	$code
);
Check::same('модуль в /bitrix/modules — корень найден сам', [$code, str_contains(implode(PHP_EOL, $output), 'DRIFT        /bitrix/modules/crm/a.php')], [1, true]);

// Модуль — символическая ссылка на каталог вне сайта. Корень всё равно
// тот, откуда скрипт запустили, а не соседний с настоящим местом модуля.
$external = $site.'-ext/shef.haschangefiles';
mkdir(dirname($external), 0777, true);
rename($module, $external);
symlink($external, $module);
file_put_contents($site.'-ext/has-change_alien.php', $edited);

$output = [];
exec(
	'cd '.escapeshellarg($site).' && env -u DOCUMENT_ROOT '.escapeshellarg(PHP_BINARY).' bitrix/modules/shef.haschangefiles/cli/check-core-changes.php 2>&1',
	$output,
	$code
);
$text = implode(PHP_EOL, $output);
Check::same(
	'модуль-ссылка — корень сайта тот, откуда запустили',
	[$code, str_contains($text, 'DRIFT        /bitrix/modules/crm/a.php'), str_contains($text, 'alien')],
	[1, true, false]
);
exec('rm -rf '.escapeshellarg($site.'-ext'));

// region Уборка ////
exec('rm -rf '.escapeshellarg($site));
// endregion ////

Check::finish();
