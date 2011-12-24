<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2011
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Frontend
 * @license    LGPL
 * @filesource
 */


/**
 * Class ModuleCatalogFullNavigation
 *
 * Front end module "navigation".
 * @copyright  Leo Feyer 2005-2011
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Controller
 */
class ModuleCatalogFullNavigation extends ModuleNavigation
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_catalog_fullnavigation';
	
	/**
	 * Catalog table
	 * @var string
	 */
	protected $strCatalogTable = '';
	
	/**
	 * Catalog alias field
	 * @var string
	 */
	protected $aliasField = 'alias';


	/**
	 * Do not display the module if there are no menu items
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### CATALOG FULL NAVIGATION MENU ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}
		
		$this->strCatalogTable = $this->Database->execute("SELECT * FROM tl_catalog_types WHERE id={$this->catalog}")->tableName;
		$this->aliasField = $this->Database->execute("SELECT * FROM tl_catalog_types WHERE id={$this->catalog}")->aliasField;

		$strBuffer = parent::generate();
		return strlen($this->Template->items) ? $strBuffer : '';
	}


	/**
	 * Generate module
	 */
	protected function compile()
	{
		global $objPage;
		
		//Determine if we are on a item reader page. If not, display the normal breadcrumb.
		if(!$this->Input->get('items'))
		{			
			return parent::compile();
		}

		$trail = array_reverse($this->getDeepestPage());
						
		$level = ($this->levelOffset > 0) ? $this->levelOffset : 0;

		// Overwrite with custom reference page
		if ($this->defineRoot && $this->rootPage > 0)
		{
			$trail = array($this->rootPage);
			$level = 0;
		}
				
		$this->Template->request = $this->getIndexFreeRequest(true);
		$this->Template->skipId = 'skipNavigation' . $this->id;
		$this->Template->skipNavigation = specialchars($GLOBALS['TL_LANG']['MSC']['skipNavigation']);
		$this->Template->items = $this->renderNavigation($trail[$level], 1, array_pop($trail));
	}
	
	
	
	protected function getPageIdFromAlias($strURL)
	{
		global $objPage;
		
		$strAlias = $strURL;
		$strAlias = preg_replace('/\?.*$/i', '', $strAlias);
		$strAlias = preg_replace('/' . preg_quote($GLOBALS['TL_CONFIG']['urlSuffix'], '/') . '$/i', '', $strAlias);
		$arrAlias = explode('/', $strAlias);
		// Skip index.php and empty data
		if (strtolower($arrAlias[0]) == 'index.php' || $arrAlias[0]=='')
		{
			array_shift($arrAlias);
		}
		
		$objCategoryPages = $this->Database->prepare("SELECT id FROM tl_page WHERE alias=?")
											   ->execute($arrAlias[0]);
		while($objCategoryPages->next())
		{
			$objPageDetails = $this->getPageDetails($objCategoryPages->id);
			//Make sure we are getting the same rootId.. Could be more than one when doing it by alias
			if($objPageDetails->rootId == $objPage->rootId)
			{
				$pageId = $objCategoryPages->id;
			}
		}
		
		return $pageId;
	
	}
	
	protected function getReferringPageID()
	{
		$strReferer = $this->getReferer();
								
		return $this->getPageIdFromAlias($strReferer);
	}
	
	
	
	protected function getDeepestPage()
	{
		global $objPage;
		
		$objItem = $this->getItemByAlias($this->Input->get('items'));
		$arrTrails = $this->getItemPageTrails($objItem);
		$intRefId = $this->getReferringPageID();
		$arrPages = array();
				
		foreach($arrTrails as $arrTrail)
		{
			//We matched a category dead on.
			if($intRefId==$arrTrail[0]['id'])
			{
				$arrPages = $arrTrail;
			}
			//@todo consider even deeper categories
		}
		
		if(!count($arrPages))
		{
			// We didn't find any pages... Let's just get the first one
			$arrPages = $arrTrails[0];
		}
			
		return $arrPages;
	}
	
	protected function getItemPageTrails($objItem)
	{
		
		$arrCats = deserialize($objItem->{$this->categoryField}, true);
				
		$arrReturn = array();
		
		//Add the referring page just in case there are no cats
		if(!count($arrCats))
			$arrCats=array($this->getReferringPageID());
		
		foreach($arrCats as $cat)
		{
			$pages = array();
			$pageId = $cat;
		
			// Get all pages up to the root page
			do
			{
				$objPages = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")
										   ->limit(1)
										   ->execute($pageId);
	
				$type = $objPages->type;
				$pageId = $objPages->pid;
				$pages[] = $objPages->id;
			}
			while ($pageId > 0 && $type != 'root' && $objPages->numRows);
	
			if ($type == 'root')
			{					
				$arrReturn[] = $pages;
			}
		}	
								
		return $arrReturn;
	}

	
	
	/**
	 * Shortcut for a single item's pages by alias
	 */
	protected function getItemByAlias($strAlias)
	{
		// Get item pages
		$objItem= $this->Database->prepare("SELECT {$this->categoryField} FROM {$this->strCatalogTable} WHERE id=? OR {$this->aliasField}=?")
									 ->limit(1)
									 ->execute((is_numeric($strAlias) ? $strAlias : 0), $strAlias);
		
		return $objItem;
	}
	
	
	/**
	 * Recursively compile the navigation menu and return it as HTML string
	 * @param integer
	 * @param integer
	 * @return string
	 */
	protected function renderNavigation($pid, $level=1, $intItemPage=0)
	{
		$time = time();

		// Get all active subpages
		$objSubpages = $this->Database->prepare("SELECT p1.*, (SELECT COUNT(*) FROM tl_page p2 WHERE p2.pid=p1.id AND p2.type!='root' AND p2.type!='error_403' AND p2.type!='error_404'" . (!$this->showHidden ? (($this instanceof ModuleSitemap) ? " AND (p2.hide!=1 OR sitemap='map_always')" : " AND p2.hide!=1") : "") . ((FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN) ? " AND p2.guests!=1" : "") . (!BE_USER_LOGGED_IN ? " AND (p2.start='' OR p2.start<".$time.") AND (p2.stop='' OR p2.stop>".$time.") AND p2.published=1" : "") . ") AS subpages FROM tl_page p1 WHERE p1.pid=? AND p1.type!='root' AND p1.type!='error_403' AND p1.type!='error_404'" . (!$this->showHidden ? (($this instanceof ModuleSitemap) ? " AND (p1.hide!=1 OR sitemap='map_always')" : " AND p1.hide!=1") : "") . ((FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN) ? " AND p1.guests!=1" : "") . (!BE_USER_LOGGED_IN ? " AND (p1.start='' OR p1.start<".$time.") AND (p1.stop='' OR p1.stop>".$time.") AND p1.published=1" : "") . " ORDER BY p1.sorting")
									  ->execute($pid);
		
		if ($objSubpages->numRows < 1)
		{
			return '';
		}

		$items = array();
		$groups = array();

		// Get all groups of the current front end user
		if (FE_USER_LOGGED_IN)
		{
			$this->import('FrontendUser', 'User');
			$groups = $this->User->groups;
		}

		// Layout template fallback
		if ($this->navigationTpl == '')
		{
			$this->navigationTpl = 'nav_default';
		}

		$objTemplate = new FrontendTemplate($this->navigationTpl);

		$objTemplate->type = get_class($this);
		$objTemplate->level = 'level_' . $level++;
		
		global $objPage;
								
		// Get page object
		//Modified to make the item's category page the current page 
		$objItemPage = $intItemPage>0 ? $this->getPageDetails($intItemPage) : $objPage;

		// Browse subpages
		while($objSubpages->next())
		{
			// Skip hidden sitemap pages
			if ($this instanceof ModuleSitemap && $objSubpages->sitemap == 'map_never')
			{
				continue;
			}

			$subitems = '';
			$_groups = deserialize($objSubpages->groups);

			// Do not show protected pages unless a back end or front end user is logged in
			if (!$objSubpages->protected || BE_USER_LOGGED_IN || (is_array($_groups) && count(array_intersect($_groups, $groups))) || $this->showProtected || ($this instanceof ModuleSitemap && $objSubpages->sitemap == 'map_always'))
			{
				// Check whether there will be subpages
				if ($objSubpages->subpages > 0 && (!$this->showLevel || $this->showLevel >= $level || (!$this->hardLimit && ($objItemPage->id == $objSubpages->id || in_array($objItemPage->id, $this->getChildRecords($objSubpages->id, 'tl_page'))))))
				{
					$subitems = $this->renderNavigation($objSubpages->id, $level, $intItemPage);
				}

				// Get href
				switch ($objSubpages->type)
				{
					case 'redirect':
						$href = $objSubpages->url;

						if (strncasecmp($href, 'mailto:', 7) === 0)
						{
							$this->import('String');
							$href = $this->String->encodeEmail($href);
						}
						break;

					case 'forward':
						if (!$objSubpages->jumpTo)
						{
							$objNext = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE pid=? AND type='regular'" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<$time) AND (stop='' OR stop>$time) AND published=1" : "") . " ORDER BY sorting")
													  ->limit(1)
													  ->execute($objSubpages->id);
						}
						else
						{
							$objNext = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
													  ->limit(1)
													  ->execute($objSubpages->jumpTo);
						}

						if ($objNext->numRows)
						{
							$href = $this->generateFrontendUrl($objNext->fetchAssoc());
							break;
						}
						// DO NOT ADD A break; STATEMENT

					default:
						$href = $this->generateFrontendUrl($objSubpages->row());
						break;
				}

				// Active page
				if (($objItemPage->id == $objSubpages->id || $objSubpages->type == 'forward' && $objItemPage->id == $objSubpages->jumpTo) && !$this instanceof ModuleSitemap && !$this->Input->get('articles'))
				{
					$strClass = (($subitems != '') ? 'submenu' : '') . ($objSubpages->protected ? ' protected' : '') . (($objSubpages->cssClass != '') ? ' ' . $objSubpages->cssClass : '');
					$row = $objSubpages->row();

					$row['isActive'] = true;
					$row['subitems'] = $subitems;
					$row['class'] = trim($strClass);
					$row['title'] = specialchars($objSubpages->title, true);
					$row['pageTitle'] = specialchars($objSubpages->pageTitle, true);
					$row['link'] = $objSubpages->title;
					$row['href'] = $href;
					$row['nofollow'] = (strncmp($objSubpages->robots, 'noindex', 7) === 0);
					$row['target'] = '';
					$row['description'] = str_replace(array("\n", "\r"), array(' ' , ''), $objSubpages->description);

					// Override the link target
					if ($objSubpages->type == 'redirect' && $objSubpages->target)
					{
						$row['target'] = ($objItemPage->outputFormat == 'xhtml') ? ' onclick="window.open(this.href); return false;"' : ' target="_blank"';
					}

					$items[] = $row;
				}

				// Regular page
				else
				{
					$strClass = (($subitems != '') ? 'submenu' : '') . ($objSubpages->protected ? ' protected' : '') . (($objSubpages->cssClass != '') ? ' ' . $objSubpages->cssClass : '') . (in_array($objSubpages->id, $objItemPage->trail) ? ' trail' : '');

					// Mark pages on the same level (see #2419)
					if ($objSubpages->pid == $objItemPage->pid)
					{
						$strClass .= ' sibling';
					}

					$row = $objSubpages->row();

					$row['isActive'] = false;
					$row['subitems'] = $subitems;
					$row['class'] = trim($strClass);
					$row['title'] = specialchars($objSubpages->title, true);
					$row['pageTitle'] = specialchars($objSubpages->pageTitle, true);
					$row['link'] = $objSubpages->title;
					$row['href'] = $href;
					$row['nofollow'] = (strncmp($objSubpages->robots, 'noindex', 7) === 0);
					$row['target'] = '';
					$row['description'] = str_replace(array("\n", "\r"), array(' ' , ''), $objSubpages->description);

					// Override the link target
					if ($objSubpages->type == 'redirect' && $objSubpages->target)
					{
						$row['target'] = ($objItemPage->outputFormat == 'xhtml') ? ' onclick="window.open(this.href); return false;"' : ' target="_blank"';
					}

					$items[] = $row;
				}
			}
		}

		// Add classes first and last
		if (count($items))
		{
			$last = count($items) - 1;

			$items[0]['class'] = trim($items[0]['class'] . ' first');
			$items[$last]['class'] = trim($items[$last]['class'] . ' last');
		}

		$objTemplate->items = $items;
		return count($items) ? $objTemplate->parse() : '';
	}

	
	
}

?>