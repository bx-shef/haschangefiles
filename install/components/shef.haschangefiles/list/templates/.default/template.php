<?php
declare(strict_types=1);

defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var \Local\Component\Shef\HasChangeFiles\ListComponent $component */

Extension::load([
	'ui.hint',
	'shef-uiclear.bootstrap',
	'shef-uiclear.bootstrap-card',
]);

Loc::loadMessages(__FILE__);

// region GRID ////
foreach($arResult['ROWS'] as $index => $data)
{
	$actions = [];
	$arResult['ROWS'][$index] = [
		'id' => $data['ID'],
		'columns' => $data,
		'actions' => $actions,
		'attrs' => [
			'sh-status' => $data['isNeedFix'] === true ? 'danger' : 'success'
		]
	];
}

$grid = (new \Shef\Options\Components\Builder('bitrix:main.ui.grid'))
	->setTemplate('')
	->setOptionCollection([
		'GRID_ID' => $arParams['GRID_ID'],
		'COLUMNS' => $arResult['COLUMNS'],
		'ROWS' => $arResult['ROWS'],
		'~NAV_PARAMS' => ['SHOW_ALWAYS' => false],
		'SHOW_ROW_CHECKBOXES' => false,
		'SHOW_GRID_SETTINGS_MENU' => false,
		'SHOW_PAGINATION' => false,
		'SHOW_SELECTED_COUNTER' => false,
		'SHOW_TOTAL_COUNTER' => true,
		'TOTAL_ROWS_COUNT' => $arResult['TOTAL_ROWS_COUNT'],
		'ALLOW_COLUMNS_SORT' => false,
		'ALLOW_COLUMNS_RESIZE' => false,
		'AJAX_MODE' => 'Y',
		'AJAX_OPTION_JUMP' => 'N',
		'AJAX_OPTION_STYLE' => 'N',
		'AJAX_OPTION_HISTORY' => 'N'
	])
	->setIsActive(true)
	->setIsHideIcons(true)
;
// endregion ////

if(count($arResult['ERRORS']) > 0):?>
<div class="row g-5 mb-4"><div class="col-12 order-0">
	<?php foreach($arResult['ERRORS'] as $error):
		ShowError($error);
	endforeach;?>
</div></div>
<?php endif;?>
<div class="row mb-5 g-5" id="<?=$arParams['NODE_ID']?>">
	<div class="col-md-12 order-0">
		<div class="card">
			<div class="card-header">
				<h3 class="card-title my-1"><?=$arParams['TITLE'];?></h3>
			</div>
			<?php $grid->include();?>
		</div>
	</div>
</div>
<script>
	BX.ready(() => {
		BX.UI.Hint.init(BX('<?=$arParams['NODE_ID']?>'));
	});
</script>