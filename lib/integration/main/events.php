<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Integration\Main;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;

Loc::loadMessages(__FILE__);

/**
 * Обработчики событий ядра.
 */
class Events
{
	public const UPDATE_PAGE = '/bitrix/admin/update_system.php';

	/** Цвет «опасно» в тексте предупреждения — тот же, что у статуса на странице. */
	public const DANGER_COLOR = '#f1416c';

	/**
	 * Страница обновлений: кнопка «Правки ядра» и подтверждение перед
	 * установкой обновлений — обновление перезапишет исправленные файлы.
	 *
	 * main:OnAdminContextMenuShow, регистрация совместимая: ядро передаёт
	 * пункты контекстного меню по ссылке.
	 */
	public static function onAdminContextMenuShow(?array &$items = []): void
	{
		if(!static::isUpdatePage())
		{
			return;
		}

		if(!CurrentUser::get()->isAdmin())
		{
			return;
		}

		$lang = (string)(Context::getCurrent()?->getLanguage() ?: 'ru');

		$items ??= [];
		$items[] = static::getContextMenuItem($lang);

		Extension::load(['ui.dialogs.messagebox']);
		Asset::getInstance()->addString(static::getConfirmScript($lang));
	}

	private static function isUpdatePage(): bool
	{
		$context = Application::getInstance()->getContext();

		return $context->getServer()->getRequestMethod() === 'GET'
			&& $context->getRequest()->getRequestedPage() === static::UPDATE_PAGE;
	}

	/**
	 * Кнопка в контекстном меню страницы обновлений: отчёт в новой вкладке.
	 *
	 * @internal открыт для теста, не API модуля
	 */
	public static function getContextMenuItem(string $lang): array
	{
		return [
			'TEXT' => (string)Loc::getMessage('SH_HASCHANGEFILES_UPDATE_BTN'),
			'TITLE' => (string)Loc::getMessage('SH_HASCHANGEFILES_UPDATE_BTN_TITLE'),
			'LINK' => AdminMenu::getUrlList($lang),
			'LINK_PARAM' => 'target="_blank"',
			'ICON' => 'adm-btn',
		];
	}

	/**
	 * Подтверждение перед установкой обновлений.
	 *
	 * Кнопки «Установить обновления» ядро рисует на странице само и вешает
	 * на них свои InstallUpdates() / InstallUpdatesSel(). Скрипт ставит перед
	 * ними диалог: «сначала проверьте, что правки зафиксированы». Отказ —
	 * обновление не начинается.
	 *
	 * Тексты уходят в скрипт через json_encode: подстановка строкой в
	 * кавычки ломалась бы на первой же кавычке в переводе.
	 *
	 * @internal открыт для теста, не API модуля
	 */
	public static function getConfirmScript(string $lang): string
	{
		$json = static fn(string $value): string => json_encode(
			$value,
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		$message = (string)Loc::getMessage('SH_HASCHANGEFILES_UPDATE_CONFIRM_MESSAGE', [
			'#DANGER_COLOR#' => static::DANGER_COLOR,
			'#URL#' => htmlspecialchars(AdminMenu::getUrlList($lang), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
		]);

		return strtr(<<<'JS'
<script>
BX.ready(function()
{
	var bind = function(node, install)
	{
		if(!node || typeof install !== 'function')
		{
			return;
		}

		node.onclick = function(event)
		{
			if(event)
			{
				event.preventDefault();
			}

			BX.UI.Dialogs.MessageBox.confirm(
				#MESSAGE#,
				#TITLE#,
				function(messageBox)
				{
					messageBox.close();
					install();
				},
				#BUTTON#,
				function(messageBox)
				{
					messageBox.close();
				}
			);

			return false;
		};
	};

	bind(BX('install_updates_button'), window.InstallUpdates);
	bind(BX('install_updates_sel_button'), window.InstallUpdatesSel);
});
</script>
JS, [
			'#MESSAGE#' => $json($message),
			'#TITLE#' => $json((string)Loc::getMessage('SH_HASCHANGEFILES_UPDATE_CONFIRM_TITLE')),
			'#BUTTON#' => $json((string)Loc::getMessage('SH_HASCHANGEFILES_UPDATE_CONFIRM_BTN')),
		]);
	}
}
