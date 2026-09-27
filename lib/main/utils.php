<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Main;

/**
 * Единый источник логики учёта правок ядра (`has-change_*`).
 *
 * Раньше эта логика жила только внутри компонента `shef.haschangefiles:list`
 * (админ-страница). Вынесена сюда, чтобы её могли переиспользовать и компонент,
 * и CLI-раннер (`cli/check-core-changes.php`) — один источник истины для
 * детекта дрейфа правок после обновления Битрикса.
 */
class Utils
{
    /** Префикс файла-зеркала правки: `has-change_<оригинал>`. */
    public const FIND_PREFIX_FILE = 'has-change_';

    /**
     * Варианты маркера правки в теле файла (в т.ч. исторические опечатки).
     * Блок правки: `// change ////` … `// change stop ////`.
     *
     * @return string[]
     */
    public static function changeMarkers(): array
    {
        return ['// change', '//change', '//cahnge', '// cahnge'];
    }

    /** Является ли имя файла зеркалом правки (`has-change_*`). */
    public static function isMirrorName(string $fileName): bool
    {
        return str_starts_with($fileName, self::FIND_PREFIX_FILE);
    }

    /** Путь оригинала по пути зеркала (убираем префикс из имени файла). */
    public static function originalPath(string $mirrorPath): string
    {
        $dir = dirname($mirrorPath);
        $name = basename($mirrorPath);
        if (str_starts_with($name, self::FIND_PREFIX_FILE)) {
            $name = substr($name, strlen(self::FIND_PREFIX_FILE));
        }

        return $dir . '/' . $name;
    }

    /** Сколько маркеров правки в тексте (сумма по всем вариантам). */
    public static function countChangeMarkers(string $content): int
    {
        $count = 0;
        foreach (self::changeMarkers() as $marker) {
            $count += substr_count($content, $marker);
        }

        return $count;
    }

    /**
     * Нужно ли переприменить правку: оригинал разошёлся с зеркалом.
     *
     * Эвристика (историческая, сохранена 1:1 из компонента): «правка на месте»
     * (нужды в фиксе нет) только если оба файла есть, размеры совпадают И в
     * оригинале присутствует хотя бы один маркер правки. Иначе — нужен фикс:
     * оригинал затёрт обновлением, или размеры разошлись, или маркеров нет.
     *
     * @param bool $oriExists  существует ли оригинал
     * @param bool $chgExists  существует ли зеркало
     * @param int  $oriSize    размер оригинала (байт)
     * @param int  $chgSize    размер зеркала (байт)
     * @param int  $oriMarkers число маркеров правки в оригинале
     * @return bool true — правку нужно переприменить (drift)
     */
    public static function needFix(
        bool $oriExists,
        bool $chgExists,
        int $oriSize,
        int $chgSize,
        int $oriMarkers
    ): bool {
        if (!$oriExists || !$chgExists) {
            return true;
        }
        if ($oriSize !== $chgSize) {
            return true;
        }

        return !($oriMarkers > 0);
    }
}
