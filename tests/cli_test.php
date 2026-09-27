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

Check::group('BASE_DIR');

$result = $run(['DOCUMENT_ROOT' => $site, 'BASE_DIR' => '/crm']);
Check::same('только публичная часть — всё на месте', [$result['code'], substr_count($result['out'], 'OK ')], [0, 1]);

$result = $run(['DOCUMENT_ROOT' => $site, 'BASE_DIR' => '/нет']);
Check::same('нет каталога — код 2', $result['code'], 2);

Check::group('корень сайта');

$result = $run(['DOCUMENT_ROOT' => $site.'/нет']);
Check::same('нет корня — код 2, а не «всё хорошо»', $result['code'], 2);

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

exec(
	'env -u DOCUMENT_ROOT '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($module.'/cli/check-core-changes.php').' 2>&1',
	$output,
	$code
);
Check::same('модуль в /bitrix/modules — корень найден сам', [$code, str_contains(implode(PHP_EOL, $output), 'DRIFT        /bitrix/modules/crm/a.php')], [1, true]);

// region Уборка ////
exec('rm -rf '.escapeshellarg($site));
// endregion ////

Check::finish();
