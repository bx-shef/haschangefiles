# Проверка на портале

Всё, что ниже рантайма Битрикса, тестами не закрыть: установка, права, меню,
страница обновлений, поведение при обновлении модуля. Проверять это
приходится руками — и лучше по списку, потому что забытый шаг находит не
разработчик, а клиент.

Процедура рассчитана на **отдельный стенд**, а не на боевой портал. Шаги
«удалить модуль» и «поставить на CP1251» на рабочем портале делать нельзя.

Раскладка репозитория — в [module-structure.md](module-structure.md), сборка —
в [build-and-install.md](build-and-install.md).

## Что понадобится

| | |
|---|---|
| портал | «коробка» Битрикс24 или БУС, главный модуль **22.600.300** и выше |
| PHP | **8.2** и выше, расширение `mbstring` |
| кодировка | **только UTF-8** |
| `shef.options` | **3.0.0** и выше, установлен |
| доступ | администратор портала и доступ к файлам по ssh |
| архив | со страницы релиза либо собранный `./build.sh` |

Для сценария «обновление» нужен стенд, где уже стоит **1.x** — на нём
проверяется то, ради чего 2.0.0 сделана мажорной.

## Перед началом

Снимите копию каталога модуля и настроек — шаги с удалением необратимы:

```bash
cp -a /var/www/portal/bitrix/modules/shef.haschangefiles /tmp/shef.haschangefiles.before 2>/dev/null
mysqldump -u… portal b_option --where="MODULE_ID='shef.haschangefiles'" > /tmp/opt.before.sql
mysqldump -u… portal b_module_to_module --where="TO_MODULE_ID='shef.haschangefiles'" > /tmp/events.before.sql
```

И положите на стенд две правки-образца — по ним видно, что отчёт работает:

```bash
cd /var/www/portal
printf '<?php\n// change ////\n$a = 2;\n// change stop ////\n' > bitrix/php_interface/sh_test.php
cp bitrix/php_interface/sh_test.php bitrix/php_interface/has-change_sh_test.php
printf '<?php\n$a = 1;\n' > bitrix/php_interface/sh_drift.php
printf '<?php\n// change ////\n$a = 2;\n' > bitrix/php_interface/has-change_sh_drift.php
```

## 0. Архив — тот самый

Архив собирается **побайтово одинаково** у всех, кто взял тот же коммит:

```bash
git clone https://github.com/bx-shef/haschangefiles.git
cd haschangefiles && git checkout <тег проверяемой версии>
./build.sh                       # последняя строка напечатает sha256
sha256sum /путь/к/скачанному/shef.haschangefiles.zip
```

Хеши обязаны совпасть. Первым уровнем внутри архива — ровно `shef.haschangefiles/`.

## A. Чистая установка

1. Убедиться, что `shef.options` стоит и его версия 3.0.0 или выше.
2. Распаковать в `bitrix/modules/`, чтобы получилось
   `bitrix/modules/shef.haschangefiles/`.
3. **Marketplace → Установленные решения** → «[SH] Правки ядра» → установить.

**Ожидается:** «Модуль успешно установлен»; появился
`/bitrix/admin/shef_haschangefiles_list.php` — одна строка `require` на
`admin/list.php` модуля **там, где модуль стоит** (поставили в
`/local/modules` — путь `/local/modules/…`); **не** появились
`/local/components/shef.haschangefiles`, `/local/admin/shef.haschangefiles`,
`/bitrix/images/shef.haschangefiles`.

**Отдельно:** на стенде без `shef.options` (или со старым 2.x) установка
обязана отказать с текстом про `shef.options` и версию.

## B. Обновление с 1.x — главный сценарий 2.0.0

Делается на стенде, где стоит 1.x (1.0.x или 1.1.0).

1. Заменить каталог модуля содержимым новой версии **целиком** (старый убрать,
   новый распаковать).
2. Открыть `/bitrix/admin/settings.php?mid=shef.haschangefiles`.

**Ожидается:**

* страница настроек открывается. В 1.x `options_conf.php` передавал
  `indexDoc`, и с `shef.options` 3.x страница падала бы с «Unknown named
  parameter»;
* имя проекта и корень PhpStorm — прежние;
* в меню **Настройки → Правки ядра** есть, а `/bitrix/admin/shef_haschangefiles_list.php`
  появился сам, при первом построении меню администратором;
* на странице обновлений кнопка «Правки ядра» и подтверждение работают —
  через прежнюю регистрацию обработчика (класс 1.x оставлен заглушкой) и
  **ровно по одному разу**;
* `shef.uiclear`, если он на стенде есть, не падает: его событие получает
  «нечего добавить».

3. Разложить файлы установщиком без удаления модуля (удаление стёрло бы
   настройки):

```bash
cd /var/www/portal && php -r '
$_SERVER["DOCUMENT_ROOT"] = getcwd();
define("NO_KEEP_STATISTIC", true); define("NOT_CHECK_PERMISSIONS", true);
require "bitrix/modules/main/include/prolog_before.php";
require "bitrix/modules/shef.haschangefiles/install/index.php";
var_dump((new shef_haschangefiles())->InstallFiles());'
```

**Ожидается:** `bool(true)`; `/local/admin/shef.haschangefiles` и
`/local/components/shef.haschangefiles` убраны — страница 1.x больше не
открывается (404), и отчёт без проверки прав с ней ушёл.

## C. Отчёт

Открыть **Настройки → Правки ядра → Учёт правок**.

**Ожидается:**

* два раздела — «Публичная часть» и «Ядро Битрикса», у каждого свой грид;
* `sh_test.php` — «На месте», `sh_drift.php` — «Восстановить» красным;
* путь `/bitrix/php_interface`, дата и размер обоих файлов;
* под пользователем **без** прав администратора
  `/bitrix/admin/shef_haschangefiles_list.php` показывает форму входа, пункта
  в меню нет.

Время страницы на портале с большим `/bitrix` — секунды: обход честный,
кеша нет. Больше полминуты — проверьте, не попали ли в обход тяжёлые
каталоги проекта, и сообщите.

## D. PhpStorm

На машине с JetBrains Toolbox: в настройках модуля указать имя проекта и
корень сайта в нём, открыть отчёт, щёлкнуть дату у `sh_drift.php`.

**Ожидается:** файл открывается в PhpStorm.

## E. Страница обновлений

Открыть **Обновление платформы** (`/bitrix/admin/update_system.php`).

**Ожидается:**

* в контекстном меню кнопка «Правки ядра», открывает отчёт в новой вкладке;
* «Установить обновления» сначала показывает диалог «Важно» со ссылкой на
  отчёт; «Отмена» — обновление **не** начинается; подтверждение —
  начинается (проверять на стенде, где обновления есть и их не жалко);
* на других страницах административной части кнопки нет.

## F. Консоль

```bash
cd /var/www/portal
php bitrix/modules/shef.haschangefiles/cli/check-core-changes.php; echo "код $?"
BASE_DIR=/bitrix/php_interface php bitrix/modules/shef.haschangefiles/cli/check-core-changes.php --diff
```

**Ожидается:** `OK` у `sh_test.php`, `DRIFT` у `sh_drift.php`, код `1`; с
`--diff` — разница файлов. `curl https://<портал>/bitrix/modules/shef.haschangefiles/cli/check-core-changes.php`
— 403 от веб-сервера.

## G. Удаление

1. **Marketplace → Установленные решения** → «[SH] Правки ядра» → удалить.

**Ожидается:**

* настроек модуля в `b_option` нет, настройки `shef.options` — на месте;
* в `b_module_to_module` не осталось обработчиков с
  `TO_MODULE_ID='shef.haschangefiles'` — в том числе двух из 1.x;
* `/bitrix/admin/shef_haschangefiles_list.php` удалён, остальные файлы
  `/bitrix/admin/` на месте. Если перед удалением положить на место заглушки
  свой файл — он остаётся;
* файлы `has-change_*` на сайте **остались**: это данные проекта.

## H. Портал в CP1251

Установка обязана отказать с текстом про UTF-8. На современных ядрах ветка
недостижима — `Application::isUtfMode()` возвращает `true` без условий, — тогда
в бланке отмечается «пропущено», и это верный ответ.

## I. Примеры на живом ядре

```bash
for e in check report; do DOCUMENT_ROOT=/var/www/portal php examples/$e.php || echo "FAIL $e"; done
```

**Ожидается:** дважды `ГОТОВО: …`, ни одного `FAIL`, `Warning`, `Deprecated`.

## После проверки

```bash
rm /var/www/portal/bitrix/php_interface/{sh_test,has-change_sh_test,sh_drift,has-change_sh_drift}.php
```

## Бланк результата

```
Версия: ____  Коммит: ____  sha256 архива сошёлся: да / нет
Ядро main: ____  PHP: ____  shef.options: ____

0. Архив .................................. ок / не ок
A. Чистая установка ....................... ок / не ок
   без shef.options — отказ ............... ок / не ок
B. Обновление с 1.x ....................... ок / не ок / нет стенда
C. Отчёт .................................. ок / не ок
   не администратору — закрыт ............. ок / не ок
D. PhpStorm ............................... ок / не ок / нет Toolbox
E. Страница обновлений .................... ок / не ок
F. Консоль ................................ ок / не ок
G. Удаление ............................... ок / не ок
H. CP1251 ................................. ок / пропущено
I. Примеры на живом ядре .................. ок / не ок

Замечания:
```
