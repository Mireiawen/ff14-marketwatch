# Get the Composer libraries
FROM "composer" as composer-build
MAINTAINER "Mira Liikanen <mir@mireiawen.net>"

# Set the workdir
WORKDIR "/usr/src/myapp"

# Copy the application files for composer
COPY "." "/usr/src/myapp"
RUN \
	composer "install"

# Build the actual image
FROM "php:7.2-apache"
MAINTAINER "Mira Liikanen <mir@mireiawen.net>"

# Specify the API key, user needs to override this
ENV XIVAPI_KEY "https://xivapi.com/app"

# Set the document root
ENV APACHE_DOCUMENT_ROOT "/usr/src/myapp/public"
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install module dependencies
RUN \
	apt-get "update"
RUN \
	apt-get -y install "libicu-dev" && \
	docker-php-ext-install "intl"
RUN \
	docker-php-ext-install "gettext"

# Set the workdir
WORKDIR "/usr/src/myapp"

# Install the composer build files
COPY --from=composer-build "/usr/src/myapp/vendor" "/usr/src/myapp/vendor"

# Install the application
COPY "." "/usr/src/myapp"
RUN \
	mkdir -p \
		"/usr/src/myapp/cache" && \
	chown "www-data:www-data" \
		"/usr/src/myapp/cache" && \
	rm -rf \
		"/usr/src/myapp/.dockerignore" \
		"/usr/src/myapp/composer.json" \
		"/usr/src/myapp/composer.lock"
