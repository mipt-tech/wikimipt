<?php

class CommentHideAPI extends ApiBase {

	public function execute() {
		$user = $this->getUser();

		$comment = Comment::newFromID( $this->getMain()->getVal( 'commentID' ) );

		$userCheck = (
			$user->isAllowed( 'commentadmin' )
		);

		// Blocked users cannot delete comments, and neither can unprivileged ones.
		if ( $user->getBlock() && !$userCheck ) {
			$this->dieBlocked( $user->getBlock() );
		} elseif ( $user->isBlockedGlobally() && !$userCheck ) {
			$this->dieBlocked( $user->getGlobalBlock() );
		}

		if ($this->getMain()->getVal( 'hide' ) == true) {
			$comment->hide();
		} else {
			$comment->unhide();
		}

		$result = $this->getResult();
		$result->addValue( $this->getModuleName(), 'ok', 'ok' );
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'commentID' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'hide' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'boolean'
			]
		];
	}
}
