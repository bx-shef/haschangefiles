# shef.haschangefiles change log

## 2.0.0 — 2026-09-27

Модуль приведён к канону [shef.options](https://github.com/bx-shef/options):
сборка, тесты, CI, Composer, документация в репозитории. Мажорная версия —
из-за несовместимостей с 1.x.

**Несовместимо с 1.x:**

* пакет Composer — `bxshef/haschangefiles`, тип `bitrix-module` (было
  `shef/haschangefiles`, `bitrix-d7-module` — модуль уехал бы в
  `bitrix/modules/shef.shef.haschangefiles/`); лицензия MIT;
* PHP 8.2, `shef.options` 3.0.0 и выше; `shef.uiclear` больше не нужен;
* отчёт — страница административной части
  `/bitrix/admin/shef_haschangefiles_list.php`, только администратору. Публичная
  `/local/admin/shef.haschangefiles/list.php` прав не проверяла; она и
  компонент `shef.haschangefiles:list` из `/local/components` удалены,
  установщик убирает их на портале;
* пункт на верхней панели (событие `shef.uiclear`) заменён разделом меню
  **Настройки → Правки ядра**;
* `Utils::needFix()` (1.1.0) заменён `Utils::getStatus()` и enum `Status`;
* скриншоты больше не раскладываются в `/bitrix/images`.

**Исправлено:**

* правка считалась «на месте», если совпадал размер: обновление, заменившее
  символ на символ, не замечалось. Теперь сравнивается содержимое;
* страница настроек падала с `shef.options` 3.x («Unknown named parameter»
  `indexDoc`);
* имя файла и путь выводились в отчёт без экранирования;
* тексты подтверждения на странице обновлений подставлялись в скрипт
  строкой — кавычка в переводе ломала кнопку «Установить обновления»;
* обход шёл по символическим ссылкам (ссылка наверх — бесконечная
  рекурсия) и падал на нечитаемом каталоге;
* путь оригинала считался заменой префикса по всему пути, а не в имени
  файла;
* два грида на странице отчёта делили один `GRID_ID`;
* в `installDir` компоненты раскладывались дважды.

**Новое:**

* состояния «Нет пометки» и «Нет файла» отдельно от «Восстановить»;
* `cli/check-core-changes.php`: `DOCUMENT_ROOT`, код `2`, если не найден
  корень сайта; модуль в `/local/modules`;
* обработчик страницы обновлений — `Integration\Main\Events`; прежний класс
  оставлен заглушкой для порталов с 1.x, деинсталляция снимает обе
  регистрации 1.x;
* установщик берёт каталог модуля у себя (`/local/modules` работает),
  стирает настройки при удалении (`savedata = Y` оставляет), отказывается
  ставиться на портал не в UTF-8.

## 1.1.0 — 2026-08-25
* Логика детекта дрейфа вынесена в `Shef\Haschangefiles\Main\Utils` (единый
  источник; компонент `shef.haschangefiles:list` делегирует в неё).
* Добавлен CLI-раннер `cli/check-core-changes.php` — детект дрейфа правок ядра
  из консоли/CI (OK/DRIFT, ненулевой код при расхождениях).

## 1.0.6 — 2023-09-22
* фиксация кода

## 1.0.1 — 2023-04-19
* Initial release
* Создана страница /local/admin/shef.haschangefiles/list.php
* Создан компонент \Local\Component\Shef\HasChangeFiles\ListComponent
* Обработка события \Shef\Haschangefiles\Integration\Shef\UiClear\Events\onBitrixMenuExtInitTopPanelUserMenu
* Обработка события \Shef\Haschangefiles\Integration\Shef\UiClear\Events\onAdminContextMenuShow

[↑ Содержание](README.md)
