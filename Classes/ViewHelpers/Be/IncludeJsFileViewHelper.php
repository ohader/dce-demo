<?php
namespace ArminVieweg\Dce\ViewHelpers\Be;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2012-2017 Armin Ruediger Vieweg <armin@v.ieweg.de>
 */

/**
 * This view helper adds js file to pagerenderer
 *
 * @package ArminVieweg\Dce
 */
class IncludeJsFileViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper
{
    /**
     * Adds js file to PageRenderer
     *
     * @param string $path to js file
     * @return void
     */
    public function render($path)
    {
        /** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer */
        $pageRenderer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Page\PageRenderer');
        $pageRenderer->addJsFile($path);
    }
}
