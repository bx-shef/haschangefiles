<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Main;

/**
 * Правила учёта правок ядра — одни на страницу модуля и на проверку из
 * консоли (cli/check-core-changes.php).
 *
 * Правка ядра помечается блоком `// change ////` … `// change stop ////`, а
 * рядом с файлом кладётся его копия с правкой — зеркало `has-change_<имя>`.
 * Обновление Битрикса перезаписывает файл, зеркало остаётся: по их
 * расхождению и видно, какую правку надо вернуть.
 *
 * Класс самодостаточен — ни ядра, ни модуля: консольная проверка подключает
 * его явным require_once и работает без поднятого портала.
 */
class Utils
{
	/** Префикс зеркала правки: `has-change_<оригинал>`. */
	public const FIND_PREFIX_FILE = 'has-change_';

	/**
	 * Варианты маркера правки в теле файла, включая опечатки, которые уже
	 * разошлись по порталам: исправить их там некому, а не считать — значит
	 * показать правку как пропавшую.
	 *
	 * @return string[]
	 */
	public static function changeMarkers(): array
	{
		return ['// change', '//change', '//cahnge', '// cahnge'];
	}

	/** Имя файла — зеркало правки? */
	public static function isMirrorName(string $fileName): bool
	{
		return str_starts_with($fileName, static::FIND_PREFIX_FILE)
			&& strlen($fileName) > strlen(static::FIND_PREFIX_FILE);
	}

	/**
	 * Путь оригинала по пути зеркала: префикс снимается только с имени
	 * файла. Каталог с тем же сочетанием букв в пути не трогается — раньше
	 * компонент делал str_replace по всему пути.
	 */
	public static function originalPath(string $mirrorPath): string
	{
		$name = basename($mirrorPath);
		if(str_starts_with($name, static::FIND_PREFIX_FILE))
		{
			$name = substr($name, strlen(static::FIND_PREFIX_FILE));
		}

		return dirname($mirrorPath).'/'.$name;
	}

	/** Сколько маркеров правки в тексте (сумма по всем вариантам). */
	public static function countChangeMarkers(string $content): int
	{
		$count = 0;
		foreach(static::changeMarkers() as $marker)
		{
			$count += substr_count($content, $marker);
		}

		return $count;
	}

	/**
	 * Файлы совпадают содержимым.
	 *
	 * До 2.0.0 сравнивался только размер: обновление, заменившее в файле
	 * символ на символ, выглядело «правкой на месте». Размер остаётся
	 * первой, дешёвой проверкой, содержимое — второй.
	 */
	public static function isSameContent(string $left, string $right): bool
	{
		if(!is_file($left) || !is_file($right))
		{
			return false;
		}

		if(filesize($left) !== filesize($right))
		{
			return false;
		}

		return hash_file('sha256', $left) === hash_file('sha256', $right);
	}

	/**
	 * Состояние правки.
	 *
	 * «На месте» — только если оригинал есть, совпадает с зеркалом и в нём
	 * есть маркер. Порядок проверок задаёт, что покажет страница: пропавший
	 * файл важнее разошедшегося, разошедшийся — важнее непомеченного.
	 */
	public static function getStatus(bool $originalExists, bool $isSame, int $originalMarkers): Status
	{
		if(!$originalExists)
		{
			return Status::NoOriginal;
		}

		if(!$isSame)
		{
			return Status::Drift;
		}

		if($originalMarkers < 1)
		{
			return Status::NoMarkers;
		}

		return Status::Ok;
	}
}
