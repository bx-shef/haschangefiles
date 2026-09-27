<?php declare(strict_types=1);

/**
 * Настраиваемые параметры модуля
 *
 * * requireModules -> обязательные модулей
 * * requirePhpExt -> обязательные расширения PHP
 * * registerAutoLoadClasses -> авто подгрузка классов
 * * registerNamespace -> авто подгрузка Namespace
 * * options -> опции устанавливаемые через окружение
 * * installEvents -> события для установки
 * * installDir -> пути установки файлов
 * * controllers -> контроллеры для ajax
 * * ui.entity-selector -> провайдер для диалога выбора сущностей
 * * intranet.customSection -> указывает провайдер страниц левого меню Если нужно использовать из другого модуля - то в installLeftMenu[] указываем moduleId
 * * installLeftMenu -> разделы и страницы в левом меню
 *
 * @memo installLeftMenu[].pages[].settingsRow не серилизовать.
 * @memo installLeftMenu[].code и installLeftMenu[].pages[].code писать без разделителей
 * @memo installLeftMenu[].pages[].settingsRow первый параметр компонет. Остальное смотреть в контроллере intranet.customSection
 *
 */

return [
	'requireModules' => [
		'value' => [
			'shef.options',
			'shef.uiclear',
		],
		'readonly' => true,
	],
	'requirePhpExt' => [
		'value' => [],
		'readonly' => true,
	],
	'registerAutoLoadClasses' => [
		'value' => [],
		'readonly' => true,
	],
	'registerNamespace' => [
		'value' => [],
		'readonly' => true,
	],
	'options' => [
		'value' => [],
		'readonly' => true,
	],
	'installEvents' => [
		'value' => [
			[
				'isCompatible' => false,
				'from' => [
					'module' => 'shef.uiclear',
					'event' => 'onBitrixMenuExtInitTopPanelUserMenu'
				],
				'to' => [
					'module' => 'shef.haschangefiles',
					'class' => '\Shef\Haschangefiles\Integration\Shef\UiClear\Events',
					'function' => 'onBitrixMenuExtInitTopPanelUserMenu'
				]
			],
			[
				'isCompatible' => true,
				'from' => [
					'module' => 'main',
					'event' => 'OnAdminContextMenuShow'
				],
				'to' => [
					'module' => 'shef.haschangefiles',
					'class' => '\Shef\Haschangefiles\Integration\Shef\UiClear\Events',
					'function' => 'onAdminContextMenuShow'
				]
			]
		],
		'readonly' => true,
	],
	'installDir' => [
		'value' => [
			[
				'type' => 'components',
				'from' => '/install/components',
				'to' => '/local/components',
				'customPathUnInstall' => [],
				'isNeedUnInstall' => true,
			],
			[
				'type' => 'localAdmin',
				'from' => '/install/local/admin',
				'to' => '/local/admin',
				'customPathUnInstall' => [],
				'isNeedUnInstall' => true,
			],
			[
				'type' => 'components',
				'from' => '/install/components',
				'to' => '/local/components',
				'customPathUnInstall' => [],
				'isNeedUnInstall' => true,
			],
			[
				'type' => 'images',
				'from' => '/install/images',
				'to' => '/bitrix/images',
				'customPathUnInstall' => [],
				'isNeedUnInstall' => true,
			],
		],
		'readonly' => true,
	],
	'controllers' => [
		'value' => [],
		'readonly' => true,
	]
];