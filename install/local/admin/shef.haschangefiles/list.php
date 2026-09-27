<?php declare(strict_types=1);

// /local/admin/shef.haschangefiles/list.php ////

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Shef\Options\Components\Builder;
use Shef\Options\Main\Utils;

const STOP_STATISTICS = true;
const NO_KEEP_STATISTIC = 'Y';
const NO_AGENT_STATISTIC = 'Y';

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

try
{
	if(!Loader::includeModule('shef.options'))
	{
		throw new LoaderException('module shef.options not loaded');
	}
	
	Utils::getCMainApplication()
		->SetTitle('Fix B24 Code');
	echo '<div id="sh-template" class="g-5">';
	
	(new Builder('shef.haschangefiles:list'))
		->setTemplate('')
		->addOptionCollection('TITLE', 'Public')
		->addOptionCollection('BASE_DIR', '')
		->addOptionCollection('SKIP_DIRS', [
			'/bitrix',
			'/upload',
			'/local',
			'/images',
		])
		->setIsActive(true)
		->setIsHideIcons(true)
		->include()
	;

	(new Builder('shef.haschangefiles:list'))
		->setTemplate('')
		->addOptionCollection('TITLE', 'Bitrix Core')
		->addOptionCollection('BASE_DIR', '/bitrix')
		->addOptionCollection('SKIP_DIRS', [
			'/modules/shef.uiclear',
			'/backup',
			'/blocks',
			'/cache',
			'/managed_cache',
			'/stack_cache',
			'/catalog_export',
			'/fonts',
			'/image_uploader',
			'/mobileapp',
			'/otp',
			'/panel',
			'/sounds',
			'/tmp',
			'/updates'
		])
		->setIsActive(true)
		->setIsHideIcons(true)
		->include()
	;
	
	echo '</div>';
	
}
catch(Throwable $throwable)
{
	ShowError(implode(PHP_EOL, [
		'Throwable: '.$throwable->getMessage(),
		'File: '.$throwable->getFile(),
		'Line: '.$throwable->getLine(),
		'Trace: '.print_r(str_replace($_SERVER["DOCUMENT_ROOT"], '', $throwable->getTraceAsString()), true)
	]));
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");