#!/bin/sh
set -e

isapplication=${IS_APPLICATION:-false}

if [ "$isapplication" != "false" ]; then
    php artisan migrate --force

    php artisan mca:setup-initial-data || echo 'Failed to setup initial data'
fi

# Run the default command
exec "$@"
