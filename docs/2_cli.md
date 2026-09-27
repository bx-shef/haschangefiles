# Проверка из консоли

`cli/check-core-changes.php` — то же, что отчёт «Правки ядра», но ответом
служит код возврата. Для шага после обновления Битрикса, для CI, для
cron-сторожа.

```bash
php bitrix/modules/shef.haschangefiles/cli/check-core-changes.php
```

```
OK           /bitrix/components/bitrix/news.list/templates/.default/template.php
DRIFT        /bitrix/modules/crm/lib/order.php
NO-ORIGINAL  /bitrix/js/ui/old.js
----
Зеркал найдено: 3; восстановить: 2
```

## Параметры

| | |
|---|---|
| `DOCUMENT_ROOT=<путь>` | корень сайта. Не задан — на четыре уровня выше скрипта: `<корень>/bitrix/modules/shef.haschangefiles/cli` или `<корень>/local/modules/shef.haschangefiles/cli` |
| `BASE_DIR=/bitrix` | обходить не весь сайт, а каталог от корня |
| `--diff` | для каждого `DRIFT` напечатать `diff -u` зеркала и файла — что вернуть |

## Код возврата

| код | |
|---|---|
| `0` | всё на месте |
| `1` | есть что восстановить: `DRIFT`, `NO-MARKERS` или `NO-ORIGINAL` |
| `2` | не найден корень сайта или `BASE_DIR` |

`2` отдельно от `1` сознательно: «не нашёл, где искать» не должно выглядеть
ни «всё хорошо», ни «правки пропали».

## Чем отличается от отчёта

* Ядро Битрикса и база не поднимаются: скрипт подключает четыре класса
  (`Status`, `Utils`, `ChangeFile`, `Scanner`) явным `require_once` и
  сравнивает только файлы. Работает и на копии сайта без портала — в CI по
  репозиторию проекта.
* Обходит весь `BASE_DIR` с пропуском по именам (`\Shef\Haschangefiles\Main\Scanner::DEFAULT_SKIP_NAMES`),
  без разделов отчёта: `/local` здесь тоже обходится.
* Ничего не меняет.

Из браузера не запускается: каталог модуля закрыт веб-сервером, а сам
скрипт на любой SAPI, кроме `cli`, отвечает 403.

## После обновления

```bash
cd /home/bitrix/www
php bitrix/modules/shef.haschangefiles/cli/check-core-changes.php --diff > /tmp/core-changes.txt
echo $?
```

Код `1` — открыть отчёт или `/tmp/core-changes.txt` и вернуть правки, как
описано в [Как вести правки ядра](1_usage.md#4-возвращаем-правку).
