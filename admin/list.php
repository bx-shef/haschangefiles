<?php declare(strict_types=1);

/**
 * Отчёт «Правки ядра» — только администратору.
 *
 * Открывается через /bitrix/admin/shef_haschangefiles_list.php — заглушку,
 * которую пишут установщик и меню (каталог модуля браузеру недоступен).
 *
 * До 2.0.0 отчёт жил публичной страницей /local/admin/shef.haschangefiles/list.php
 * на шаблоне сайта и прав не проверял: список исправленных файлов ядра видел
 * любой, кто знал адрес. Теперь это страница административной части.
 *
 * Разделы, колонки и строки собирает \Shef\Haschangefiles\Main\Report;
 * здесь только вывод. Грид — штатный main.ui.grid, по одному на раздел.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Shef\Haschangefiles\Integration\Main\AdminMenu;
use Shef\Haschangefiles\Main\ChangeFile;
use Shef\Haschangefiles\Main\Constants;
use Shef\Haschangefiles\Main\Report;
use Shef\Haschangefiles\Main\Scanner;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

/** @var \CMain $APPLICATION */
/** @var \CUser $USER */
global $APPLICATION, $USER;

if(!($USER instanceof \CUser) || !$USER->IsAdmin())
{
	$APPLICATION->AuthForm(Loc::getMessage('SH_HASCHANGEFILES_LIST_ACCESS_DENIED'));
}

if(!Loader::includeModule('shef.haschangefiles'))
{
	require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
	\CAdminMessage::ShowMessage('Module shef.haschangefiles is not installed');
	require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
	return;
}

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$lang = (string)(Context::getCurrent()?->getLanguage() ?: LANGUAGE_ID);
$documentRoot = (string)Application::getDocumentRoot();
$project = Constants::getProjectSelfName();
$projectRoot = Constants::getProjectSelfRootPath();

$APPLICATION->SetTitle(Loc::getMessage('SH_HASCHANGEFILES_LIST_TITLE'));

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
?>
<style>
	.sh-hcf-section { margin-bottom: 30px; }
	.sh-hcf-section h2 { font-size: 18px; font-weight: 600; margin: 0 0 12px; }
	.sh-hcf-status { font-weight: 600; }
	.sh-hcf-status-success { color: #47be7d; }
	.sh-hcf-status-danger { color: <?=$escape(\Shef\Haschangefiles\Integration\Main\Events::DANGER_COLOR)?>; }
	.sh-hcf-upper { color: #009ef7; text-transform: uppercase; }
</style>
<p>
	<?=$escape((string)Loc::getMessage('SH_HASCHANGEFILES_LIST_NOTE'))?>
	<br><a href="<?=$escape(AdminMenu::getUrlSettings($lang))?>"><?=$escape((string)Loc::getMessage('SH_HASCHANGEFILES_LIST_PHPSTORM'))?></a>
</p>
<?php
foreach(Report::getSections($documentRoot) as $section)
{
	$rows = [];
	foreach(Scanner::find($section['dir'], $section['skip']) as $num => $path)
	{
		$rows[] = Report::getRow(new ChangeFile($path), $num + 1, $documentRoot, $project, $projectRoot);
	}
	?>
	<div class="sh-hcf-section">
		<h2><?=$escape($section['title'])?> <small>(<?=$escape(Report::getRelativePath($section['dir'], $documentRoot) ?: '/')?>)</small></h2>
		<?php
		$APPLICATION->IncludeComponent(
			'bitrix:main.ui.grid',
			'',
			[
				'GRID_ID' => 'SHEF_HASCHANGEFILES_'.$section['code'],
				'COLUMNS' => Report::getColumns(),
				'ROWS' => $rows,
				'SHOW_ROW_CHECKBOXES' => false,
				'SHOW_GRID_SETTINGS_MENU' => false,
				'SHOW_PAGINATION' => false,
				'SHOW_SELECTED_COUNTER' => false,
				'SHOW_TOTAL_COUNTER' => true,
				'TOTAL_ROWS_COUNT' => count($rows),
				'ALLOW_COLUMNS_SORT' => false,
				'ALLOW_COLUMNS_RESIZE' => false,
				'AJAX_MODE' => 'N',
			],
			false,
			['HIDE_ICONS' => 'Y']
		);
		?>
	</div>
	<?php
}

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
