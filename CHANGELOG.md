# shef.haschangefiles change log

## 1.1.0 — 2026-08-25
* Логика детекта дрейфа вынесена в `Shef\Haschangefiles\Main\Utils` (единый
  источник; компонент `shef.haschangefiles:list` делегирует в неё).
* Добавлен CLI-раннер `cli/check-core-changes.php` — детект дрейфа правок ядра
  из консоли/CI (OK/DRIFT, ненулевой код при расхождениях). Для процесса
  обновления Битрикса — см. `docs/infra/bitrix-update.md`.
* Юнит-тесты на `Utils` (needFix / countChangeMarkers / originalPath).

## 1.0.6 — 2023-XX-XX
* -

## 1.1.5 — 2023-09-22
* фиксация кода

## 1.0.1 — 2023-04-19
* Initial release
* Создана страница /local/admin/shef.haschangefiles/list.php
* Создан компонент \Local\Component\Shef\HasChangeFiles\ListComponent
* Обработка события \Shef\Haschangefiles\Integration\Shef\UiClear\Events\onBitrixMenuExtInitTopPanelUserMenu
* Обработка события \Shef\Haschangefiles\Integration\Shef\UiClear\Events\onAdminContextMenuShow

[↑ Содержание](README.md)