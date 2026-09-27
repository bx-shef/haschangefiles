<?php declare(strict_types=1);

/**
 * Поиск зеркал и состояние правки — то, ради чего модуль существует.
 *
 * Классы подключаются явным require_once, без автозагрузки и ядра: ровно так
 * их подключает консольная проверка, и тест заодно держит, что они
 * самодостаточны. Понадобится классу ядро — тест упадёт fatal-ом.
 *
 * Что держит:
 *
 * * состояние: на месте / разошёлся / без пометки / нет оригинала. Отдельно —
 *   обновление, заменившее символ на символ: до 2.0.0 сравнивался размер, и
 *   такой файл выглядел «правкой на месте»;
 * * путь оригинала: префикс снимается с имени файла, а не по всему пути;
 * * обход: пропуск по имени и по пути, символическая ссылка на каталог не
 *   обходится (ссылка наверх давала бесконечную рекурсию), нечитаемый
 *   каталог пропускается, а не роняет страницу.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';
require_once $root.'/lib/main/status.php';
require_once $root.'/lib/main/utils.php';
require_once $root.'/lib/main/changefile.php';
require_once $root.'/lib/main/scanner.php';

use Shef\Haschangefiles\Main\ChangeFile;
use Shef\Haschangefiles\Main\Scanner;
use Shef\Haschangefiles\Main\Status;
use Shef\Haschangefiles\Main\Utils;

$site = sys_get_temp_dir().'/shef-haschangefiles-scanner-'.getmypid();

$put = static function(string $path, string $content) use ($site): string
{
	$path = $site.$path;
	if(!is_dir(dirname($path)))
	{
		mkdir(dirname($path), 0777, true);
	}
	file_put_contents($path, $content);

	return $path;
};

$edited = "<?php\n// change ////\n\$a = 2;\n// change stop ////\n";

// Правка на месте.
$put('/bitrix/modules/crm/lib/ok.php', $edited);
$put('/bitrix/modules/crm/lib/has-change_ok.php', $edited);
// Обновление перезаписало файл целиком.
$put('/bitrix/modules/crm/lib/drift.php', "<?php\n\$a = 1;\n");
$put('/bitrix/modules/crm/lib/has-change_drift.php', $edited);
// Обновление заменило символ на символ: размер тот же, маркер на месте.
$put('/bitrix/modules/crm/lib/same-size.php', str_replace('$a = 2', '$a = 3', $edited));
$put('/bitrix/modules/crm/lib/has-change_same-size.php', $edited);
// Совпадают, но правка не помечена.
$put('/bitrix/templates/main/nomark.php', "<?php\n\$a = 2;\n");
$put('/bitrix/templates/main/has-change_nomark.php', "<?php\n\$a = 2;\n");
// Ядро файл удалило.
$put('/bitrix/js/ui/has-change_gone.js', "// change ////\n");
// Каталог с тем же сочетанием букв в имени.
$put('/bitrix/components/has-change_dir/tpl.php', $edited);
$put('/bitrix/components/has-change_dir/has-change_tpl.php', $edited);
// Там, где не ищем.
$put('/bitrix/cache/has-change_cached.php', $edited);
$put('/upload/has-change_upload.php', $edited);
$put('/bitrix/backup/has-change_backup.php', $edited);
// Публичная часть.
$put('/crm/index.php', $edited);
$put('/crm/has-change_index.php', $edited);
// Не зеркало: одно слово префикса без имени.
$put('/bitrix/has-change_', '');

$status = static fn(string $mirror): Status => (new ChangeFile($site.$mirror))->getStatus();

Check::group('состояние правки');

Check::same('на месте', $status('/bitrix/modules/crm/lib/has-change_ok.php'), Status::Ok);
Check::same('обновление перезаписало', $status('/bitrix/modules/crm/lib/has-change_drift.php'), Status::Drift);
Check::same('тот же размер, другое содержимое — разошёлся', $status('/bitrix/modules/crm/lib/has-change_same-size.php'), Status::Drift);
Check::same('совпадает, но без пометки', $status('/bitrix/templates/main/has-change_nomark.php'), Status::NoMarkers);
Check::same('оригинала нет', $status('/bitrix/js/ui/has-change_gone.js'), Status::NoOriginal);
Check::same('на месте — не править', Status::Ok->isNeedFix(), false);
Check::same('остальное — править', array_map(static fn(Status $s): bool => $s->isNeedFix(), [Status::Drift, Status::NoMarkers, Status::NoOriginal]), [true, true, true]);

Check::group('маркеры');

Check::same('все варианты, включая опечатки', Utils::countChangeMarkers("// change\n//change\n//cahnge\n// cahnge\n"), 4);
Check::same('«// change stop» — тоже маркер', Utils::countChangeMarkers("// change ////\n// change stop ////\n"), 2);
Check::same('без маркеров', Utils::countChangeMarkers("<?php\n// правка\n"), 0);
// Маркер ищется подстрокой, и «// changed» в чужом комментарии тоже
// засчитывается. Так было всегда; держим, чтобы это не поменялось молча.
Check::same('«// changed» — тоже маркер', Utils::countChangeMarkers("<?php\n// changed by vendor\n"), 1);

Check::group('путь оригинала');

Check::same('префикс снимается с имени', Utils::originalPath('/www/a/has-change_b.php'), '/www/a/b.php');
Check::same(
	'каталог с тем же сочетанием букв не трогается',
	Utils::originalPath('/www/has-change_dir/has-change_tpl.php'),
	'/www/has-change_dir/tpl.php'
);
Check::same('имя зеркала', Utils::isMirrorName('has-change_x.php'), true);
Check::same('один префикс — не зеркало', Utils::isMirrorName('has-change_'), false);
Check::same('префикс не в начале — не зеркало', Utils::isMirrorName('x_has-change_y.php'), false);

Check::group('обход');

$found = array_map(
	static fn(string $path): string => substr($path, strlen($site)),
	Scanner::find($site)
);

Check::same('найдено ровно то, что надо', $found, [
	'/bitrix/components/has-change_dir/has-change_tpl.php',
	'/bitrix/js/ui/has-change_gone.js',
	'/bitrix/modules/crm/lib/has-change_drift.php',
	'/bitrix/modules/crm/lib/has-change_ok.php',
	'/bitrix/modules/crm/lib/has-change_same-size.php',
	'/bitrix/templates/main/has-change_nomark.php',
	'/crm/has-change_index.php',
]);

$found = Scanner::find($site, [$site.'/bitrix']);
Check::same('пропуск по пути', array_map(static fn(string $path): string => substr($path, strlen($site)), $found), ['/crm/has-change_index.php']);

$found = Scanner::find($site.'/bitrix/', [$site.'/bitrix/modules/'], []);
Check::same(
	'без пропуска по имени кеш и резервные копии видны, пути со слэшем на конце — те же',
	array_map(static fn(string $path): string => substr($path, strlen($site)), $found),
	[
		'/bitrix/backup/has-change_backup.php',
		'/bitrix/cache/has-change_cached.php',
		'/bitrix/components/has-change_dir/has-change_tpl.php',
		'/bitrix/js/ui/has-change_gone.js',
		'/bitrix/templates/main/has-change_nomark.php',
	]
);

Check::same('каталога нет — пусто, а не ошибка', Scanner::find($site.'/нет'), []);

// Ссылка наверх: обход по ней не закончился бы никогда.
symlink($site, $site.'/bitrix/modules/loop');
symlink($site.'/crm', $site.'/crm-link');
$found = Scanner::find($site);
Check::same('по символическим ссылкам на каталоги не ходим', count($found), 7);

// Нечитаемый каталог. Под root права не действуют — там проверять нечего.
if(function_exists('posix_geteuid') && posix_geteuid() !== 0)
{
	mkdir($site.'/closed');
	$put('/closed/has-change_x.php', $edited);
	chmod($site.'/closed', 0000);
	Check::same('нечитаемый каталог пропускается', count(Scanner::find($site)), 7);
	chmod($site.'/closed', 0777);
}

// region Уборка ////
exec('rm -rf '.escapeshellarg($site));
// endregion ////

Check::finish();
