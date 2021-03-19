<?php

$files = require(__DIR__ . DIRECTORY_SEPARATOR . 'config.php');
$vhostFile = $files['vhost'];
$sslFile = $files['ssl'];

while (true) {
    $menuChoice = readline('l=list, a=add, q=quit : ');

    switch ($menuChoice) {
        case 'l':
            displayHosts($vhostFile, $sslFile);
            break;
        case 'a':
            askForHostInfosAndAddHost($vhostFile);
        case 'q':
            break 2;
    }
}

function displayHosts(string $vhostFile, string $sslFile): void
{
    list($vhostNamesFromVhostFile, $vhostNamesFromSslFile) = readHosts($vhostFile, $sslFile);
    sort($vhostNamesFromVhostFile);
    sort($vhostNamesFromSslFile);

    $numberOfHosts = count($vhostNamesFromVhostFile);

    if ($numberOfHosts) {
        echo PHP_EOL . $numberOfHosts . ' host' . ($numberOfHosts ? 's' : '') . ' :' . PHP_EOL;

        foreach ($vhostNamesFromVhostFile as $hostName) {
            $hasSsl = in_array($hostName, $vhostNamesFromSslFile);
            echo PHP_EOL . '- ' . $hostName . ($hasSsl ? ' ✓' : '');
        }
    }

    $orphanSslHostNames = array_diff($vhostNamesFromSslFile, $vhostNamesFromVhostFile);

    $numberOfOrphans = count($orphanSslHostNames);
    if ($numberOfOrphans) {
        if ($numberOfHosts) {
            echo PHP_EOL;
        }

        echo PHP_EOL . $numberOfOrphans . ' orphan ssl host' . ($numberOfOrphans ? 's' : '') . ' :' . PHP_EOL;

        foreach ($orphanSslHostNames as $hostName) {
            echo PHP_EOL . '- ' . $hostName;
        }
    }

    echo PHP_EOL . PHP_EOL;
}

/**
 * @return array[]
 */
function readHosts(string $vhostFile, string $sslFile): array
{
    $vhostNamesFromVhostFile = readHostnamesFromVhostFile($vhostFile);
    $vhostNamesFromSslFile = readHostnamesFromSslFile($sslFile);

    return [$vhostNamesFromVhostFile, $vhostNamesFromSslFile];
}

/**
 * @return string[]
 */
function readHostnamesFromVhostFile(string $file): array
{
    return readHostnamesFromFile($file, '<VirtualHost *:80>', '</VirtualHost>', PHP_EOL);
}

/**
 * @return string[]
 */
function readHostnamesFromSslFile(string $file): array
{
    return readHostnamesFromFile($file, '<VirtualHost *:443>', '</VirtualHost>', PHP_EOL);
}

/**
 * @return string[]
 */
function readHostnamesFromFile(
    string $file,
    string $vhostStartTag,
    string $vhostEndTag,
    string $serverNameEndTag
): array
{
    $items = parseItemsInsideTags($vhostStartTag, $vhostEndTag, file_get_contents($file));

    $names = [];
    foreach ($items as $index => $item) {

        if ($index === 0) {
            continue;
        }

        $item = trim($item);

        if ($item === '') {
            continue;
        }

        $names[] = parseServerNameInsideVhostItem($item, $serverNameEndTag);
    }

    return $names;
}

/**
 * @return string[]
 */
function parseItemsInsideTags(string $startTag, string $endTag, string $content): array
{
    $items = [];

    $explodedItemsByVhostOpenTag = explode($startTag, $content);

    foreach ($explodedItemsByVhostOpenTag as $explodedItemByVhostOpenTag) {
        $items[] = explode($endTag, $explodedItemByVhostOpenTag)[0];
    }

    return $items;
}

function parseServerNameInsideVhostItem(string $vhostItem, string $endTag): string
{
    return substr(parseItemsInsideTags('ServerName ', $endTag, $vhostItem)[1], 4);
}

function askForHostInfosAndAddHost(string $vhostFile): void
{
    $confirm = null;

    while ($confirm !== 'y') {
        $domainName = readline('Enter domain name (without "www") : ');
        $folderName = readline('Enter folder name : ');

        echo PHP_EOL . 'We are going to add domain name "' . $domainName . '" pointing to folder "' . $folderName . '"' . PHP_EOL;
        $confirm = readline('Are you sure ? (y) ');
    }
    
    appendVhostToVhostFile($domainName, $folderName, $vhostFile);

    echo PHP_EOL . PHP_EOL . 'Done !';
}

function appendVhostToVhostFile(string $domainName, string $folderName, string $file): void
{
    appendContentToFile(PHP_EOL
        . '<VirtualHost *:80>
ServerName www.' . $domainName . '
ServerAdmin pierre@miniggiodev.fr
ServerAlias ' . $domainName . '
DocumentRoot /var/www/html/' . $folderName . '/
ErrorLog /var/www/logs/' . $folderName . '_error.log
CustomLog /var/www/logs/' . $folderName . '_access.log combined
<Directory "/var/www/html/' . $folderName . '/">
    AllowOverride All
</Directory>
</VirtualHost>' . PHP_EOL, $file);
}

function appendVhostToSslFile(string $domainName, string $folderName, string $file): void
{
    appendContentToFile(PHP_EOL .
        '<VirtualHost *:443>
<Directory "/var/www/html/' . $folderName . '/">
    AllowOverride All
</Directory>
DocumentRoot "/var/www/html/' . $folderName . '"
ServerName www.' . $domainName . ':443
ServerAdmin pierre@miniggiodev.fr
ServerAlias ' . $domainName . '
ErrorLog logs/ssl_error_log
TransferLog logs/ssl_access_log
LogLevel warn
SSLEngine on
<Files ~ "\.(cgi|shtml|phtml|php3?)$">
    SSLOptions +StdEnvVars
</Files>
<Directory "/var/www/cgi-bin">
    SSLOptions +StdEnvVars
</Directory>
BrowserMatch "MSIE [2-5]"nokeepalive ssl-unclean-shutdown downgrade-1.0 force-response-1.0
CustomLog logs/ssl_request_log "%t %h %{SSL_PROTOCOL}x %{SSL_CIPHER}x \"%r\" %b"
Include /etc/letsencrypt/options-ssl-apache.conf
ServerAlias www.' . $domainName . '
SSLCertificateFile /etc/letsencrypt/live/miniggiodev.fr/cert.pem
SSLCertificateKeyFile /etc/letsencrypt/live/miniggiodev.fr/privkey.pem
SSLCertificateChainFile /etc/letsencrypt/live/miniggiodev.fr/chain.pem
</VirtualHost>' . PHP_EOL, $file);
}

function appendContentToFile(string $content, string $file): void
{
    file_put_contents($file, $content, FILE_APPEND);
}
