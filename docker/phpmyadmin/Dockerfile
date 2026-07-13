FROM phpmyadmin:latest

# Increase upload limit
RUN echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/phpmyadmin-misc.ini \
    && echo 'post_max_size = 64M' >> /usr/local/etc/php/conf.d/phpmyadmin-misc.ini
