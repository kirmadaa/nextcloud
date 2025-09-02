FROM nextcloud:31-apache

USER root

WORKDIR /var/www/html
COPY . /var/www/html/custom_apps/mail

RUN chown -R www-data:www-data /var/www/html/custom_apps \
    && chown -R www-data:www-data /var/www/html/

USER www-data

EXPOSE 80
CMD ["apache2-foreground"]
