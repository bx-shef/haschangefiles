<?php declare(strict_types=1);

namespace Shef\Haschangefiles\Main;

use Bitrix\Main\Application;
use Bitrix\Main\Config;

class Constants
{
	public const MODULE_ID = 'shef.haschangefiles';

	public static function getModuleId(): string
	{
		return static::MODULE_ID;
	}

	public static function getSettingsOptions(): array
	{
		$list = Config\Configuration::getInstance(static::getModuleId())
			->get('options');

		if(!is_array($list))
		{
			$list = [];
		}

		return $list;
	}

	// region ProjectSelf ////
	/**
	 * Как проект назван в PhpStorm — для ссылок jetbrains:// на странице
	 * учёта правок. Не задан — имя сервера.
	 */
	public static function getProjectSelfName(): string
	{
		$def = (string)Application::getInstance()->getContext()->getServer()->getServerName();

		return (string)Config\Option::get(static::MODULE_ID, 'PRJ_name', $def);
	}

	/**
	 * В каком каталоге проекта PhpStorm лежит корень сайта.
	 */
	public static function getProjectSelfRootPath(): string
	{
		return (string)Config\Option::get(static::MODULE_ID, 'PRJ_path', '');
	}
	// endregion ////
}
