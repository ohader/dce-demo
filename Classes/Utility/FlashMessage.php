<?php
namespace DceTeam\Dce\Utility;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is free software and is                          *
 *  | licensed under GNU General Public License.                                                                ♥php  *
 *  | (c) 2012-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>                                                          */
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility for Flash Messages
 *
 * @package DceTeam\Dce
 */
class FlashMessage {
	/**
	 * @var \TYPO3\CMS\Core\Messaging\FlashMessageQueue
	 */
	static protected $flashMessageQueue;

	/**
	 * @param string $message
	 * @param string $title optional
	 * @param int $severity
	 * @return void
	 * @throws \TYPO3\CMS\Core\Exception
	 */
	static public function add($message, $title = '', $severity = \TYPO3\CMS\Core\Messaging\FlashMessage::WARNING) {
		if (static::$flashMessageQueue === NULL) {
			/** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
			$flashMessageService = GeneralUtility::makeInstance('TYPO3\CMS\Core\Messaging\FlashMessageService');
			/** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
			static::$flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
		}

		/** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
		$flashMessage = GeneralUtility::makeInstance('TYPO3\CMS\Core\Messaging\FlashMessage', htmlspecialchars($message), $title, $severity, TRUE);
		static::$flashMessageQueue->enqueue($flashMessage);
	}

}