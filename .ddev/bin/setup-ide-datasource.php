#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * USAGE
 *
 * run this script with the input from 'ddev describe -j' on STDINT:
 *
 * ddev describe -j | bin/setup-ide-datasource.php
 *
 * It will parse the DB port number and add it to the jdbc database resource URL. If no
 * .idea/dataSources.xml file is present it will copy it from .idea/dataSources.xml.dist
 *
 * The script is limited to resource URLs looking like this:
 *
 * jdbc:mariadb://localhost:49166/db_name
 *
 * the scheme (mariadb), host, port and path might vary but jdbc has to be in place
 * and no other URL parts are considered
 */

namespace Inpsyde\SetupIde;

const FILE_DATA_SOURCES = '/dataSources.xml';
const FILE_TEMPLATE_DATA_SOURCES = '/dataSources.xml.dist';

$ideaDir = dirname(__DIR__, 2) . '/.idea';
$relIdeDir = basename($ideaDir);

// incomplete reverse part of parse_url
function build_url(array $urlParts): string
{
    $scheme = $urlParts['scheme'] ?? 'mariadb';
    $host = $urlParts['host'] ?? 'localhost';
    $port = $urlParts['port'] ?? '3306';
    $path = $urlParts['path'] ?? '';

    return $scheme . '://' . $host . ':' . $port . $path;
}

try {

    $sourceName = getenv('SOURCE_NAME');
    if(!$sourceName) {
        throw new \RuntimeException("Specify a SOURCE_NAME environment variable to change port for");
    }

    if (! file_exists($ideaDir . FILE_DATA_SOURCES)
        && file_exists($ideaDir . FILE_TEMPLATE_DATA_SOURCES)) {
       $templateXml = new \SimpleXMLElement(file_get_contents($ideaDir . FILE_TEMPLATE_DATA_SOURCES));
       $templateSourceNode = $templateXml->xpath('//data-source[@name="DDEV_PROJECT"]')[0];
       $templateSourceNode['name'] = $sourceName;
       $templateXml->asXML($ideaDir . FILE_DATA_SOURCES);

       echo "Placed {$relIdeDir}" .  FILE_DATA_SOURCES . " as it did not exist yet" . PHP_EOL;
    }

    $stdin = '';
    while ($line = fgets(STDIN)) {
        $stdin .= $line;
    }

    $ddevParameter = json_decode($stdin, true);
    if (! $ddevParameter) {
        throw new \RuntimeException("Could not decode input from STDIN. Make sure its valid JSON");
    }

    if (! isset($ddevParameter['raw']['dbinfo']['published_port'])) {
        throw new \RuntimeException("Missing path .raw.dbinfo.published_port");
    }

    $dbPort = (int)$ddevParameter['raw']['dbinfo']['published_port'];

    $dataSource = new \SimpleXMLElement(file_get_contents($ideaDir . FILE_DATA_SOURCES));
    $dbUrlNodes = $dataSource->xpath("//data-source[@name='{$sourceName}']/jdbc-url");

    if (empty($dbUrlNodes)) {
        throw new \RuntimeException(
            "Could not find xPath //data-source[@name='". $sourceName . "']/jdbc-url from .idea/dataSource.xml"
        );
    }
    $dbUrlNode = $dbUrlNodes[0];
    // URL is actually invalid 'jdbc:mariadb://localhost:49160/db_test'
    $dbUrl = str_replace('jdbc:', '', (string)$dbUrlNode);
    $dbUrlParts = parse_url((string)$dbUrl);

    $recentPort = (int)$dbUrlParts['port'];
    if ($recentPort === $dbPort) {
        echo "DB ports did not change" . PHP_EOL;
        exit(0);
    }
    $dbUrlParts['port'] = $dbPort;
    $dbUrlNode[0] = 'jdbc:' . build_url($dbUrlParts);

    $dataSource->asXML($ideaDir . FILE_DATA_SOURCES);

    echo "DB Port changed from {$recentPort} to {$dbPort} for data source {$sourceName} in {$relIdeDir}" . FILE_DATA_SOURCES . PHP_EOL;

} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

