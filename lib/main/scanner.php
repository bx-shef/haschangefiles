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
	 * Имена каталогов, которые не обходятся на любой глубине.
	 *
	 * Только .git. До 2.0.0 здесь были и cache, tmp, upload, backup,
	 * updates — и зеркало в bitrix/modules/<модуль>/lib/cache/ не видели ни
	 * страница, ни консоль. Тяжёлые каталоги пропускаются по пути, на своём
	 * уровне: getScope().
	 */
	public const DEFAULT_SKIP_NAMES = [
		'.git',
	];

	/**
	 * Где искать правки — одно и то же для страницы отчёта и консоли.
	 *
	 * Публичная часть — корень сайта без /bitrix (у него свой раздел),
	 * /upload (данные), /local (код проекта: обновление его не
	 * перезаписывает) и /images. Ядро — /bitrix без каталогов, где правок
	 * не бывает, а файлов много.
	 *
	 * @return array<string, array{dir: string, skip: string[]}> код раздела => что обходить
	 */
	public static function getScope(string $documentRoot): array
	{
		$documentRoot = static::normalize($documentRoot);
		$bitrix = $documentRoot.'/bitrix';

		$prefix = static fn(string $base, array $dirs): array => array_map(
			static fn(string $dir): string => $base.$dir,
			$dirs
		);

		return [
			'PUBLIC' => [
				'dir' => $documentRoot,
				'skip' => $prefix($documentRoot, ['/bitrix', '/upload', '/local', '/images']),
			],
			'CORE' => [
				'dir' => $bitrix,
				'skip' => $prefix($bitrix, [
					'/backup',
					'/blocks',
					'/cache',
					'/catalog_export',
					'/fonts',
					'/html_pages',
					'/image_uploader',
					'/managed_cache',
					'/mobileapp',
					'/otp',
					'/panel',
					'/sounds',
					'/stack_cache',
					'/tmp',
					'/updates',
				]),
			],
		];
	}

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
