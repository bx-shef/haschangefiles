<?php declare(strict_types=1);

/**
 * Проверка правок ядра из консоли: то же, что страница «Правки ядра», но
 * кодом возврата — для CI и для шага после обновления Битрикса.
 *
 * Обходит те же разделы, что и страница (Scanner::getScope()), для каждого
 * зеркала has-change_<файл> сравнивает его с оригиналом рядом и печатает
 * состояние. Первое слово строки — контракт, его разбирают скрипты:
 *
 *   OK           правка на месте
 *   DRIFT        оригинал разошёлся с зеркалом — правку вернуть
 *   NO-MARKERS   совпадают, но в файле нет пометки «// change»
 *   NO-ORIGINAL  оригинала нет
 *
 * Запуск (из каталога модуля или откуда угодно):
 *
 *   php cli/check-core-changes.php                  # публичная часть и ядро
 *   BASE_DIR=/bitrix php cli/check-core-changes.php # один каталог от корня
 *   php cli/check-core-changes.php --diff           # плюс diff -u по DRIFT
 *
 * Корень сайта — из DOCUMENT_ROOT, а без него — на четыре уровня выше этого
 * файла, по пути, которым его запустили (модуль-ссылка не уводит наверх
 * от своего настоящего места): <корень>/bitrix/modules/shef.haschangefiles/cli
 * или <корень>/local/modules/shef.haschangefiles/cli.
 *
 * Код возврата: 0 — всё на месте; 1 — есть что восстановить; 2 — не найден
 * корень сайта (нет в нём /bitrix) или каталог BASE_DIR внутри него. Скрипт
 * ничего не меняет. Ядро Битрикса и базу не поднимает — сравнивает только
 * файлы, поэтому работает и на копии сайта без портала.
 */

if(PHP_SAPI !== 'cli')
{
	http_response_code(403);
	die('Forbidden: CLI only');
}

// Ядро не поднимаем, поэтому и автозагрузки нет: классы — явным require.
require_once dirname(__DIR__).'/lib/main/status.php';
require_once dirname(__DIR__).'/lib/main/utils.php';
require_once dirname(__DIR__).'/lib/main/changefile.php';
require_once dirname(__DIR__).'/lib/main/scanner.php';

use Shef\Haschangefiles\Main\ChangeFile;
use Shef\Haschangefiles\Main\Scanner;
use Shef\Haschangefiles\Main\Status;

/** Имя файла с диска — в терминал без управляющих символов. */
$printable = static fn(string $value): string => (string)preg_replace('/[\x00-\x1f\x7f]/', '?', $value);

$fail = static function(string $message): never
{
	fwrite(STDERR, $message.PHP_EOL);
	exit(2);
};

/**
 * «.» и «..» в пути — без разрешения ссылок: realpath() увёл бы модуль-ссылку
 * в его настоящий каталог, а нужен путь, как его видит сайт.
 */
$normalize = static function(string $path): string
{
	$parts = [];
	foreach(explode('/', str_replace('\\', '/', $path)) as $part)
	{
		if($part === '' || $part === '.')
		{
			continue;
		}

		if($part === '..')
		{
			array_pop($parts);
			continue;
		}

		$parts[] = $part;
	}

	return '/'.implode('/', $parts);
};

// Без DOCUMENT_ROOT — от пути, которым скрипт запустили: __DIR__ уже
// разрешён, и у модуля-ссылки вёл бы в чужой каталог рядом с настоящим.
// Скрипт подключён чужим (обёртка в CI) — SCRIPT_FILENAME про обёртку, тогда
// от своего файла.
$script = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
if($script === '' || realpath($script) !== realpath(__FILE__))
{
	$script = __FILE__;
}
elseif(!str_starts_with($script, '/'))
{
	$script = getcwd().'/'.$script;
}

$documentRoot = trim((string)getenv('DOCUMENT_ROOT'));
$root = $normalize($documentRoot !== '' ? (str_starts_with($documentRoot, '/') ? $documentRoot : getcwd().'/'.$documentRoot) : dirname($normalize($script), 5));

// Каталог без /bitrix — не сайт: «зеркал 0, код 0» на опечатке в пути
// выглядело бы пройденной проверкой.
if(!is_dir($root) || !is_dir($root.'/bitrix'))
{
	$fail('Не найден корень сайта (каталог с /bitrix): задайте DOCUMENT_ROOT');
}

$showDiff = in_array('--diff', $argv, true);
if($showDiff && !function_exists('shell_exec'))
{
	fwrite(STDERR, 'shell_exec отключён — --diff не печатается'.PHP_EOL);
	$showDiff = false;
}

$scope = Scanner::getScope($root);

// BASE_DIR — от корня, без разрешения ссылок: /bitrix бывает ссылкой на
// общее ядро нескольких сайтов, и это всё ещё «ядро этого сайта». Корень
// целиком — обычные разделы: иначе пропуск /bitrix из публичной части
// спрятал бы ядро.
$baseRel = trim((string)getenv('BASE_DIR'));
$baseDir = $baseRel !== '' ? $normalize($root.'/'.$baseRel) : $root;
if($baseDir !== $root)
{
	if(!str_starts_with($baseDir, $root.'/') || !is_dir($baseDir))
	{
		$fail('Нет каталога BASE_DIR внутри корня сайта: '.$printable($baseRel));
	}

	// Один каталог, пропуски — те же, что у разделов.
	$scope = [[
		'dir' => $baseDir,
		'skip' => array_merge(...array_values(array_column($scope, 'skip'))),
	]];
}

$found = 0;
$toFix = 0;

foreach($scope as $section)
foreach(Scanner::find($section['dir'], $section['skip']) as $mirror)
{
	$found++;
	$file = new ChangeFile($mirror);
	$status = $file->getStatus();

	printf('%-12s %s%s', $status->value, $printable(substr($file->getOriginalPath(), strlen($root))), PHP_EOL);

	if(!$status->isNeedFix())
	{
		continue;
	}

	$toFix++;

	if($showDiff && $status === Status::Drift)
	{
		echo (string)shell_exec('diff -u '.escapeshellarg($mirror).' '.escapeshellarg($file->getOriginalPath())), '----', PHP_EOL;
	}
}

echo '----', PHP_EOL;
printf('Зеркал найдено: %d; восстановить: %d%s', $found, $toFix, PHP_EOL);

exit($toFix > 0 ? 1 : 0);
