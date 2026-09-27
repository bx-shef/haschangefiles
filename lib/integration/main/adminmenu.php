<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Integration\Main;

use Bitrix\Main\Localization\Loc;
use Shef\Haschangefiles\Main\AdminPage;
use Shef\Haschangefiles\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Раздел «Правки ядра» в меню административной части.
 *
 * Штатная замена пункта, который до 2.0.0 модуль вешал на верхнюю панель
 * через событие модуля shef.uiclear. Меню отдаёт admin/menu.php, а его ядро
 * подключает само для каждого установленного модуля — регистрировать ничего
 * не нужно.
 *
 * Метод чистый — без глобалов и обращений к базе, — чтобы его можно было
 * проверить без портала: права и язык передаёт admin/menu.php.
 */
class AdminMenu
{
	public const PARENT_MENU = 'global_menu_settings';
	public const ITEMS_ID = 'menu_shef_haschangefiles';

	public const LIST_PAGE = '/bitrix/admin/'.AdminPage::FILE;

	/**
	 * @param string $lang язык административной части
	 * @return array описание раздела в формате admin/menu.php
	 */
	public static function build(string $lang): array
	{
		return [
			'parent_menu' => static::PARENT_MENU,
			'section' => Constants::MODULE_ID,
			'sort' => 1910,
			'text' => (string)Loc::getMessage('SH_HASCHANGEFILES_MENU'),
			'title' => (string)Loc::getMessage('SH_HASCHANGEFILES_MENU_TITLE'),
			'icon' => 'sys_menu_icon',
			'items_id' => static::ITEMS_ID,
			'items' => [
				[
					'text' => (string)Loc::getMessage('SH_HASCHANGEFILES_MENU_LIST'),
					'url' => static::getUrlList($lang),
					'more_url' => [static::LIST_PAGE],
				],
				[
					'text' => (string)Loc::getMessage('SH_HASCHANGEFILES_MENU_SETTINGS'),
					'url' => static::getUrlSettings($lang),
				],
			],
		];
	}

	/**
	 * Кладёт страницу учёта правок в /bitrix/admin, если её там нет.
	 *
	 * Заглушку пишет установщик. Но портал, обновлённый с 1.x заменой
	 * файлов, установщик не проходил — и пункт меню вёл бы в 404.
	 * Переустановка не выход: она стирает настройки. Поэтому меню, которое
	 * строится только у администратора, пишет недостающую заглушку само — тем
	 * же AdminPage::install(), с путём туда, где модуль стоит. Чужой файл на
	 * этом месте не трогает.
	 *
	 * @param string $documentRoot корень сайта
	 * @param string $moduleDir каталог модуля, абсолютный
	 * @return bool страница на месте и ведёт в этот модуль
	 */
	public static function ensureListPage(string $documentRoot, string $moduleDir): bool
	{
		return AdminPage::install($documentRoot, $moduleDir);
	}

	// region Адреса ////
	/**
	 * Страница учёта правок.
	 */
	public static function getUrlList(string $lang): string
	{
		return static::LIST_PAGE.'?'.http_build_query([
			'lang' => $lang,
		]);
	}

	public static function getUrlSettings(string $lang): string
	{
		return '/bitrix/admin/settings.php?'.http_build_query([
			'lang' => $lang,
			'mid' => Constants::MODULE_ID,
		]);
	}
	// endregion ////
}
