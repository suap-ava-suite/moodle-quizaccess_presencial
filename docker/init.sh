#!/bin/sh
set -eu

mkdir -p /var/www/moodledata
chown www-data:www-data /var/www/moodledata
chmod 2770 /var/www/moodledata

installation_status=0
php /usr/local/bin/moodle-is-installed.php || installation_status=$?
case "$installation_status" in
    0)
        runuser -u www-data -- php admin/cli/upgrade.php --non-interactive
        ;;
    1)
        runuser -u www-data -- php admin/cli/install_database.php \
            --agree-license \
            --lang=pt_br \
            --fullname="Moodle local - Liberação Presencial" \
            --shortname=presencial-local \
            --adminuser="$MOODLE_ADMIN_USER" \
            --adminpass="$MOODLE_ADMIN_PASSWORD" \
            --adminemail=admin@example.com
        ;;
    *)
        exit "$installation_status"
        ;;
esac

runuser -u www-data -- php admin/cli/purge_caches.php
