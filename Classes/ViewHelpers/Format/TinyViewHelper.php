<?php
namespace DceTeam\Dce\ViewHelpers\Format;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is free software and is                          *
 *  | licensed under GNU General Public License.                                                                ♥php  *
 *  | (c) 2012-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>                                                          */

/**
 * Tiny Viewhelper
 *
 * @package DceTeam\Dce
 */
class TinyViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * Make the given source tiny
	 *
	 * @param string $subject Code to make tiny
	 * @return string Tiny code
	 */
	public function render($subject = NULL) {
		if ($subject === NULL) {
			$subject = $this->renderChildren();
		}
		return str_replace(array("\r", "\n", "\t"), '', $subject);
	}
}