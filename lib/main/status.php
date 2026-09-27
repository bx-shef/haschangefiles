<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Main;

/**
 * Состояние правки ядра: зеркало `has-change_<имя>` против оригинала.
 *
 * Значения — то, что печатает консольная проверка; подписи для страницы
 * лежат в языковом файле компонента (STATUS_<значение>).
 */
enum Status: string
{
	/** Оригинал совпадает с зеркалом, маркер правки на месте. */
	case Ok = 'OK';
	/** Оригинал разошёлся с зеркалом — обычно его перезаписало обновление. */
	case Drift = 'DRIFT';
	/** Совпадают, но маркера правки нет — правку не найти глазами. */
	case NoMarkers = 'NO-MARKERS';
	/** Зеркало есть, оригинала нет — файл удалён или переименован ядром. */
	case NoOriginal = 'NO-ORIGINAL';

	public function isNeedFix(): bool
	{
		return $this !== self::Ok;
	}
}
