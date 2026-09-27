<?php declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;

/**
 * Опции для страницы настроек
 * 
 * языковой файл options.php
 *
 * Tab(prefix)->Option(code) ~> код свойства: prefix_code
 */

$response = ShOptionsConfig::getInstance(
	moduleId: 'shef.haschangefiles',
	indexDoc: 'README.md'
);

if(!$response->isSuccess())
{
	return $response;
}

/** @var ShOptionsConfig $options */
$options = $response->getData()['OPTIONS'];

$options->addTab(
	(new Options\Tab('PRJ'))
		->setName(Loc::getMessage($options->moduleId.'_TAB_PRJ_NAME'))
		->setTitle(Loc::getMessage($options->moduleId.'_TAB_PRJ_TITLE'))
		->addOption(
			(new Options\RowInfo('WARNING_PRJ'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_PRJ_WARNING'))
				->setType(Options\TypeUIAlert::Warning)
		)
		->addOption(
			(new Options\Text('name'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_PRJ_name'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_PRJ_name_descr'))
		)
		->addOption(
			(new Options\Text('path'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_PRJ_path'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_PRJ_path_descr'))
		)
);

return $options->get();