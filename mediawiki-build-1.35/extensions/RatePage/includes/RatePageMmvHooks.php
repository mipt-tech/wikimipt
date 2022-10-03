<?php

/**
 * Enables access to MMV's internal functionality.
 * Class RatePage\MultimediaViewer\RatePageMmvHooks
 *
 * No namespace because it apparently breaks the autoloader (???).
 */
class RatePageMmvHooks extends MultimediaViewerHooks {
	/**
	 * Returns whether MMV should be enabled for this user.
	 *
	 * @param User $user
	 *
	 * @return bool
	 */
	public static function isMmvEnabled( User $user ) : bool {
		return self::shouldHandleClicks( $user );
	}
}