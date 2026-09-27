<?php declare(strict_types=1);

/**
 * Настраиваемые параметры модуля
 *
 * * requireModules -> обязательные модули
 * * requirePhpExt -> обязательные расширения PHP
 * * registerAutoLoadClasses -> авто подгрузка классов
 * * registerNamespace -> авто подгрузка Namespace
 * * options -> опции устанавливаемые через окружение
 * * installEvents -> события для установки
 * * installDir -> пути установки файлов
 * * controllers -> контроллеры для ajax
 *
 * Классы самого модуля (Shef\Haschangefiles\...) в registerNamespace не
 * нужны: ядро отображает их в lib/ по соглашению. Чужих библиотек у модуля
 * нет.
 *
 * installDir пуст сознательно: страница отчёта в /bitrix/admin не
 * копируется, а пишется (Main\AdminPage) — путь в ней зависит от того, где
 * стоит модуль. Компонентов, скриптов и стилей модуль не раскладывает.
 */

return [
	'requireModules' => [
		'value' => [
			'shef.options',
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
				'isCompatible' => true,
				'from' => [
					'module' => 'main',
					'event' => 'OnAdminContextMenuShow'
				],
				'to' => [
					'module' => 'shef.haschangefiles',
					'class' => '\Shef\Haschangefiles\Integration\Main\Events',
					'function' => 'onAdminContextMenuShow'
				]
			],
		],
		'readonly' => true,
	],
	'installDir' => [
		'value' => [],
		'readonly' => true,
	],
	'controllers' => [
		'value' => [
			'namespaces' => [],
		],
		'readonly' => true,
	]
];
