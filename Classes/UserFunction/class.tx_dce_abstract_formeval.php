<?php

/*  | This extension is part of the TYPO3 project. The TYPO3 project is free software and is                          *
 *  | licensed under GNU General Public License.                                                                ♥php  *
 *  | (c) 2012-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>                                                          */
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract class for DCE form validators
 *
 * @package DceTeam\Dce
 */
abstract class tx_dce_abstract_formeval {

	/**
	 * JavaScript validation
	 *
	 * @return string javascript function code for js validation
	 */
	public function returnFieldJS() {
		return 'return value;';
	}

	/**
	 * PHP Validation
	 *
	 * @param string $value
	 * @return mixed
	 */
	public function evaluateFieldValue($value) {
		return $value;
	}

	/**
	 * Adds a flash message
	 *
	 * @param string $message
	 * @param string $title optional message title
	 * @param int $severity optional severity code. One of the t3lib_FlashMessage constants
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	protected function addFlashMessage($message, $title = '', $severity = \TYPO3\CMS\Core\Messaging\FlashMessage::OK) {
		if (!is_string($message)) {
			throw new InvalidArgumentException('The flash message must be string, ' . gettype($message) . ' given.', 1243258395);
		}

		/** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
		$flashMessage = GeneralUtility::makeInstance(
			'TYPO3\CMS\Core\Messaging\FlashMessage',
			$message,
			$title,
			$severity,
			TRUE
		);

		/** @var \TYPO3\CMS\Core\Messaging\FlashMessageQueue $flashMessageQueue */
		$flashMessageQueue = GeneralUtility::makeInstance('TYPO3\CMS\Core\Messaging\FlashMessageQueue');
		$flashMessageQueue->enqueue($flashMessage);
	}

	/**
	 * Returns the translation of current language, stored in locallang_db.xml.
	 *
	 * @param string $key key in locallang_db.xml to translate
	 * @param array $arguments optional arguments
	 * @return string Translated text
	 */
	protected function translate($key, array $arguments = array()) {
		return \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('LLL:EXT:dce/Resources/Private/Language/locallang_db.xml:' . $key, 'Dce', $arguments);
	}
}