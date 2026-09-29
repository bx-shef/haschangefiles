<?php declare(strict_types=1);

/**
 * Настройки PhpStorm: что читает Constants.
 *
 * Коды опций — префикс вкладки плюс код опции из options_conf.php: PRJ_name
 * и PRJ_path. Разойдутся — страница настроек сохраняет одно, а отчёт читает
 * другое, и ссылки в PhpStorm молча ведут не туда. Проект по умолчанию —
 * имя сервера: ссылки есть и без настройки.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Shef\Haschangefiles\Main\Constants;

Check::group('умолчания');

Option::$values = [];
Context::$serverName = 'portal.example';
Check::same('проект — имя сервера', Constants::getProjectSelfName(), 'portal.example');
Check::same('корень сайта в проекте — пусто', Constants::getProjectSelfRootPath(), '');

Check::group('из настроек');

Option::set('shef.haschangefiles', 'PRJ_name', 'crm-portal');
Option::set('shef.haschangefiles', 'PRJ_path', '/www');
Check::same('проект', Constants::getProjectSelfName(), 'crm-portal');
Check::same('корень сайта в проекте', Constants::getProjectSelfRootPath(), '/www');

Check::group('коды — те, что пишет страница настроек');

require_once $root.'/tests/stub/options.php';
define('LANGUAGE_ID', 'ru');
$tabs = require $root.'/options_conf.php';
$written = [];
foreach($tabs as $tab)
{
	foreach($tab->getOptionList() as $option)
	{
		if($option instanceof \Shef\Options\Main\Options\Text)
		{
			$written[] = $tab->getCode().'_'.$option->getCode();
		}
	}
}
Check::same('страница настроек пишет PRJ_name и PRJ_path', $written, ['PRJ_name', 'PRJ_path']);

Check::finish();
