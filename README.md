# shef.haschangefiles

Модуль Битрикс24 «коробки» и БУС для учёта правок ядра. Правка ядра — файл
Битрикса, изменённый руками; обновление платформы перезапишет его молча.
Модуль помнит, **какие** файлы правили, и после обновления показывает, какие
правки надо вернуть: отчётом в административной части и проверкой из
консоли с кодом возврата.

Опирается на [shef.options](https://github.com/bx-shef/options): его нужно
поставить первым.

# Что нужно для установки

| | |
|---|---|
| PHP | 8.2 и выше |
| Главный модуль Битрикс | 22.600.300 и выше |
| Модуль `shef.options` | 3.0.0 и выше |
| Кодировка портала | **только UTF-8** |
| Расширение PHP | `mbstring` |

# Установка

**Порядок шагов важен:** сначала `shef.options`, потом файлы этого модуля, потом
установка в административном разделе.

## Через Composer

```bash
composer require bxshef/haschangefiles
```

Модуль развернётся в `bitrix/modules/shef.haschangefiles/` сам, вместе с ним
приедет `bxshef/options`. Composer 2.2+ требует разрешить плагин
раскладки — один раз, в `composer.json` проекта:

```json
{
	"config": {
		"allow-plugins": {
			"composer/installers": true
		}
	}
}
```

## Из архива

Скачайте `shef.haschangefiles.zip` со [страницы релизов](https://github.com/bx-shef/haschangefiles/releases)
и распакуйте в `bitrix/modules/`. Должно получиться
`bitrix/modules/shef.haschangefiles/` — именно через точку.

## Дальше — в административном разделе

1. **Настройки → Marketplace → Установленные решения** → «[SH] Правки ядра» →
   **Установить**.
2. По желанию — **Настройки → Настройки продукта → Настройки модулей →
   [SH] Правки ядра** → вкладка «PhpStorm»: имя проекта и корень сайта в нём.
   Тогда даты в отчёте открывают файл в PhpStorm через JetBrains Toolbox.

# Как пользоваться

1. Правку в файле ядра обрамляете блоком `// change ////` … `// change stop ////`.
2. Файл с правкой копируете рядом как `has-change_<имя>` — это зеркало.
3. После обновления Битрикса открываете **Настройки → Правки ядра → Учёт
   правок**: «Восстановить» — файл разошёлся с зеркалом, правку надо вернуть
   и снова сохранить копию.

Перед установкой обновлений модуль напоминает сам: на странице
**Обновление платформы** кнопки «Установить обновления» сначала спрашивают,
зафиксированы ли правки.

Из консоли — то же, ответом кодом возврата:

```bash
php bitrix/modules/shef.haschangefiles/cli/check-core-changes.php   # 0 — всё на месте, 1 — есть что вернуть
```

# Документация

Вся документация — в репозитории:

* [как вести правки ядра](https://github.com/bx-shef/haschangefiles/blob/main/docs/1_usage.md)
* [проверка из консоли](https://github.com/bx-shef/haschangefiles/blob/main/docs/2_cli.md)
* [безопасность](https://github.com/bx-shef/haschangefiles/blob/main/docs/security.md)
* [запускаемые примеры](https://github.com/bx-shef/haschangefiles/blob/main/examples/README.md)
* [проверка на портале](https://github.com/bx-shef/haschangefiles/blob/main/docs/portal-check.md)
* [change log](https://github.com/bx-shef/haschangefiles/blob/main/CHANGELOG.md)

# Лицензия

[MIT](https://github.com/bx-shef/haschangefiles/blob/main/LICENSE)
