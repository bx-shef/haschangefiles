<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Integration\Shef\UiClear;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Shef\Haschangefiles\Main\Constants;

/**
 * Заглушка для порталов, обновлённых с 1.x.
 *
 * До 2.0.0 здесь жили оба обработчика модуля: пункт «[SH] Правки ядра» на
 * верхней панели через событие shef.uiclear и кнопка с подтверждением на
 * странице обновлений через main:OnAdminContextMenuShow. С 2.0.0 от
 * shef.uiclear модуль не зависит: пункт живёт в меню административной части
 * (admin/menu.php), а обработчик ядра — в Integration\Main\Events.
 *
 * Но регистрация обработчиков в b_module_to_module при замене файлов модуля
 * никуда не девается — её снимает только деинсталляция. Удали мы класс, и
 * событие звало бы то, чего нет. Поэтому класс остаётся: на shef.uiclear
 * честно отвечает «мне нечего добавить», а страницу обновлений передаёт
 * новому обработчику. Установщик снимает обе регистрации при удалении
 * (\shef_haschangefiles::getLegacyEventsList()).
 *
 * Удалять вместе со следующей мажорной версией, когда порталов на 1.x не
 * останется.
 */
class Events
{
	public static function onBitrixMenuExtInitTopPanelUserMenu(Event $event): EventResult
	{
		return new EventResult(
			EventResult::UNDEFINED,
			null,
			Constants::MODULE_ID
		);
	}

	public static function onAdminContextMenuShow(?array &$items = []): void
	{
		\Shef\Haschangefiles\Integration\Main\Events::onAdminContextMenuShow($items);
	}
}
