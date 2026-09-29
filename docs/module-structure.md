# Раскладка репозитория

Файл про устройство репозитория. Опорные точки модуля — в [CLAUDE.md](../CLAUDE.md),
процесс — в [CONTRIBUTING.md](../CONTRIBUTING.md), сборка — в
[build-and-install.md](build-and-install.md).

## Модуль лежит в корне, и это вынужденно

Composer разворачивает в целевой каталог **корень пакета целиком** и подкаталоги
выбирать не умеет. Поэтому `lib/`, `install/`, `lang/` лежат прямо в корне
репозитория, рядом с `build.sh` и `.github/`.

Плата за это — два списка в шапке `build.sh`:

* **SHIP** — уезжает на портал и в Composer-пакет;
* **KEEP** — остаётся в репозитории.

**Файл, не попавший ни в один список, роняет сборку.** Тот же список продублирован
в `.gitattributes` через `export-ignore`; списки обязаны совпадать, сверяется
автоматически, см. `check_gitattributes`.

## Что где лежит

| путь | | что это |
|---|---|---|
| `install/index.php` | SHIP | установщик, класс `shef_haschangefiles extends CModule` |
| `install/version.php` | SHIP | `VERSION` и `VERSION_DATE` — источник истины о версии |
| `admin/menu.php` | SHIP | меню «Правки ядра»; ядро подключает его само, из каталога модуля |
| `admin/list.php` | SHIP | отчёт; открывается заглушкой `/bitrix/admin/shef_haschangefiles_list.php`, которую пишет установщик (`Main\AdminPage`) |
| `cli/check-core-changes.php` | SHIP | та же проверка из консоли, ответ — кодом возврата |
| `.settings.php` | SHIP | зависимости и обработчик страницы обновлений |
| `include.php` | SHIP | точка входа: подключает `autoload.php` |
| `autoload.php` | SHIP | подключает `shef.options` |
| `default_option.php` | SHIP | умолчания настроек |
| `options.php`, `options_conf.php` | SHIP | страница настроек на `ShOptionsConfig` из `shef.options` |
| `lib/` | SHIP | классы модуля, **имена файлов строго строчными** |
| `lang/ru/` | SHIP | языковые файлы, зеркалят структуру `lib/` и `admin/` |
| `README.md`, `CHANGELOG.md`, `LICENSE` | SHIP | |
| `composer.json` | SHIP | манифест пакета `bxshef/haschangefiles` |
| `docs/` | KEEP | вся документация |
| `build.sh` | KEEP | сборка и проверки |
| `tests/` | KEEP | тесты и заглушки ядра |
| `examples/` | KEEP | запускаемые примеры |
| `.claude/skills/` | KEEP | навыки агента: навыки линейки — **копия** из `bx-shef/options` (`MANIFEST`, раскладывает `sync.sh --to`); `shef-core-change` и `shef-restore-core-changes` — **локальные**, правятся здесь (`LOCAL.MANIFEST`, `sync.sh --local`) |
| `.github/` | KEEP | CI и релиз |
| `CONTRIBUTING.md`, `CLAUDE.md` | KEEP | процесс и памятка агенту |
| `.gitattributes`, `.gitignore` | KEEP | |

## Классы

| класс | что делает | ядро |
|---|---|---|
| `\Shef\Haschangefiles\Main\Utils` | маркеры правки, путь оригинала по зеркалу, сравнение файлов, состояние | не нужно |
| `\Shef\Haschangefiles\Main\Status` | enum состояния: `OK`, `DRIFT`, `NO-MARKERS`, `NO-ORIGINAL` | не нужно |
| `\Shef\Haschangefiles\Main\ChangeFile` | одна правка: зеркало и оригинал, размеры, даты, состояние | не нужно |
| `\Shef\Haschangefiles\Main\Scanner` | поиск зеркал под каталогом; границы обхода для страницы и консоли (`getScope()`) | не нужно |
| `\Shef\Haschangefiles\Main\Report` | названия разделов, колонки и строки отчёта | `Loc` |
| `\Shef\Haschangefiles\Main\AdminPage` | заглушка страницы отчёта в `/bitrix/admin` | не нужно |
| `\Shef\Haschangefiles\Main\Constants` | id модуля, настройки PhpStorm | да |
| `\Shef\Haschangefiles\Integration\Main\AdminMenu` | раздел в меню административной части | `Loc` |
| `\Shef\Haschangefiles\Integration\Main\Events` | кнопка и подтверждение на странице обновлений | да |
| `\Shef\Haschangefiles\Integration\Shef\UiClear\Events` | заглушка для порталов с 1.x | да |

«Не нужно» — это обещание, а не случайность: `Utils`, `Status`, `ChangeFile`
и `Scanner` подключает явным `require_once` консольная проверка, которая
ядро не поднимает, а `AdminPage` — установщик, где на автозагрузку классов
модуля полагаться нельзя. Держат это `tests/scanner_test.php`,
`tests/cli_test.php` и `tests/adminpage_test.php`: они подключают классы
без заглушек ядра.

## Нижний регистр в `lib/` обязателен

`Bitrix\Main\Loader` отображает класс в путь **строчными**, разбирая первые два
сегмента namespace как id модуля: `Shef\Haschangefiles\Main\Utils` ищется как
`bitrix/modules/shef.haschangefiles/lib/main/utils.php`. Поэтому свой
namespace в `registerNamespace` не нужен, и ключ пуст.

На macOS заглавная буква сходит с рук, на боевом Linux класс просто не найдётся.
Проверяется в `build.sh`, `check_lowercase`, и в `tests/autoload_test.php`.

## Установщик ничего не копирует

`installDir` в `.settings.php` пуст. В 1.x он раскладывал три каталога:
компонент в `/local/components`, публичную страницу в `/local/admin` и
скриншоты в `/bitrix/images`. Теперь:

* отчёт — страница административной части, грид на ней строит сама
  страница, компонента нет;
* страница открывается заглушкой в `/bitrix/admin`, а её **пишет**
  `Main\AdminPage` — путь в ней зависит от того, где стоит модуль;
* документация живёт в репозитории, скриншоты ушли вместе с ней.

Каталоги 1.x установщик убирает и при установке, и при удалении
(`getLegacyDirList()`).

## Документация не едет на портал

Документация живёт в репозитории. В поставке остаётся только `README.md` — как
readme пакета, — и все ссылки из него ведут на GitHub.
