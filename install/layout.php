<?php declare(strict_types=1);

/**
 * Раскладка модуля вне его каталога — для портала, который выкладывается из git
 * (bx-shef/options#55, читает Main\Modules\Layout shef.options без ядра): заглушка страницы
 * отчёта в /bitrix/admin (Main\AdminPage), ровно как InstallFiles(). installDir пуст.
 */

require_once dirname(__DIR__).'/lib/main/adminpage.php';

return static function(string $documentRoot, string $moduleDir): array {
	$root = rtrim(str_replace('\\', '/', $documentRoot), '/');

	return [
		'copy' => [],
		'files' => [
			substr(\Shef\Haschangefiles\Main\AdminPage::getTarget($root), strlen($root)) => \Shef\Haschangefiles\Main\AdminPage::getContent($root, $moduleDir),
		],
	];
};
