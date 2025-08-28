@echo off

call php bin/composer.phar exec php-cs-fixer fix %*
