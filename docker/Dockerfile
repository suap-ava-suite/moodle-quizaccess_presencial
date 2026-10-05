FROM moodlehq/moodle-php-apache:8.3

# A fixed Moodle release keeps the manual test environment reproducible.
RUN curl --fail --location --retry 3 \
        https://codeload.github.com/moodle/moodle/tar.gz/refs/tags/v4.5.12 \
        -o /tmp/moodle.tar.gz \
    && tar -xzf /tmp/moodle.tar.gz --strip-components=1 -C /var/www/html \
    && rm /tmp/moodle.tar.gz

COPY config.php.template /var/www/html/config.php
COPY is-installed.php.template /usr/local/bin/moodle-is-installed.php
COPY init.sh /docker-entrypoint.d/50-presencial-init.sh
COPY php.ini /usr/local/etc/php/conf.d/zz-presencial-local.ini

RUN chmod +x /docker-entrypoint.d/50-presencial-init.sh

WORKDIR /var/www/html
