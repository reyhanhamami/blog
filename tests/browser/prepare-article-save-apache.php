<?php

// Local Apache/PHP only; no WAMP service or production configuration changes.
$root = str_replace('\\', '/', realpath(__DIR__.'/../..'));
$apache = 'C:/wamp64/bin/apache/apache2.4.58';
$php = 'C:/wamp64/bin/php/php8.3.0';
$qa = $root.'/storage/app/article-save-qa';
if (! is_file($apache.'/bin/httpd.exe') || ! is_file($php.'/php8apache2_4.dll')) {
    throw new RuntimeException('Native Windows QA Apache/PHP required.');
}
if (! is_dir($qa)) {
    mkdir($qa, 0777, true);
}
$config = <<<CONF
ServerRoot "$apache"
Listen 127.0.0.1:8770
ServerName 127.0.0.1:8770
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
# Explicit QA simulation, not evidence of a production ModSecurity rule.
RewriteEngine On
RewriteCond %{REQUEST_METHOD} =POST
RewriteCond %{HTTP:X-QA-Block} ^1$
RewriteRule ^/admin/posts/[0-9]+$ - [F]
ErrorDocument 403 /admin/posts/1
SetEnv APP_ENV local
SetEnv APP_DEBUG false
SetEnv APP_URL http://127.0.0.1:8770
SetEnv DB_CONNECTION sqlite
SetEnv DB_DATABASE "$qa/article-save-qa.sqlite"
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
post_max_size=8M
display_errors=Off
log_errors=On
error_log="$qa/php-error.log"
zend.exception_ignore_args=On
INI;
file_put_contents($qa.'/httpd.conf', $config);
file_put_contents($qa.'/php.ini', $ini);
echo "Apache QA configuration ready on loopback port 8770.\n";
