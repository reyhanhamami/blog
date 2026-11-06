<?php

// Loopback-only Apache QA; never modifies the system WAMP configuration.
$root = str_replace('\\', '/', realpath(__DIR__.'/../..'));
$apache = str_replace('\\', '/', getenv('LIVEWIRE_QA_APACHE_ROOT') ?: 'C:/wamp64/bin/apache/apache2.4.58');
$php = str_replace('\\', '/', getenv('LIVEWIRE_QA_PHP_ROOT') ?: 'C:/wamp64/bin/php/php8.3.0');
$qa = $root.'/storage/app/livewire-transport-qa';
if (! is_file($apache.'/bin/httpd.exe') || ! is_file($php.'/php8apache2_4.dll')) {
    throw new RuntimeException('An existing Windows Apache and matching PHP module are required.');
}
if (! is_dir($qa)) {
    mkdir($qa, 0777, true);
}
$config = <<<CONF
ServerRoot "$apache"
Listen 127.0.0.1:8767
ServerName 127.0.0.1:8767
PidFile "$qa/httpd.pid"
ErrorLog "$qa/apache-error.log"
LogLevel warn
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule env_module modules/mod_env.so
LoadModule php_module "$php/php8apache2_4.dll"
PHPIniDir "$qa"
TypesConfig "$apache/conf/mime.types"
DocumentRoot "$root/public"
DirectoryIndex index.php
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$root/public">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
<FilesMatch \.php$>
    SetHandler application/x-httpd-php
</FilesMatch>
SetEnv APP_ENV production
SetEnv APP_URL http://127.0.0.1:8767
SetEnv DB_CONNECTION sqlite
SetEnv DB_DATABASE "$root/storage/app/public-theme-qa.sqlite"
SetEnv SESSION_DRIVER file
SetEnv CACHE_STORE array
CONF;
$ini = <<<INI
extension_dir="$php/ext"
extension=php_pdo_sqlite.dll
extension=php_sqlite3.dll
extension=php_mbstring.dll
extension=php_openssl.dll
extension=php_fileinfo.dll
extension=php_gd.dll
extension=php_intl.dll
date.timezone=Asia/Jakarta
memory_limit=256M
display_errors=Off
log_errors=On
error_log="$qa/php-error.log"
zend.exception_ignore_args=On
INI;
file_put_contents($qa.'/httpd.conf', $config);
file_put_contents($qa.'/php.ini', $ini);
echo "Loopback Apache QA configuration ready.\n";
