<?php
# This file was automatically generated by the MediaWiki 1.35.7
# installer. If you make manual changes, please keep track in case you
# need to recreate them later.
#
# See includes/DefaultSettings.php for all configurable settings
# and their default values, but don't forget to make changes in _this_
# file, not there.
#
# Further documentation for configuration settings may be found at:
# https://www.mediawiki.org/wiki/Manual:Configuration_settings

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}


## Uncomment this to disable output compression
# $wgDisableOutputCompression = true;

$wgSitename = "ВикиФизтех";

## The URL base path to the directory containing the wiki;
## defaults for all runtime URL paths are based off of this.
## For more information on customizing the URLs
## (like /w/index.php/Page_title to /wiki/Page_title) please see:
## https://www.mediawiki.org/wiki/Manual:Short_URL
$wgScriptPath = "";

## The protocol and server name to use in fully-qualified URLs
$wgServer = "{{ mediawiki_server_schema }}://{{ mediawiki_server_domain }}";

## The URL path to static resources (images, scripts, etc.)
$wgResourceBasePath = $wgScriptPath;

## The URL paths to the logo.  Make sure you change this from the default,
## or else you'll overwrite your logo when you upgrade!
# $wgLogos = [ '1x' => "$wgResourceBasePath/resources/assets/wiki.png" ];
$wgLogos = [ '1x' => "/images/mediawiki_logo.png"];
$wgFavicon = "/images/mediawiki_favicon.ico";

## UPO means: this is also a user preference option

$wgEnableEmail = false;
$wgEnableUserEmail = true; # UPO

$wgEmergencyContact = "apache@🌻.invalid";
$wgPasswordSender = "apache@🌻.invalid";

$wgEnotifUserTalk = false; # UPO
$wgEnotifWatchlist = false; # UPO
$wgEmailAuthentication = true;

## Database settings
$wgDBtype = "mysql";
$wgDBserver = "{{ mediawiki_mysql_host }}";
$wgDBname = "{{ mediawiki_mysql_db }}";
$wgDBuser = "{{ mediawiki_mysql_user }}";
$wgDBpassword = "{{ mediawiki_mysql_password }}";

# MySQL specific settings
$wgDBprefix = "";

# MySQL table options to use during installation or update
$wgDBTableOptions = "ENGINE=InnoDB, DEFAULT CHARSET=utf8mb4";

# Shared database table
# This has no effect unless $wgSharedDB is also set.
$wgSharedTables[] = "actor";

## Shared memory settings
$wgMainCacheType = CACHE_MEMCACHED;
$wgMemCachedServers = [ 'memcached:11211' ];

## To enable image uploads, make sure the 'images' directory
## is writable, then set this to true:
$wgEnableUploads = true;
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";
$wgGenerateThumbnailOnParse = true;

# InstantCommons allows wiki to use images from https://commons.wikimedia.org
$wgUseInstantCommons = true;

# Periodically send a pingback to https://www.mediawiki.org/ with basic data
# about this MediaWiki instance. The Wikimedia Foundation shares this data
# with MediaWiki developers to help guide future development efforts.
$wgPingback = false;

## If you use ImageMagick (or any other shell command) on a
## Linux server, this will need to be set to the name of an
## available UTF-8 locale. This should ideally be set to an English
## language locale so that the behaviour of C library functions will
## be consistent with typical installations. Use $wgLanguageCode to
## localise the wiki.
$wgShellLocale = "C.UTF-8";

## Set $wgCacheDirectory to a writable directory on the web server
## to make your wiki go slightly faster. The directory should not
## be publicly accessible from the web.
#$wgCacheDirectory = "$IP/cache";

# Site language code, should be one of the list in ./languages/data/Names.php
$wgLanguageCode = "ru";

$wgSecretKey = "{{ mediawiki_secret }}";

# Changing this will log out all existing sessions.
$wgAuthenticationTokenVersion = "1";

# Site upgrade key. Must be set to a string (default provided) to turn on the
# web installer while LocalSettings.php is in place
$wgUpgradeKey = "3f1cfea8e2fc4f1b";

## For attaching licensing metadata to pages, and displaying an
## appropriate copyright notice / icon. GNU Free Documentation
## License and Creative Commons licenses are supported so far.
$wgRightsPage = ""; # Set to the title of a wiki page that describes your license/copyright
$wgRightsUrl = "";
$wgRightsText = "";
$wgRightsIcon = "";

# Path to the GNU diff3 utility. Used for conflict resolution.
$wgDiff3 = "/usr/bin/diff3";

## Default skin: you can change the default skin. Use the internal symbolic
## names, ie 'vector', 'monobook':
$wgDefaultSkin = "vector";
$wgDefaultMobileSkin = "citizen";

#Set Default Timezone
$wgLocaltimezone = "Europe/Moscow";
date_default_timezone_set( $wgLocaltimezone );

# Enabled skins.
# The following skins were automatically enabled:
wfLoadSkin( 'MonoBook' );
wfLoadSkin( 'Timeless' );
wfLoadSkin( 'Vector' );
wfLoadSkin( 'Citizen' );


# Enabled extensions. Most of the extensions are enabled by adding
# wfLoadExtension( 'ExtensionName' );
# to LocalSettings.php. Check specific extension documentation for more details.
# The following extensions were automatically enabled:
wfLoadExtension( 'CategoryTree' );
wfLoadExtension( 'CharInsert' );
wfLoadExtension( 'Cite' );
wfLoadExtension( 'CiteThisPage' );
wfLoadExtension( 'CodeEditor' );
wfLoadExtension( 'Comments' );
// require_once "$IP/extensions/Comments/CommentsOfTheDay.php";

wfLoadExtension( 'ConfirmEdit' );
wfLoadExtension( 'Gadgets' );
wfLoadExtension( 'HeaderFooter' );
wfLoadExtension( 'ImageMap' );
wfLoadExtension( 'InputBox' );
wfLoadExtension( 'Interwiki' );
wfLoadExtension( 'LocalisationUpdate' );
wfLoadExtension( 'Math' );
wfLoadExtension( 'MultimediaViewer' );
wfLoadExtension( 'Nuke' );
wfLoadExtension( 'OATHAuth' );
wfLoadExtension( 'PageForms' );
wfLoadExtension( 'PageImages' );
wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'PdfHandler' );
# wfLoadExtension( 'PluggableAuth' );
wfLoadExtension( 'Poem' );
wfLoadExtension( 'Renameuser' );
wfLoadExtension( 'ReplaceText' );
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'SecureLinkFixer' );
wfLoadExtension( 'SemanticMediaWiki' );
wfLoadExtension( 'SemanticResultFormats' );
wfLoadExtension( 'SpamBlacklist' );
wfLoadExtension( 'SyntaxHighlight_GeSHi' );
wfLoadExtension( 'TemplateData' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'TitleBlacklist' );
require_once "$IP/extensions/Validator/Validator.php";
wfLoadExtension( 'VisualEditor' );
wfLoadExtension( 'WikiEditor' );
wfLoadExtension( 'MobileFrontend' );

# End of automatically generated settings.
# Add more configuration options below.

wfLoadExtension( 'RatePage' );
wfLoadExtension( 'YouTube' );

enableSemantics( '{{ mediawiki_server_domain }}' );
require_once "$IP/extensions/SemanticInternalObjects/SemanticInternalObjects.php";

$smwgPageSpecialProperties = [ '_MDAT', '_CDAT' ];

$wgRPShowResultsBeforeVoting = true;
$wgRPImmediateSMWUpdate = true;
# $wgRPEnableSMWContests = false;
# $wgRPEnableSMWRatings = false;
# $wgRPUseMMVModule = false;

# OAuth2Client settings (https://www.mediawiki.org/wiki/Extension:OAuth2_Client#Configuration)
wfLoadExtension( 'MW-OAuth2Client' );
$wgOAuth2Client['client']['id']     = '{{ mediawiki_yandex_oauth_client_id }}'; // The client ID assigned to you by the provider
$wgOAuth2Client['client']['secret'] = '{{ mediawiki_yandex_oauth_client_secret }}'; // The client secret assigned to you by the provider

$wgOAuth2Client['configuration']['authorize_endpoint']     = 'https://oauth.yandex.ru/authorize'; // Authorization URL ?force_confirm=yes
$wgOAuth2Client['configuration']['access_token_endpoint']  = 'https://oauth.yandex.ru/token'; // Token URL
$wgOAuth2Client['configuration']['api_endpoint']           = 'https://login.yandex.ru/info?format=json'; // URL to fetch user JSON
$wgOAuth2Client['configuration']['redirect_uri']           = '{{ mediawiki_server_schema }}://{{ mediawiki_server_domain }}/index.php/Special:OAuth2Client/callback'; // URL for OAuth2 server to redirect to

$wgOAuth2Client['configuration']['username'] = 'login'; // JSON path to username
$wgOAuth2Client['configuration']['email'] = 'default_email'; // JSON path to email
$wgOAuth2Client['configuration']['real_name'] = 'real_name'; // JSON path to email

$wgOAuth2Client['configuration']['scopes'] = ''; //'openid email profile'; //Permissions
$wgOAuth2Client['configuration']['service_name'] = 'Яндекс'; // the name of your service
$wgOAuth2Client['configuration']['service_login_link_text'] = 'Войти с @phystech.edu (Яндекс-почта)'; // the text of the login link

# Restrict anonymous users from creating account or editing pages
$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['*']['edit'] = false;

# Proxy server settings
$wgUseCdn = true;
$wgCdnServers = array();
$wgCdnServers[] = "172.24.0.1";
$wgCdnServersNoPurge = [];
$wgCdnServersNoPurge[] = "171.0.0.0/8";

# Comments settings
$wgCommentsInRecentChanges = true;
$wgCommentsSortDescending = true;
$wgCommentsDefaultAvatar = "/images/default_avatar.gif";

{% if mediawiki_enable_debug %}
# Debug settings
$wgDebugLogFile = "/var/log/mediawiki/mediawiki-debug.log";
$wgDebugToolbar = false;

$wgShowExceptionDetails = true;
error_reporting( -1 );
ini_set( 'display_startup_errors', 1 );
ini_set( 'display_errors', 1 );
$wgDebugDumpSql = true;
{% else %}
$wgShowExceptionDetails = false;
$wgShowDebug = false;
$wgDevelopmentWarnings = false;
ini_set( 'display_errors', 0 );
{% endif %}
