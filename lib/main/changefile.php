<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Main;

/**
 * Одна правка ядра: зеркало `has-change_<имя>` и оригинал рядом с ним.
 *
 * Класс самодостаточен, как Utils: его зовут и страница модуля, и
 * консольная проверка без поднятого портала.
 */
class ChangeFile
{
	private readonly string $originalPath;
	private ?Status $status = null;
	private ?int $markers = null;

	public function __construct(private readonly string $mirrorPath)
	{
		$this->originalPath = Utils::originalPath($mirrorPath);
	}

	public function getMirrorPath(): string
	{
		return $this->mirrorPath;
	}

	public function getOriginalPath(): string
	{
		return $this->originalPath;
	}

	public function isOriginalExists(): bool
	{
		return is_file($this->originalPath);
	}

	/** Размер оригинала, байт; нет файла — null. */
	public function getOriginalSize(): ?int
	{
		return static::getSize($this->originalPath);
	}

	/** Размер зеркала, байт; нет файла — null. */
	public function getMirrorSize(): ?int
	{
		return static::getSize($this->mirrorPath);
	}

	/** Время изменения оригинала, unix; нет файла — null. */
	public function getOriginalModified(): ?int
	{
		return static::getModified($this->originalPath);
	}

	/** Время изменения зеркала, unix; нет файла — null. */
	public function getMirrorModified(): ?int
	{
		return static::getModified($this->mirrorPath);
	}

	/** Маркеров правки в оригинале. */
	public function getMarkers(): int
	{
		if(null === $this->markers)
		{
			$content = $this->isOriginalExists() ? file_get_contents($this->originalPath) : '';
			$this->markers = Utils::countChangeMarkers(is_string($content) ? $content : '');
		}

		return $this->markers;
	}

	/**
	 * Маркеры считаются только у совпавших файлов: разошедшийся — DRIFT и
	 * без них, а читать его целиком незачем. Файл в сотни мегабайт иначе
	 * ронял страницу отчёта и консоль по памяти.
	 */
	public function getStatus(): Status
	{
		if(null === $this->status)
		{
			$isExists = $this->isOriginalExists();
			$isSame = $isExists && Utils::isSameContent($this->originalPath, $this->mirrorPath);

			$this->status = Utils::getStatus(
				$isExists,
				$isSame,
				$isSame ? $this->getMarkers() : 0
			);
		}

		return $this->status;
	}

	public function isNeedFix(): bool
	{
		return $this->getStatus()->isNeedFix();
	}

	private static function getSize(string $path): ?int
	{
		if(!is_file($path))
		{
			return null;
		}

		$size = filesize($path);

		return false === $size ? null : $size;
	}

	private static function getModified(string $path): ?int
	{
		if(!is_file($path))
		{
			return null;
		}

		$time = filemtime($path);

		return false === $time ? null : $time;
	}
}
