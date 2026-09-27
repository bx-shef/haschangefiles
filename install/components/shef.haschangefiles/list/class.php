<?php declare(strict_types=1);

namespace Local\Component\Shef\HasChangeFiles;

defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\IO\FileNotFoundException;
use Bitrix\Main\IO\FileOpenException;
use Bitrix\Main\Loader;
use Bitrix\Main\Error;
use Bitrix\Main\Application;
use Bitrix\Main\IO;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Shef\Options\Components;
use Shef\Haschangefiles\Main\Constants;

Loc::loadMessages(__FILE__);

if(!Loader::includeModule('shef.options'))
{
	die('Can\'t include module shef.options');
}

enum PrefixContent: string
{
	case TP_1 = '// change';
	case TP_2 = '//change';
	case TP_3 = '//cahnge';
	case TP_4 = '// cahnge';
}

class ListComponentRow
{
	private ListComponentFile $chg;
	private ListComponentFile $ori;
	
	public function __construct(
		string $pathChangeFile
	)
	{
		$this->chg = new ListComponentFile($pathChangeFile);
		$this->ori = new ListComponentFile(static::getOriPath($pathChangeFile));
	}
	
	protected static function getOriPath(string $pathChangeFile): string
	{
		return str_replace(ListComponent::FindPrefixFile, '', $pathChangeFile);
	}
	
	public function getOri(): ListComponentFile
	{
		return $this->ori;
	}
	
	public function getChg(): ListComponentFile
	{
		return $this->chg;
	}
	
	/**
	 * Проверяет совпадение ORI и CHG файлов
	 *
	 * @throws FileNotFoundException
	 * @throws FileOpenException
	 */
	public function isNeedFix(): bool
	{
		if(!$this->getOri()->isExists())
		{
			return true;
		}
		elseif(!$this->getChg()->isExists())
		{
			return true;
		}
		elseif($this->getChg()->getSize() !== $this->getOri()->getSize())
		{
			return true;
		}
		
		if(
			$this->getOri()->getContentComments() > 0
			&& $this->getChg()->getSize() === $this->getOri()->getSize()
		)
		{
			return false;
		}
		
		return true;
	}
}

class ListComponentFile
{
	private readonly IO\File $file;
	
	public function __construct(string $pathFile)
	{
		$this->file = new IO\File($pathFile);
	}
	
	public function getName(): string
	{
		return $this->getFile()->getName();
	}
	
	public function getDirectoryName(): string
	{
		return $this->getFile()->getDirectoryName();
	}
	
	public function getClearDirectoryName(): string
	{
		return str_replace(Application::getDocumentRoot(), '', $this->getDirectoryName());
	}
	
	public function isExists(): bool
	{
		return $this->file->isExists();
	}
	
	/**
	 * @throws FileNotFoundException
	 */
	public function getLastChange(): string
	{
		if($this->isExists())
		{
			return (DateTime::createFromTimestamp(
				$this->file->getModificationTime()
			))->toString();
		}
		
		return '-';
	}
	
	/**
	 * @throws FileNotFoundException
	 * @throws FileOpenException
	 */
	public function getSize(): float
	{
		if($this->isExists())
		{
			return $this->file->getSize();
		}
		
		return 0;
	}
	
	public function getFile(): IO\File
	{
		return $this->file;
	}
	
	/**
	 * @throws FileNotFoundException
	 */
	public function getContentComments(): int
	{
		$count = 0;
		
		if(!$this->isExists())
		{
			return $count;
		}
		
		$content = $this->getFile()->getContents();
		
		return array_sum(
			array_map(
				function(PrefixContent $enum)
				use (&$content)
				{
					return substr_count($content, $enum->value);
				},
				PrefixContent::cases()
			)
		);
	}
}

class ListComponent
	extends Components\AComponent
{
	private const GridId = 'SHEF_HAS_CHANGE_FILE_GRID';
	public const FindPrefixFile = 'has-change_';
	
	// region Modules ////
	protected static function getModulesList(): array
	{
		return [
			'shef.options',
			'shef.uiclear',
			'shef.haschangefiles'
		];
	}
	// endregion ////
	
	// region Tools.Page ////
	
	// endregion ////
	
	// region Params ////
	/**
	 * @return null|array
	 */
	protected function listKeysSignedParameters(): ?array
	{
		return [
			'TITLE',
			'BASE_DIR',
			'SKIP_DIRS',
		];
	}

	protected function initParams(): void
	{
		$this->arParams['TITLE'] = (string)($this->arParams['TITLE'] ?? '?');
		$this->arParams['GRID_ID'] = (string)($this->arParams['GRID_ID'] ?? static::GridId);
		
		$this->arParams['SELF_PROJECT_TITLE'] = Constants::getProjectSelfName();
		if(mb_strlen($this->arParams['SELF_PROJECT_TITLE']) < 1)
		{
			$this->arParams['SELF_PROJECT_TITLE'] = 'not-set';
		}
		$this->arParams['SELF_PROJECT_ROOT'] = Constants::getProjectSelfRootPath();
		
		
		$this->arParams['BASE_DIR'] = static::prepareDir((string)$this->arParams['BASE_DIR'], '');
		$this->arParams['NODE_ID'] = 'node_for_'.str_replace('\\', '_', $this->arParams['BASE_DIR']);
		
		if(!is_array($this->arParams['SKIP_DIRS']))
		{
			$this->arParams['SKIP_DIRS'] = [];
		}
		
		array_walk(
			$this->arParams['SKIP_DIRS'],
			function(string &$dir)
			{
				$dir = static::prepareDir($dir, $this->arParams['BASE_DIR']);
			}
		);
	}
	
	protected function checkRequiredParams(): void
	{
		if(mb_strlen($this->arParams['BASE_DIR']) < 1)
		{
			$this->addError(new Error('Error BASE_DIR'));
		}
	}
	
	protected function initResult(): void
	{
		$this->arResult = [
			'ERRORS' => [],
			'LIST' => [],
			'ROWS' => [],
			'TOTAL_ROWS_COUNT' => 0,
			'COLUMNS' => $this->getGridColumns()
		];
	}
	// endregion ////
	
	// region Work ////
	protected function process(): void
	{
		$this->getHasChangeFiles($this->arParams['BASE_DIR']);
		$this->renderGrid();
		
		unset($this->arResult['LIST']);
		
		$this->arResult['TOTAL_ROWS_COUNT'] = count($this->arResult['ROWS']);
	}
	
	/**
	 * Рекурсивно обходим BASE_DIR пропуская SKIP_DIRS
	 *
	 * На каждый файл строим ListComponentRow
	 *
	 * @param string $dir
	 * @return void
	 *
	 * @see ListComponentRow
	 * @todo: replace file iterator
	 */
	private function getHasChangeFiles(string $dir): void
	{
		$files = scandir($dir);

		for($i = count($files) - 1; $i >= 0; $i--)
		{
			if(preg_match('/^\.+$/', $files[$i]))
			{
				continue;
			}

			$path = $dir.'/'.$files[$i];
			if(is_dir($path))
			{
				if(in_array($path, $this->arParams['SKIP_DIRS']))
				{
					continue;
				}
				$this->getHasChangeFiles($path);
			}
			elseif(static::isFileHasChange($files[$i]))
			{
				$this->arResult['LIST'][] = new ListComponentRow($path);
			}
		}
	}
	// endregion ////
	
	// region Grid ////
	protected function getGridColumns(): array
	{
		return [
			[
				'id' => 'NUM',
				'name' => '#',
				'default' => true,
				'width' => 15
			],
			[
				'id' => 'STATUS',
				'name' => Loc::getMessage('GRID_COL_STATUS'),
				'default' => true,
				'width' => 125
			],
			[
				'id' => 'FILE',
				'name' => Loc::getMessage('GRID_COL_FILE'),
				'default' => true,
				'width' => 550
			],
			[
				'id' => 'ORI',
				'name' => Loc::getMessage('GRID_COL_ORI'),
				'default' => true,
				'width' => 250
			],
			[
				'id' => 'CHG',
				'name' => Loc::getMessage('GRID_COL_CHG'),
				'default' => true,
				'width' => 250
			]
		];
	}
	
	/**
	 * @throws FileNotFoundException
	 * @throws FileOpenException
	 */
	private function renderGrid(): void
	{
		$i = 0;
		$this->arResult['ROWS'] = array_map(
			function(ListComponentRow $row)
				use (&$i)
			{
				$isNeedFix = $row->isNeedFix();
				
				return [
					'ID' => str_replace('/', '_', $row->getOri()->getClearDirectoryName(true).'/'.$row->getOri()->getName()),
					'NUM' => ++$i,
					'STATUS' => Loc::getMessage('STATUS_'.($isNeedFix ? 'Y' : 'N')),
					'isNeedFix' => $isNeedFix,
					'FILE' => Loc::getMessage('FILE', [
						'#NAME#' => $row->getOri()->getName(),
						'#DIR#' => static::makePrettyPath($row->getOri()->getClearDirectoryName(true))
					]),
					'ORI' => Loc::getMessage('FILE_DETAIL', [
						'#PHPSTORM_LOCAL_PROJECT#' => $this->arParams['SELF_PROJECT_TITLE'],
						'#PHPSTORM_LOCAL_ROOT_PATH#' => $this->arParams['SELF_PROJECT_ROOT'],
						'#PHPSTORM_EDIT_PATH#' => $row->getOri()->getClearDirectoryName(true).'/'.$row->getOri()->getName(),
						'#LAST_CHANGE#' => $row->getOri()->getLastChange(),
						'#NAME#' => $row->getOri()->getName(),
						'#SIZE#' => $row->getOri()->getSize(),
					]),
					'CHG' => Loc::getMessage('FILE_DETAIL', [
						'#PHPSTORM_LOCAL_PROJECT#' => $this->arParams['SELF_PROJECT_TITLE'],
						'#PHPSTORM_LOCAL_ROOT_PATH#' => $this->arParams['SELF_PROJECT_ROOT'],
						'#PHPSTORM_EDIT_PATH#' => $row->getChg()->getClearDirectoryName(true).'/'.$row->getChg()->getName(),
						'#LAST_CHANGE#' => $row->getChg()->getLastChange(),
						'#NAME#' => $row->getChg()->getName(),
						'#SIZE#' => $row->getChg()->getSize(),
					]),
					
					'INFO' => $row
				];
				
			},
			$this->arResult['LIST']
		);
		
		
		
	}
	// endregion ////
	
	// region Tools ////
	private static function prepareDir(string $dir, string $baseDir): string
	{
		if($baseDir === '')
		{
			$baseDir = Application::getDocumentRoot();
		}
		$dir = trim($dir);
		
		$dir = str_replace(
			Application::getDocumentRoot(),
			'',
			$dir
		);
		
		if(mb_strlen($dir) < 1)
		{
			$dir = '';
		}
		elseif(mb_substr($dir, 0, 1) !== '/')
		{
			$dir = '/'.$dir;
		}
		
		return $baseDir.$dir;
	}
	
	private static function isFileHasChange(string $fileName): bool
	{
		if(preg_match('/^'.static::FindPrefixFile.'/', $fileName))
		{
			return true;
		}
		
		return false;
	}
	
	private static function getMapReplace(): array 
	{
		return [
			'/components/' => '/<b class="sh-upper">components</b>/',
			'/bitrix/activities/bitrix/' => '/bitrix<b class="sh-upper">/activities/bitrix/</b>',
			'/bitrix/templates/' => '/bitrix<b class="sh-upper">/templates/</b>',
			'/modules/crm/' => '/modules<b class="sh-upper">/crm/</b>',
			'/modules/sale/' => '/modules<b class="sh-upper">/sale/</b>',
			'/modules/salescenter/' => '/modules<b class="sh-upper">/salescenter/</b>',
			'/modules/documentgenerator/' => '/modules<b class="sh-upper">/documentgenerator/</b>',
			'/modules/highloadblock/' => '/modules<b class="sh-upper">/highloadblock/</b>',
			'/modules/catalog/' => '/modules<b class="sh-upper">/catalog/</b>',
			'/modules/main/' => '/modules<b class="sh-upper">/main/</b>',
			'/modules/location/' => '/modules<b class="sh-upper">/location/</b>',
			'/modules/intranet/' => '/modules<b class="sh-upper">/intranet/</b>',
			'/modules/rest/' => '/modules<b class="sh-upper">/rest/</b>',
			'/modules/shef.tracking/' => '/modules<b class="sh-upper">/shef.tracking/</b>',
			'/modules/' => '/<b class="sh-upper">modules</b>/',
			'/bitrix/js/ui/' => '/bitrix<b class="sh-upper">/js/ui/</b>',
			'/bitrix/js/intranet/' => '/bitrix<b class="sh-upper">/js/intranet/</b>',
			'/bitrix/js/crm/' => '/bitrix<b class="sh-upper">/js/crm/</b>',
			'/bitrix/js/' => '/bitrix<b class="sh-upper">/js/</b>',
		];
	}
	
	private function makePrettyPath(string $path): string
	{
		$mapReplace = static::getMapReplace();
		
		return str_replace(
			array_keys($mapReplace),
			array_values($mapReplace),
			$path
		);
	}
	// endregion ////
}
