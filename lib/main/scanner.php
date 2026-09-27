<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Main;

/**
 * Поиск зеркал правок `has-change_*` под каталогом.
 *
 * Один обход на страницу модуля и на консольную проверку. Раньше их было
 * два, и компонентный спотыкался на том, на чём порталы стоят сплошь и
 * рядом:
 *
 * * символическая ссылка на каталог (общий /bitrix у нескольких сайтов,
 *   ссылка на upload) — обход шёл по ней, а ссылка наверх давала
 *   бесконечную рекурсию. Здесь по ссылкам не ходим;
 * * каталог без прав на чтение — scandir() отдавал false, и count() падал
 *   TypeError посреди страницы. Здесь такой каталог пропускается.
 *
 * Класс самодостаточен, как Utils.
 */
class Scanner
{
	/**
	 * Каталоги, в которых правок ядра не бывает, а файлов — сотни тысяч.
	 * Пропускаются по имени на любой глубине.
	 */
	public const DEFAULT_SKIP_NAMES = [
		'.git',
		'backup',
		'cache',
		'html_pages',
		'managed_cache',
		'stack_cache',
		'tmp',
		'updates',
		'upload',
	];

	/**
	 * @param string $baseDir где искать, абсолютный путь
	 * @param string[] $skipPaths каталоги, которые не обходить, абсолютные пути
	 * @param string[] $skipNames имена каталогов, которые не обходить нигде
	 * @return string[] пути зеркал, по алфавиту
	 */
	public static function find(string $baseDir, array $skipPaths = [], array $skipNames = self::DEFAULT_SKIP_NAMES): array
	{
		$baseDir = static::normalize($baseDir);
		if($baseDir === '' || !is_dir($baseDir) || !is_readable($baseDir))
		{
			return [];
		}

		$skipPaths = array_fill_keys(array_map(static::normalize(...), $skipPaths), true);
		$skipNames = array_fill_keys($skipNames, true);

		$filter = new \RecursiveCallbackFilterIterator(
			new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
			static function(\SplFileInfo $current) use ($skipPaths, $skipNames): bool
			{
				if(!$current->isDir())
				{
					return Utils::isMirrorName($current->getFilename());
				}

				if($current->isLink() || !$current->isReadable())
				{
					return false;
				}

				return !isset($skipNames[$current->getFilename()])
					&& !isset($skipPaths[static::normalize($current->getPathname())]);
			}
		);

		$found = [];
		$iterator = new \RecursiveIteratorIterator(
			$filter,
			\RecursiveIteratorIterator::LEAVES_ONLY,
			\RecursiveIteratorIterator::CATCH_GET_CHILD
		);

		foreach($iterator as $file)
		{
			/** @var \SplFileInfo $file */
			if($file->isFile())
			{
				$found[] = $file->getPathname();
			}
		}

		sort($found, SORT_STRING);

		return $found;
	}

	private static function normalize(string $path): string
	{
		$path = str_replace('\\', '/', trim($path));

		return $path === '/' ? $path : rtrim($path, '/');
	}
}
