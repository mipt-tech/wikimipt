<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\AtEase\AtEase;

/**
 * Comment class
 * Functions for managing comments and everything related to them, including:
 * -blocking comments from a given user
 * -counting the total amount of comments in the database
 * -displaying the form for adding a new comment
 * -getting all comments for a given page
 *
 * @file
 * @ingroup Extensions
 */
class Comment extends ContextSource {
	/**
	 * @var CommentsPage page of the page the <comments /> tag is in
	 */
	public $page = null;

	/**
	 * @var int total amount of comments by distinct commenters that the
	 *               current page has
	 */
	public $commentTotal = 0;

	/**
	 * @var string text of the current comment
	 */
	public $text = null;

	/**
	 * Date when the comment was posted
	 *
	 * @var null
	 */
	public $date = null;

	/**
	 * @var int internal ID number (Comments.CommentID DB field) of the
	 *               current comment that we're dealing with
	 */
	public $id = 0;

	/**
	 * @var int ID of the parent comment, if this is a child comment
	 */
	public $parentID = 0;

	/**
	 * The current vote from this user on this comment
	 *
	 * @var int|bool false if no vote, otherwise -1, 0, or 1
	 */
	public $currentVote = false;

	/**
	 * @var string comment score (SUM() of all votes) of the current comment
	 */
	public $currentScore = '0';

	/**
	 * User (object) who posted the comment
	 * @var User
	 */
	public $user;

	/**
	 * Username of the user who posted the comment
	 *
	 * @var string
	 */
	public $username = '';

	/**
	 * IP of the comment poster
	 *
	 * @var string
	 */
	public $ip = '';

	/**
	 * Actor ID of the user who posted the comment
	 *
	 * @var int
	 */
	public $actorID = 0;
	public $actorName = "";

	/**
	 * The amount of points the user has; fetched from the user_stats table if
	 * SocialProfile is installed, otherwise this remains 0
	 *
	 * @var int
	 */
	public $userPoints = 0;

	/**
	 * Comment ID of the thread this comment is in
	 * this is the ID of the parent comment if there is one,
	 * or this comment if there is not
	 * Used for sorting
	 *
	 * @var null
	 */
	public $thread = null;

	/**
	 * Unix timestamp when the comment was posted
	 * Used for sorting
	 * Processed from $date
	 *
	 * @var null
	 */
	public $timestamp = null;

	/**
	 * Hidden comment flag.
	 * When equal to 1, then comment will be hidden for non-authorized people
	 * Processed from $date
	 *
	 * @var null
	 */
	public $hidden = null;

	/**
	 * Constructor - set the page ID
	 *
	 * @param CommentsPage $page ID number of the current page
	 * @param IContextSource|null $context
	 * @param array $data Straight from the DB about the comment
	 */
	public function __construct( CommentsPage $page, $context = null, $data ) {
		$this->page = $page;

		$this->setContext( $context );

		$this->actorID = (int)$data['Comment_actor'];
		$this->actorName = "";

		if ( $this->actorID !== 0 ) {
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow(
				'actor',
				[ 'actor_name' ],
				[ 'actor_id' => $this->actorID, ],
				__METHOD__
			);
			if ( $row !== false ) {
				$this->actorName = $row->actor_name;
			}
		}

		$this->user = $commenter = User::newFromActorId( $data['Comment_actor'] );

		$this->username = $commenter->getName();
		$this->ip = $data['Comment_IP'];
		$this->text = $data['Comment_Text'];
		$this->date = $data['Comment_Date'];
		$this->userPoints = $data['Comment_user_points'];
		$this->id = (int)$data['CommentID'];
		$this->parentID = (int)$data['Comment_Parent_ID'];
		$this->hidden = (int)$data['Comment_hidden'];
		$this->thread = $data['thread'];
		$this->timestamp = $data['timestamp'];

		// @TODO: this does not look OK to additionally query the vote data here
		//			it's probably better to do it within the Comment::newFromID down below
		// if ( isset( $data['current_vote'] ) ) {
		// 	$vote = $data['current_vote'];
		// } else {
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow(
				'Comments_Vote',
				[ 'Comment_Vote_Score' ],
				[
					'Comment_Vote_ID' => $this->id,
					'Comment_Vote_actor' => $this->getUser()->getActorId()
				],
				__METHOD__
			);
			if ( $row !== false ) {
				$vote = $row->Comment_Vote_Score;
			} else {
				$vote = false;
			}
		// }

		$this->currentVote = $vote;

		// @TODO: same as above for current_vote
		// $this->currentScore = isset( $data['total_vote'] )
			// ? $data['total_vote'] : $this->getScore();
		$this->currentScore = $this->getScore();
	}

	/**
	 * Create a new Comment object from a comment ID.
	 *
	 * @param int $id Comment ID, must not be zero
	 * @return null|Comment Null on failure, Comment object on success
	 */
	public static function newFromID( $id ) {
		$context = RequestContext::getMain();
		$dbr = wfGetDB( DB_REPLICA );

		if ( !is_numeric( $id ) || $id == 0 ) {
			return null;
		}

		$tables = [];
		$params = [];
		$joinConds = [];

		// Defaults (for non-social wikis)
		$tables[] = 'Comments';
		$fields = [
			'Comment_actor', 'Comment_IP', 'Comment_Text',
			'Comment_Date', 'Comment_Date AS timestamp',
			'CommentID', 'Comment_Parent_ID', 'Comment_Page_ID', 'Comment_hidden'
		];

		// If SocialProfile is installed, query the user_stats table too.
		if (
			class_exists( 'UserProfile' ) &&
			$dbr->tableExists( 'user_stats' )
		) {
			$tables[] = 'user_stats';
			$fields[] = 'stats_total_points';
			$joinConds = [
				'Comments' => [
					'LEFT JOIN', 'Comment_actor = stats_actor'
				]
			];
		}

		// @TODO: we probably need to also query voting data here

		// Perform the query
		$res = $dbr->select(
			$tables,
			$fields,
			[ 'CommentID' => $id ],
			__METHOD__,
			$params,
			$joinConds
		);

		$row = $res->fetchObject();

		if ( $row->Comment_Parent_ID == 0 ) {
			$thread = $row->CommentID;
		} else {
			$thread = $row->Comment_Parent_ID;
		}
		$data = [
			'Comment_actor' => $row->Comment_actor,
			'Comment_IP' => $row->Comment_IP,
			'Comment_Text' => $row->Comment_Text,
			'Comment_Date' => $row->Comment_Date,
			'Comment_user_points' => ( isset( $row->stats_total_points ) ? number_format( $row->stats_total_points ) : 0 ),
			'CommentID' => $row->CommentID,
			'Comment_Parent_ID' => $row->Comment_Parent_ID,
			'Comment_hidden' => $row->Comment_hidden,
			'thread' => $thread,
			'timestamp' => wfTimestamp( TS_UNIX, $row->timestamp )
		];

		$page = new CommentsPage( $row->Comment_Page_ID, $context );

		return new Comment( $page, $context, $data );
	}

	/**
	 * Is the given User the owner (author) of this comment?
	 *
	 * @param User $user
	 * @return bool
	 */
	public function isOwner( User $user ) {
		return ( $this->actorID === $user->getActorId() );
	}

	function isAnonComment() {
		return ( $this->actorID === 0 || $this->actorName === "");
	}

	/**
	 * Parse and return the text for this comment
	 *
	 * @return string
	 */
	function getText() {
		$parser = MediaWikiServices::getInstance()->getParser();

		$commentText = trim( str_replace( '&quot;', "'", $this->text ) );
		$comment_text_parts = explode( "\n", $commentText );
		$comment_text_fix = '';
		foreach ( $comment_text_parts as $part ) {
			$comment_text_fix .= ( ( $comment_text_fix ) ? "\n" : '' ) . trim( $part );
		}

		if ( $this->getTitle()->getArticleID() > 0 ) {
			$commentText = $parser->recursiveTagParse( $comment_text_fix );
		} else {
			$commentText = $this->getOutput()->parseAsContent( $comment_text_fix );
		}

		// really bad hack because we want to parse=firstline, but don't want wrapping <p> tags
		if ( substr( $commentText, 0, 3 ) == '<p>' ) {
			$commentText = substr( $commentText, 3 );
		}

		if ( substr( $commentText, strlen( $commentText ) - 4, 4 ) == '</p>' ) {
			$commentText = substr( $commentText, 0, strlen( $commentText ) - 4 );
		}

		// make sure link text is not too long (will overflow)
		// this function changes too long links to <a href=#>http://www.abc....xyz.html</a>
		$commentText = preg_replace_callback(
			"/(<a[^>]*>)(.*?)(<\/a>)/i",
			[ 'CommentFunctions', 'cutCommentLinkText' ],
			$commentText
		);

		return $commentText;
	}

	/**
	 * Adds the comment and all necessary info into the Comments table in the
	 * database.
	 *
	 * @param string $text Text of the comment
	 * @param CommentsPage $page Container page
	 * @param User $user User commenting
	 * @param int $parentID ID of parent comment, if this is a reply
	 *
	 * @return Comment|null the added comment
	 */
	public static function add( $text, CommentsPage $page, User $user, $parentID ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$context = RequestContext::getMain();

		AtEase::suppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		AtEase::restoreWarnings();
		$dbw->insert(
			'Comments',
			[
				'Comment_Page_ID' => $page->id,
				'Comment_actor' => $user->getActorId(),
				'Comment_Text' => $text,
				'Comment_Date' => $dbw->timestamp( $commentDate ),
				'Comment_Parent_ID' => $parentID,
				'Comment_IP' => $_SERVER['REMOTE_ADDR']
			],
			__METHOD__
		);
		$commentId = $dbw->insertId();
		$id = $commentId;

		$page->clearCommentListCache();

		// Add a log entry.
		self::log( 'add', $user, $page->id, $commentId, $text );

		$dbr = wfGetDB( DB_REPLICA );
		if (
			class_exists( 'UserProfile' ) &&
			$dbr->tableExists( 'user_stats' )
		) {
			$res = $dbr->select( // need this data for seeding a Comment object
				'user_stats',
				'stats_total_points',
				[ 'stats_actor' => $user->getActorId() ],
				__METHOD__
			);

			$row = $res->fetchObject();
			if ( $row ) {
				$userPoints = number_format( $row->stats_total_points );
			} else {
				$userPoints = 0;
			}
		} else {
			$userPoints = 0;
		}

		if ( $parentID == 0 ) {
			$thread = $id;
		} else {
			$thread = $parentID;
		}
		$data = [
			'Comment_actor' => $user->getActorId(),
			'Comment_IP' => $context->getRequest()->getIP(),
			'Comment_Text' => $text,
			'Comment_Date' => $commentDate,
			'Comment_user_points' => $userPoints,
			'CommentID' => $id,
			'Comment_Parent_ID' => $parentID,
			'Comment_hidden' => 0,
			'thread' => $thread,
			'timestamp' => strtotime( $commentDate )
		];

		$page = new CommentsPage( $page->id, $context );
		$comment = new Comment( $page, $context, $data );

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			global $wgEchoMentionOnChanges;
			if ( !$wgEchoMentionOnChanges ) {
				return null;
			}
			// Modified copypasta of EchoDiscussionParser#generateEventsForRevision with less Revision-ism!
			// (Awful pun is awful, sorry about that.)
			// EchoDiscussionParser#getChangeInterpretationForRevision is *way*, way too Revision-ist for
			// our tastes. DO NOT WANT!
			$title = Title::newFromId( $page->id );

			// stolen from EchoDiscussionParser#generateEventsForRevision
			$userLinks = EchoDiscussionParser::getUserLinks( $text, $title );
			$header = $text;

			self::generateMentionEvents(
				$header, $userLinks, $text, $title, $user,
				$comment, $commentId
			);
		}

		Hooks::run( 'Comment::add', [ $comment, $commentId, $comment->page->id ] );

		return $comment;
	}

	/**
	 * It's like Article::prepareTextForEdit,
	 *  but not for editing (old wikitext usually)
	 * Stolen from AbuseFilterVariableHolder
	 *
	 * @param string $wikitext
	 * @param Article $article
	 *
	 * @return ParserOutput
	 */
	private static function parseNonEditWikitext( $wikitext, Article $article ) {
		static $cache = [];
		$cacheKey = md5( $wikitext ) . ':' . $article->getTitle()->getPrefixedText();
		if ( isset( $cache[$cacheKey] ) ) {
			return $cache[$cacheKey];
		}
		$parser = MediaWikiServices::getInstance()->getParser();
		$options = new ParserOptions( $article->getContext()->getUser() );
		$output = $parser->parse( $wikitext, $article->getTitle(), $options );
		$cache[$cacheKey] = $output;
		return $output;
	}

	/**
	 * For an action taken on a talk page, notify users whose user pages are linked.
	 *
	 * @note Literally stolen from Echo's EchoDiscussionParser (@REL1_33) and
	 * modified to be less Revision-centric.
	 *
	 * @param string $header The subject line for the discussion.
	 * @param int[] $userLinks
	 * @param string $content The content of the post, as a wikitext string.
	 * @param Title $title
	 * @param User $agent The user who made the comment.
	 * @param Comment $comment
	 * @param int $commentId
	 */
	public static function generateMentionEvents(
		$header,
		$userLinks,
		$content,
		Title $title,
		// Revision $revision,
		User $agent,
		Comment $comment,
		$commentId
	) {
		global $wgEchoMaxMentionsCount, $wgEchoMentionStatusNotifications;

		// $title = $revision->getTitle();
		if ( !$title ) {
			return;
		}
		$revId = $title->getLatestRevID();
		// Comments are often short. These Echo-isms mutilate $content into an empty string.
		// We don't want that to happen.
		// $content = EchoDiscussionParser::stripHeader( $content );
		// $content = EchoDiscussionParser::stripSignature( $content, $title );
		if ( !$userLinks ) {
			return;
		}

		$userMentions = EchoDiscussionParser::getUserMentions( $title, $agent->getId(), $userLinks );
		// $overallMentionsCount = EchoDiscussionParser::getOverallUserMentionsCount( $userMentions );
		$overallMentionsCount = count( $userMentions, COUNT_RECURSIVE ) - count( $userMentions );
		if ( $overallMentionsCount === 0 ) {
			return;
		}

		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();

		if ( $overallMentionsCount > $wgEchoMaxMentionsCount ) {
			if ( $wgEchoMentionStatusNotifications ) {
				EchoEvent::create( [
					'type' => 'mention-failure-too-many',
					'title' => $title,
					'extra' => [
						'max-mentions' => $wgEchoMaxMentionsCount,
						'section-title' => $header,
						'comment-id' => $commentId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.failure-too-many' );
			}
			return;
		}

		if ( $userMentions['validMentions'] ) {
			EchoEvent::create( [
				'type' => 'mention-comment',
				'title' => $title,
				'extra' => [
					'content' => $content,
					'section-title' => $header,
					'revid' => $revId,
					'comment-id' => $commentId, // added
					'mentioned-users' => $userMentions['validMentions'],
				],
				'agent' => $agent,
			] );
		}

		if ( $wgEchoMentionStatusNotifications ) {
			// TODO batch?
			foreach ( $userMentions['validMentions'] as $mentionedUserId ) {
				EchoEvent::create( [
					'type' => 'mention-success',
					'title' => $title,
					'extra' => [
						'subject-name' => User::newFromId( $mentionedUserId )->getName(),
						'section-title' => $header,
						'revid' => $revId,
						'comment-id' => $commentId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.success' );
			}

			// TODO batch?
			foreach ( $userMentions['anonymousUsers'] as $anonymousUser ) {
				EchoEvent::create( [
					'type' => 'mention-failure',
					'title' => $title,
					'extra' => [
						'failure-type' => 'user-anonymous',
						'subject-name' => $anonymousUser,
						'section-title' => $header,
						'revid' => $revId,
						'comment-id' => $commentId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.failure-user-anonymous' );
			}

			// TODO batch?
			foreach ( $userMentions['unknownUsers'] as $unknownUser ) {
				EchoEvent::create( [
					'type' => 'mention-failure',
					'title' => $title,
					'extra' => [
						'failure-type' => 'user-unknown',
						'subject-name' => $unknownUser,
						'section-title' => $header,
						'revid' => $revId,
						'comment-id' => $commentId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.failure-user-unknown' );
			}
		}
	}

	/**
	 * Gets the score for this comment from the database table Comments_Vote
	 *
	 * @return string
	 */
	function getScore() {
		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow(
			'Comments_Vote',
			[ 'SUM(Comment_Vote_Score) AS CommentScore' ],
			[ 'Comment_Vote_ID' => $this->id ],
			__METHOD__
		);
		$score = '0';
		if ( $row !== false && $row->CommentScore ) {
			$score = $row->CommentScore;
		}
		if (!CommentObsceneCensorRus::isAllowed($this->text)) {
			$score = intval($score) - 5;
		}
		return $score;
	}

	/**
	 * Adds a vote for a comment if the user hasn't voted for said comment yet.
	 *
	 * @param int $value Upvote or downvote (1 or -1)
	 */
	function vote( $value ) {
		$dbw = wfGetDB( DB_PRIMARY );

		if ( $value < -1 ) { // limit to range -1 -> 0 -> 1
			$value = -1;
		} elseif ( $value > 1 ) {
			$value = 1;
		}

		if ( $value == $this->currentVote ) { // user toggling off a preexisting vote
			$value = 0;
		}

		AtEase::suppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		AtEase::restoreWarnings();

		if ( $this->currentVote === false ) { // no vote, insert
			$dbw->insert(
				'Comments_Vote',
				[
					'Comment_Vote_id' => $this->id,
					'Comment_Vote_actor' => $this->getUser()->getActorId(),
					'Comment_Vote_Score' => $value,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				],
				__METHOD__
			);
		} else { // already a vote, update
			$dbw->update(
				'Comments_Vote',
				[
					'Comment_Vote_Score' => $value,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				],
				[
					'Comment_Vote_id' => $this->id,
					'Comment_Vote_actor' => $this->getUser()->getActorId(),
				],
				__METHOD__
			);
		}

		$score = $this->getScore();

		$this->currentVote = $value;
		$this->currentScore = $score;
	}

	/**
	 * Deletes entries from Comments and Comments_Vote tables and clears caches
	 */
	function delete() {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete(
			'Comments',
			[ 'CommentID' => $this->id ],
			__METHOD__
		);
		$dbw->delete(
			'Comments_Vote',
			[ 'Comment_Vote_ID' => $this->id ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		// Log the deletion to Special:Log/comments.
		self::log( 'delete', $this->getUser(), $this->page->id, $this->id );

		// Clear memcache & Squid cache
		$this->page->clearCommentListCache();

		// Ping other extensions that may have hooked into this point (i.e. LinkFilter)
		Hooks::run( 'Comment::delete', [ $this, $this->id, $this->page->id ] );
	}

	/**
	 * Hides comment for unauthorized users regardless of its content / score
	 */
	function hide() {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->update(
			'Comments',
			[
				'Comment_hidden' => 1,
			],
			[ 'CommentID' => $this->id ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		// Log the deletion to Special:Log/comments.
		self::log( 'hide', $this->getUser(), $this->page->id, $this->id );

		// Clear memcache & Squid cache
		$this->page->clearCommentListCache();
	}

	/**
	 * Unhides comment for unauthorized users (but it can still stay hidden because of its content / score)
	 */
	function unhide() {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->update(
			'Comments',
			[
				'Comment_hidden' => 0,
			],
			[ 'CommentID' => $this->id ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		// Log the deletion to Special:Log/comments.
		self::log( 'unhide', $this->getUser(), $this->page->id, $this->id );

		// Clear memcache & Squid cache
		$this->page->clearCommentListCache();
	}

	/**
	 * Log an action in the comment log.
	 *
	 * @param string $action Action to log, can be either 'add' or 'delete'
	 * @param User $user User who performed the action
	 * @param int $pageId Page ID of the page that contains the comment thread
	 * @param int $commentId Comment ID of the affected comment
	 * @param string|null $commentText Supplementary log comment, if any
	 */
	static function log( $action, $user, $pageId, $commentId, $commentText = null ) {
		global $wgCommentsInRecentChanges;
		$logEntry = new ManualLogEntry( 'comments', $action );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( Title::newFromId( $pageId ) );
		if ( $commentText !== null ) {
			$logEntry->setComment( $commentText );
		}
		$logEntry->setParameters( [
			'4::commentid' => $commentId
		] );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, ( $wgCommentsInRecentChanges ? 'rcandudp' : 'udp' ) );
	}

	/**
	 * Return the HTML for the comment vote links
	 *
	 * @param int $voteType up (+1) vote or down (-1) vote
	 * @return string
	 */
	function getVoteLink( $voteType ) {
		global $wgExtensionAssetsPath;

		$user = $this->getUser();

		// Blocked users cannot vote, obviously
		if ( $user->getBlock() ||
			$user->isBlockedGlobally() ||
			!$user->isAllowed( 'comment' )
		) {
			return '';
		}

		$voteLink = '';
		if ( $user->isRegistered() ) {
			$voteLink .= '<a id="comment-vote-link" data-comment-id="' .
				$this->id . '" data-vote-type="' . $voteType .
				'" data-voting="' . $this->page->voting . '" href="javascript:void(0);">';
		} else {
			$login = SpecialPage::getTitleFor( 'Userlogin' ); // Anonymous users need to log in before they can vote
			$urlParams = [];
			// @todo FIXME: *when* and *why* is this null?
			if ( $this->page->title instanceof Title ) {
				$returnTo = $this->page->title->getPrefixedDBkey(); // Determine a sane returnto URL parameter
				$urlParams = [ 'returnto' => $returnTo ];
			}

			$voteLink .=
				"<a href=\"" .
				htmlspecialchars( $login->getLocalURL( $urlParams ) ) .
				"\" rel=\"nofollow\">";
		}

		$imagePath = $wgExtensionAssetsPath . '/Comments/resources/images';
		if ( $voteType == 1 ) {
			if ( $this->currentVote == 1 ) {
				$voteLink .= "<img src=\"{$imagePath}/up-voted.png\" border=\"0\" alt=\"+\" /></a>";
			} else {
				$voteLink .= "<img src=\"{$imagePath}/up-unvoted.png\" border=\"0\" alt=\"+\" /></a>";
			}
		} else {
			if ( $this->currentVote == -1 ) {
				$voteLink .= "<img src=\"{$imagePath}/down-voted.png\" border=\"0\" alt=\"+\" /></a>";
			} else {
				$voteLink .= "<img src=\"{$imagePath}/down-unvoted.png\" border=\"0\" alt=\"+\" /></a>";
			}
		}

		return $voteLink;
	}

	/**
	 * Show the HTML for this comment and ignore section
	 *
	 * @param array $blockList List of users the current user has blocked
	 * @param array $anonList Map of IP addresses to names like anon#1, anon#2
	 * @return string HTML
	 */
	function display( $blockList, $anonList ) {
		if ( $this->parentID == 0 ) {
			$container_class = 'full';
		} else {
			$container_class = 'reply';
		}

		$output = '';

		$isBlocked = in_array( $this->username, $blockList );
		$output .= $this->showIgnore( !$isBlocked, $container_class );
		$output .= $this->showComment( $isBlocked, $container_class, $blockList, $anonList );

		return $output;
	}

	/**
	 * Show the box for if this comment has been ignored
	 *
	 * @param bool $hide
	 * @param string $containerClass
	 * @return string
	 */
	function showIgnore( $hide = false, $containerClass ) {
		$blockListTitle = SpecialPage::getTitleFor( 'CommentIgnoreList' );

		$style = '';
		if ( $hide ) {
			$style = " style='display:none;'";
		}

		$output = "<div id='ignore-{$this->id}' class='c-ignored {$containerClass}'{$style}>\n";
		$output .= wfMessage( 'comments-ignore-message' )->parse();
		$output .= '<div class="c-ignored-links">' . "\n";
		$output .= "<a href=\"javascript:void(0);\" data-comment-id=\"{$this->id}\">" .
			$this->msg( 'comments-show-comment-link' )->plain() . '</a> | ';
		$output .= '<a href="' . htmlspecialchars( $blockListTitle->getFullURL() ) . '">' .
			$this->msg( 'comments-manage-blocklist-link' )->plain() . '</a>';
		$output .= '</div>' . "\n";
		$output .= '</div>' . "\n";

		return $output;
	}

	/**
	 * Show the comment
	 *
	 * @param bool $hide If true, comment is returned but hidden (display:none)
	 * @param string $containerClass
	 * @param array $blockList
	 * @param array $anonList
	 * @return string
	 */
	function showComment( $hide = false, $containerClass, $blockList, $anonList ) {
		global $wgUserLevels, $wgExtensionAssetsPath, $wgCommentsDefaultAvatar;

		$style = '';
		if ( $hide ) {
			$style = " style='display:none;'";
		}

		$commentPosterLevel = '';

		if ( !$this->isAnonComment() ) {
			if ( !$this->user->isAnon() ) {
				$commentPoster = '<a href="' . htmlspecialchars( $this->user->getUserPage()->getFullURL(), ENT_QUOTES ) .
					'" rel="nofollow">' . htmlspecialchars( $this->user->getRealName(), ENT_QUOTES ) . '</a>';

				$CommentReplyTo = $this->user->getRealName();

				if ( $wgUserLevels && class_exists( 'UserLevel' ) ) {
					$user_level = new UserLevel( $this->userPoints );
					$commentPosterLevel = "{$user_level->getLevelName()}";
				}
	
				$user = User::newFromId( $this->user->getId() );
				$CommentReplyToGender = MediaWikiServices::getInstance()->getUserOptionsLookup()
					->getOption( $user, 'gender', 'unknown' );
			} else {
				$name = str_replace("imported>", "", $this->actorName);
				$commentPoster =  htmlspecialchars($name, ENT_QUOTES );
				$CommentReplyTo = $commentPoster;
				$CommentReplyToGender = 'unknown'; // Undisclosed gender as anon user
			}
		} else {
			$anonMsg = $this->msg( 'comments-anon-name' )->inContentLanguage()->plain();
			$commentPoster = $anonMsg . ' #' . $anonList[$this->ip];
			$CommentReplyTo = $anonMsg;
			$CommentReplyToGender = 'unknown'; // Undisclosed gender as anon user
		}

		// Comment delete button for privileged users
		$userObj = $this->getUser();
		$dlt = '';

		if (
			$userObj->isAllowed( 'commentadmin' )
			// Allow users to delete their own comments if that feature is enabled in
			// site configuration
			// @see https://phabricator.wikimedia.org/T147796
			//
			// upd by @endevir: do not allow :DDD
			// || $userObj->isAllowed( 'comment-delete-own' ) && $this->isOwner( $userObj )
		) {
			$dlt = ' | <span class="c-delete">' .
				'<a href="javascript:void(0);" rel="nofollow" class="comment-delete-link" data-comment-id="' .
				$this->id . '">' .
				$this->msg( 'comments-delete-link' )->plain() . 
				'</a>';
 
			if ($this->hidden) {
				$dlt = $dlt . ' | <a href="javascript:void(0);" rel="nofollow" class="comment-unhide-link" data-comment-id="' .
				$this->id . '">Снять принудительное скрытие</a>';
			} else {
				$dlt = $dlt . ' | <a href="javascript:void(0);" rel="nofollow" class="comment-hide-link" data-comment-id="' .
				$this->id . '">Скрыть принудительно</a>';
			}

			$dlt = $dlt . '</span>';
		}

		// Reply Link (does not appear on child comments)
		$replyRow = '';
		if ( $userObj->isAllowed( 'comment' ) ) {
			if ( $this->parentID == 0 ) {
				if ( $replyRow ) {
					$replyRow .= wfMessage( 'pipe-separator' )->plain();
				}
				$replyRow .= " | <a href=\"#end\" rel=\"nofollow\" class=\"comments-reply-to\" data-comment-id=\"{$this->id}\" data-comments-safe-username=\"" .
					htmlspecialchars( $CommentReplyTo, ENT_QUOTES ) . "\" data-comments-user-gender=\"" .
					htmlspecialchars( $CommentReplyToGender ) . '">' .
					wfMessage( 'comments-reply' )->plain() . '</a>';
			}
		}

		$comment_class = ' ';
		if ( $this->parentID == 0 ) {
			$comment_class .= 'f-message ';
		} else {
			$comment_class .= 'r-message ';
		}

		if (intval($this->currentScore) < -2 && intval($this->currentScore) > -10) {
			$comment_class .= 'c-message-negative-scored-' . abs(intval($this->currentScore)) . ' ';
		}
		if (intval($this->currentScore) <= -10) {
			$comment_class .= 'c-message-negative-scored-9 ';
		}

		// Display Block icon for logged in users for comments of users
		// that are already not in your block list
		$blockLink = '';
		if (
			$userObj->isRegistered() &&
			$userObj->getActorId() != $this->actorID &&
			!( in_array( $this->user->getId(), $blockList ) )
		) {
			$blockLink = '<a href="javascript:void(0);" rel="nofollow" class="comments-block-user" data-comments-safe-username="' .
				htmlspecialchars( $this->username, ENT_QUOTES ) .
				'" data-comments-comment-id="' . $this->id . '" data-comments-user-id="' .
				$this->user->getId() . "\">
					<img src=\"{$wgExtensionAssetsPath}/Comments/resources/images/block.svg\" border=\"0\" alt=\"\"/>
				</a>";
		}

		// Default avatar image, if SocialProfile extension isn't enabled
		if (
			$this->user->isRegistered()
		) {
			$avatarImg = '<img style="width: 52px; border: none; border-radius: 50%;" src="https://api.dicebear.com/8.x/croodles/svg?seed=' . $this->user->getId() . '" alt="" border="0" />';
		} else {
			$avatarImg = '<img style="width: 52px; border: none; border-radius: 50%;" src="https://api.dicebear.com/8.x/croodles/svg?seed=' . $this->ip . '" alt="" border="0" />';
		}
		// If SocialProfile *is* enabled, then use its wAvatar class to get the avatars for each commenter
		if ( class_exists( 'wAvatar' ) ) {
			$avatar = new wAvatar( $this->user->getId(), 'ml' );
			$avatarImg = $avatar->getAvatarURL() . "\n";
		}

		$output = "<div id='comment-{$this->id}' class='c-item {$containerClass}'{$style}>" . "\n";
		$output .= "<div class=\"c-avatar\">{$avatarImg}</div>" . "\n";
		$output .= '<div class="c-container">' . "\n";
		$output .= '<div class="c-user">' . "\n";
		$output .= "{$commentPoster}";
		$output .= "<span class=\"c-user-level\">{$commentPosterLevel}</span> {$blockLink}" . "\n";

		AtEase::suppressWarnings(); // E_STRICT bitches about strtotime()

		$should_hide_comment = (
			intval($this->currentScore) <= -3 ||
			$this->hidden ||
			!CommentObsceneCensorRus::isAllowed($this->text) || // для матерных слов
			CommentFunctions::isSpam($this->text)
		) && $this->getUser()->isAnon();

		$output .= '<div class="c-time">' .
			$this->date .
			'&nbsp;<i>(' .
			wfMessage(
				'comments-time-ago',
				CommentFunctions::getTimeAgo( strtotime( $this->date ) )
			)->parse() . ')</i></div>' . "\n";
		AtEase::restoreWarnings();

		$output .= '<div class="c-score">' . "\n";
		$output .= $this->getScoreHTML();
		$output .= '</div>' . "\n";

		$output .= '</div>' . "\n"; 
		$output .= "<div class=\"c-comment {$comment_class}\">" . "\n";
		if ($should_hide_comment) {
			if ( $this->page->title ) {
				$auth_link = '/index.php?title=Служебная:OAuth2Client/redirect&returnto=' . $this->page->title->getPrefixedUrl() . "%23comment-{$this->id}";
			} else {
				$auth_link = '/index.php?title=Служебная:OAuth2Client/redirect';
			}
			$output .= '<i><a href="/index.php?title=НЛО" target="_blank" rel="noopener noreferer">НЛО</a> прилетело и оставило это здесь.</i>'; // <br>Чтобы посмотреть, <a href="' . $auth_link . '">станьте инопланетянином</a>
		} else {
			$output .= $this->getText();
		}
		$output .= '</div>' . "\n";
		$output .= '<div class="c-actions">' . "\n";
		if ( $this->page->title ) { // for some reason doesn't always exist
			$output .= '<a href="' . htmlspecialchars( $this->page->title->getFullURL() ) . "#comment-{$this->id}\" rel=\"nofollow\">" .
			$this->msg( 'comments-permalink' )->plain() . '</a> ';
		}
		if ( $replyRow || $dlt ) {
			$output .= "{$replyRow} {$dlt}" . "\n";
		}
		$output .= '</div>' . "\n";
		$output .= '</div>' . "\n";
		$output .= '<div class="visualClear"></div>' . "\n";
		$output .= '</div>' . "\n";

		return $output;
	}

	/**
	 * Get the HTML for the comment score section of the comment
	 *
	 * @return string
	 */
	function getScoreHTML() {
		$output = '';

		if ( $this->page->allowMinus == true || $this->page->allowPlus == true ) {
			$output .= '<span class="c-score-title">' .
				wfMessage( 'comments-score-text' )->plain() .
				" <span id=\"Comment{$this->id}\">{$this->currentScore}</span></span>";

			// Voting is possible only when database is unlocked
			if ( !MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
				if ($this->getUser()->isAnon()) {
					$output .= "<i style=\"font-weight: 100;\" ><br />(Авторизуйтесь, чтобы оценить)</i>";
					return $output;
				}
				// You can only vote for other people's comments, not for your own
				if ( $this->getUser()->getActorId() != $this->actorID ) {
					$output .= "<span id=\"CommentBtn{$this->id}\">";
					if ( $this->page->allowPlus == true ) {
						$output .= $this->getVoteLink( 1 );
					}

					if ( $this->page->allowMinus == true ) {
						$output .= $this->getVoteLink( -1 );
					}
					$output .= '</span>';
				} else {
					$output .= wfMessage( 'word-separator' )->plain() . wfMessage( 'comments-you' )->plain();
				}
			}
		}

		return $output;
	}
}
