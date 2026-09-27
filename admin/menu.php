<?php declare(strict_types=1);

/**
 * Раздел модуля в меню административной части.
 *
 * Ядро подключает этот файл само — для каждого установленного модуля, при
 * построении меню. Регистрировать его не нужно, копировать тоже: файл
 * читается прямо из каталога модуля.
 *
 * Отчёт — только администратору: в нём пути к файлам ядра.
 *
 * @see \Shef\Haschangefiles\Integration\Main\AdminMenu
 */

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Shef\Haschangefiles\Integration\Main\AdminMenu;

defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true || die();

/** @var \CUser $USER */
global $USER;

if(!($USER instanceof \CUser) || !$USER->IsAdmin())
{
	return false;
}

if(!Loader::includeModule('shef.haschangefiles'))
{
	return false;
}

$context = Context::getCurrent();

// Портал, обновлённый с 1.x заменой файлов, установщик не проходил, и
// страницы отчёта в /bitrix/admin у него нет. Не вышло положить — меню всё
// равно строим: настройки от неё не зависят.
AdminMenu::ensureListPage(
	(string)Application::getDocumentRoot(),
	dirname(__DIR__)
);

return AdminMenu::build(
	lang: (string)($context?->getLanguage() ?: LANGUAGE_ID),
);
