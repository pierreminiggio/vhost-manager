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
        case 'q':
            break 2;
    }
}

function displayHosts(string $vhostFile, string $sslFile): void
{
    list($vhostNamesFromVhostFile, $vhostNamesFromSslFile) = readHosts($vhostFile, $sslFile);

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
    return readHostnamesFromFile($file, '<VirtualHost *:443>', '</VirtualHost>', ':443');
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
    foreach ($items as $item) {
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
