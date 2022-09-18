FROM php:7.4-apache

# System dependencies
RUN set -eux; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		git \
		librsvg2-bin \
		imagemagick \
		# Required for SyntaxHighlighting
		python3 \
	; \
	rm -rf /var/lib/apt/lists/*

# Install the PHP extensions we need
RUN set -eux; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libicu-dev \
		libonig-dev \
	; \
	\
	docker-php-ext-install -j "$(nproc)" \
		calendar \
		intl \
		mbstring \
		mysqli \
		opcache \
	; \
	\
	pecl install APCu-5.1.21; \
	docker-php-ext-enable \
		apcu \
	; \
	rm -r /tmp/pear; \
	\
	# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# Enable Short URLs
RUN set -eux; \
	a2enmod rewrite; \
	{ \
		echo "<Directory /var/www/html>"; \
		echo "  RewriteEngine On"; \
		echo "  RewriteCond %{REQUEST_FILENAME} !-f"; \
		echo "  RewriteCond %{REQUEST_FILENAME} !-d"; \
		echo "  RewriteRule ^ %{DOCUMENT_ROOT}/index.php [L]"; \
		echo "</Directory>"; \
	} > "$APACHE_CONFDIR/conf-available/short-url.conf"; \
	a2enconf short-url

# Enable AllowEncodedSlashes for VisualEditor
RUN sed -i "s/<\/VirtualHost>/\tAllowEncodedSlashes NoDecode\n<\/VirtualHost>/" "$APACHE_CONFDIR/sites-available/000-default.conf"

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# SQLite Directory Setup
RUN set -eux; \
	mkdir -p /var/www/data; \
	chown -R www-data:www-data /var/www/data

# Version
ENV MEDIAWIKI_MAJOR_VERSION 1.35
ENV MEDIAWIKI_VERSION 1.35.7

# MediaWiki setup
RUN set -eux; \
	fetchDeps=" \
		gnupg \
		dirmngr \
	"; \
	apt-get update; \
	apt-get install -y --no-install-recommends $fetchDeps; \
	\
	curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
	curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig; \
	export GNUPGHOME="$(mktemp -d)"; \
# gpg key from https://www.mediawiki.org/keys/keys.txt
	gpg --batch --keyserver keyserver.ubuntu.com --recv-keys \
		D7D6767D135A514BEB86E9BA75682B08E8A3FEC4 \
		441276E9CCD15F44F6D97D18C119E1A64D70938E \
		F7F780D82EBFB8A56556E7EE82403E59F9F8CD79 \
		1D98867E82982C8FE0ABC25F9B69B3109D3BB7B0 \
	; \
	gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz; \
	tar -x --strip-components=1 -f mediawiki.tar.gz; \
	gpgconf --kill all; \
	rm -r "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz; \
	chown -R www-data:www-data extensions skins cache images; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $fetchDeps; \
	rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y vim wget curl zip p7zip-full ca-certificates openssh-client && \
    mkdir ~/.ssh && chmod 700 ~/.ssh && ssh-keyscan -t rsa github.com >> ~/.ssh/known_hosts && \
    update-ca-certificates && wget -cO - https://getcomposer.org/download/latest-2.x/composer.phar > composer.phar && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

RUN composer config --no-plugins allow-plugins.composer/installers true && \
    composer require --no-update mediawiki/semantic-media-wiki:3.2.3  && \
    composer require --no-update mediawiki/validator:2.2.3  && \
    composer require --no-update mediawiki/header-footer:3.0.1  && \
    composer require --no-update mediawiki/page-forms:5.4  && \
    composer require --no-update mediawiki/semantic-result-formats:4.0.1  && \
    composer update --no-dev --no-interaction

# Installing MediaWiki/Ratings
# https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/Ratings/+/refs/heads/master
# https://www.mediawiki.org/w/index.php?oldid=1435552
RUN wget --no-check-certificate -O extensions/Ratings.tar.gz https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/Ratings/+archive/refs/heads/master.tar.gz && \
    mkdir extensions/Ratings && \
    tar -xvf extensions/Ratings.tar.gz --directory extensions/Ratings

# Installing Math extension
COPY extensions/Math-REL1_35-4ec097f.tar.gz extensions/Math.tar.gz
RUN mkdir extensions/Math && \
    tar -xvf extensions/Math.tar.gz --directory extensions

# Installing pluggable auth
RUN wget --no-check-certificate -O extensions/PluggableAuth.tar.gz https://extdist.wmflabs.org/dist/extensions/PluggableAuth-REL1_35-e2589cd.tar.gz && \
    mkdir extensions/PluggableAuth && \
    tar -xvf extensions/PluggableAuth.tar.gz --directory extensions

# Installing SemanticInternalObjects extension
RUN wget --no-check-certificate -O extensions/SemanticInternalObjects.tar.gz https://extdist.wmflabs.org/dist/extensions/SemanticInternalObjects-REL1_35-d03dda4.tar.gz && \
    tar -xvf extensions/SemanticInternalObjects.tar.gz --directory extensions

# Installing CharInsert extension
RUN wget --no-check-certificate -O extensions/CharInsert.tar.gz https://extdist.wmflabs.org/dist/extensions/CharInsert-REL1_35-89fa92f.tar.gz && \
    tar -xvf extensions/CharInsert.tar.gz --directory extensions
 
# Installing SimpleFeed extension
RUN cd ./extensions && git clone https://github.com/jonnylamb/simplefeed.git && chown -R www-data: ./simplefeed/*

# Installing RatePage extension
RUN wget -O extensions/RatePage.tar.gz https://gitlab.com/nonsensopedia/extensions/ratepage/-/archive/REL1_35/ratepage-REL1_35.tar.gz && \
    tar -xvf extensions/RatePage.tar.gz --directory extensions && \
    mv extensions/ratepage-REL1_35 extensions/RatePage

# Installing OAuth2 Client extension (https://www.mediawiki.org/wiki/Extension:OAuth2_Client )
# RUN cd extensions && git clone https://github.com/Schine/MW-OAuth2Client.git && cd MW-OAuth2Client && git submodule update --init && cd vendors/oauth2-client && composer install
COPY extensions/MW-OAuth2Client extensions/MW-OAuth2Client
RUN cd extensions/MW-OAuth2Client/vendors/oauth2-client && composer install

# Installing Comments extension
# COPY extensions/Comments-master.tar.gz extensions/Comments.tar.gz
# RUN tar -xvf extensions/Comments.tar.gz --directory extensions && \
	# mv extensions/mediawiki-extensions-Comments-master extensions/Comments
COPY extensions/Comments extensions/Comments

# Installing MobileFrontend extension
COPY extensions/MobileFrontend-REL1_35-a4f595e.tar.gz extensions/MobileFrontend.tar.gz
RUN mkdir extensions/MobileFrontend && \
    tar -xvf extensions/MobileFrontend.tar.gz --directory extensions

# Installing Citizen skin
COPY skins/citizen.zip skins/citizen.zip
RUN unzip skins/citizen.zip -d skins && \
	mv skins/mediawiki-skins-Citizen-main skins/Citizen

# Enable mod_remoteip apache2 module to fetch real client IP from X-Forwarded-For header
RUN a2enmod remoteip
COPY apache2-remoteip.conf /etc/apache2/conf-enabled/remoteip.conf

CMD ["apache2-foreground"]
