<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Main;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Отчёт «Правки ядра»: что обходить и как показать строку.
 *
 * Страница модуля (admin/list.php) только выводит: разделы, колонки и
 * строки собираются здесь, без глобалов и без обращений к базе, — чтобы
 * проверить их можно было без портала. Главное, что тут проверяется, —
 * экранирование: имя файла и путь попадают в HTML ячейки как есть, а файлы
 * в корне сайта кладут не только разработчики.
 */
class Report
{
	/**
	 * Разделы отчёта: публичная часть и ядро.
	 *
	 * В публичной части не обходятся /bitrix (у него свой раздел), /upload
	 * (данные) и /local (код проекта, а не ядра — править его можно без
	 * зеркал). В ядре — каталоги, где правок не бывает, а файлов много.
	 *
	 * @return list<array{code: string, title: string, dir: string, skip: string[]}>
	 */
	public static function getSections(string $documentRoot): array
	{
		$documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
		$bitrix = $documentRoot.'/bitrix';

		return [
			[
				'code' => 'PUBLIC',
				'title' => (string)Loc::getMessage('SH_HASCHANGEFILES_SECTION_PUBLIC'),
				'dir' => $documentRoot,
				'skip' => array_map(
					static fn(string $dir): string => $documentRoot.$dir,
					['/bitrix', '/upload', '/local', '/images']
				),
			],
			[
				'code' => 'CORE',
				'title' => (string)Loc::getMessage('SH_HASCHANGEFILES_SECTION_CORE'),
				'dir' => $bitrix,
				'skip' => array_map(
					static fn(string $dir): string => $bitrix.$dir,
					[
						'/backup',
						'/blocks',
						'/cache',
						'/catalog_export',
						'/fonts',
						'/image_uploader',
						'/managed_cache',
						'/mobileapp',
						'/otp',
						'/panel',
						'/sounds',
						'/stack_cache',
						'/tmp',
						'/updates',
					]
				),
			],
		];
	}

	/**
	 * Колонки грида main.ui.grid.
	 */
	public static function getColumns(): array
	{
		return [
			['id' => 'NUM', 'name' => '#', 'default' => true, 'width' => 40],
			['id' => 'STATUS', 'name' => (string)Loc::getMessage('SH_HASCHANGEFILES_COL_STATUS'), 'default' => true, 'width' => 140],
			['id' => 'FILE', 'name' => (string)Loc::getMessage('SH_HASCHANGEFILES_COL_FILE'), 'default' => true, 'width' => 550],
			['id' => 'ORI', 'name' => (string)Loc::getMessage('SH_HASCHANGEFILES_COL_ORI'), 'default' => true, 'width' => 250],
			['id' => 'CHG', 'name' => (string)Loc::getMessage('SH_HASCHANGEFILES_COL_CHG'), 'default' => true, 'width' => 250],
		];
	}

	/**
	 * Строка грида. Значения колонок — готовый HTML, всё пришедшее с диска
	 * экранировано.
	 *
	 * @param string $project как проект назван в PhpStorm
	 * @param string $projectRoot каталог корня сайта внутри проекта PhpStorm
	 */
	public static function getRow(
		ChangeFile $file,
		int $num,
		string $documentRoot,
		string $project = '',
		string $projectRoot = ''
	): array
	{
		$status = $file->getStatus();
		$original = static::getRelativePath($file->getOriginalPath(), $documentRoot);
		$mirror = static::getRelativePath($file->getMirrorPath(), $documentRoot);

		return [
			'id' => 'row_'.md5($original),
			'columns' => [
				'NUM' => $num,
				'STATUS' => sprintf(
					'<span class="sh-hcf-status sh-hcf-status-%s">%s</span>',
					$status->isNeedFix() ? 'danger' : 'success',
					static::escape((string)Loc::getMessage('SH_HASCHANGEFILES_STATUS_'.str_replace('-', '_', $status->value)))
				),
				'FILE' => sprintf(
					'<b>%s</b><br>%s',
					static::escape(basename($original)),
					static::makePrettyPath(static::escape(dirname($original)))
				),
				'ORI' => static::getFileCell($original, $file->getOriginalModified(), $file->getOriginalSize(), $project, $projectRoot),
				'CHG' => static::getFileCell($mirror, $file->getMirrorModified(), $file->getMirrorSize(), $project, $projectRoot),
			],
			'actions' => [],
			'attrs' => [
				'data-sh-status' => $status->value,
			],
		];
	}

	/**
	 * Ссылка «открыть в PhpStorm» через JetBrains Toolbox.
	 *
	 * Параметры кодируются: путь с пробелом или «&» иначе открыл бы не тот
	 * файл, а то и не тот проект.
	 */
	public static function getUrlPhpStorm(string $project, string $projectRoot, string $path): string
	{
		return 'jetbrains://php-storm/navigate/reference?'.http_build_query(
			[
				'project' => $project,
				'path' => rtrim($projectRoot, '/').$path,
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * Путь от корня сайта. Файл вне корня — как есть.
	 */
	public static function getRelativePath(string $path, string $documentRoot): string
	{
		$path = str_replace('\\', '/', $path);
		$documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');

		if($documentRoot !== '' && str_starts_with($path, $documentRoot.'/'))
		{
			return substr($path, strlen($documentRoot));
		}

		return $path;
	}

	/**
	 * Выделить в пути модуль, компонент, шаблон или расширение: по ним
	 * глазами ищут, чья это правка. Принимает уже экранированный путь.
	 */
	public static function makePrettyPath(string $escapedPath): string
	{
		return (string)preg_replace(
			'#/(modules|components|templates|js|activities)/([^/]+)#',
			'/$1/<b class="sh-hcf-upper">$2</b>',
			$escapedPath,
			1
		);
	}

	private static function getFileCell(string $path, ?int $modified, ?int $size, string $project, string $projectRoot): string
	{
		if(null === $modified)
		{
			return static::escape((string)Loc::getMessage('SH_HASCHANGEFILES_FILE_NOT_FOUND'));
		}

		$date = static::escape(date('d.m.Y H:i:s', $modified));
		$size = static::escape((string)Loc::getMessage('SH_HASCHANGEFILES_FILE_SIZE', ['#SIZE#' => (string)$size]));

		if($project === '')
		{
			return $date.'<br>'.$size;
		}

		return sprintf(
			'<a href="%s">%s</a><br>%s',
			static::escape(static::getUrlPhpStorm($project, $projectRoot, $path)),
			$date,
			$size
		);
	}

	private static function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
