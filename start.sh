#!/bin/bash
# Set Apache to listen on Render's PORT environment variable
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/:80>/:$PORT>/g" /etc/apache2/sites-available/000-default.conf
fi
# Start Apache in foreground
apache2-foreground
