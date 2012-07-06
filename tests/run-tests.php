#!/usr/bin/env php
<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   UnitTests
 */

/**
 * runtests.php - Launch PHPUnit for specific test group(s).
 *
 * Usage: runtests.sh [ -h <html-dir> ] [ -c <clover-xml-file> ] [ -g ]
 *     [ ALL | <test-group> [ <test-group> ... ] ]
 *
 * This script makes it easier to execute PHPUnit test runs from the
 * shell, using the path to the test or using @group tags defined in
 * the test suite files to run subsets of tests.
 *
 * To get a list of all @group tags: phpunit --list-groups Zend/
 *
 * @category Zend
 * @package  UnitTests
 */

// PHPUnit doesn't understand relative paths well when they are in the config file.
chdir(__DIR__);

$return = 0;
$baseCmd = 'phpunit -c ' . (file_exists('phpunit.xml') ? 'phpunit.xml' : 'phpunit.xml.dist');

$components = array();
$failures = array();

for ($i = 1; $i < $_SERVER['argc']; ++$i) {
    $arg = $_SERVER['argv'][$i];
    if ('--' == $arg) {
        ++$i;
        break;
    }

    $arg = str_replace(array('-','_'), ' ', $arg, $count);

    if ($count > 0) {
        $arg = strtolower($arg);
        $arg = ucwords($arg);
        $arg = str_replace(' ', '', $arg);
    }

    $arg = str_replace(array('\\','/','.'), ' ', $arg, $count);
    $arg = ucwords($arg);
    $arg = str_replace(' ', '/', $arg);

    if ('Zend/' != substr($arg, 0, 5)) {
        $arg = 'Zend/' . $arg;
    }
    $components[] = $arg;
}

for (; $i < $_SERVER['argc']; ++$i) {
    $baseCmd .= ' ' . $_SERVER['argv'][$i];
}

//echo $baseCmd . ' ' . implode(' ', $components) . "\n";
$result = 1;
if (empty($components)) {
    foreach (new DirectoryIterator('Zend') as $file) {
        if ($file->isDot()) {
            continue;
        }

        $name = $file->getFilename();
        if (preg_match('/^((?:[A-Z][a-z0-9]*)+)(?:\\.php)?$/', $name, $matches)) {
            $components[] = 'Zend/' . $matches[1];
        }
    }
}

foreach ($components as $component) {
    $name = str_replace('/', '\\', $component);
    echo $name;

    if (is_dir($component)) {
        echo '\\*';
    } elseif (file_exists($component . '.php')) {
        echo '.php';
    }

    echo ":\n";
    $cmd = $baseCmd . ' ' . $component;
    //passthru($cmd, $result);

    if ($result) {
        $failures[] = $name;
    }

    echo "\n";
}
echo "\n";

if (empty($failures)) {
    echo "No failures\n";
} else {
    echo 'Failures in: ' . implode(', ', $failures) . "\n";
}

return count($failures);






$phpunit_coverage = '';

$run_as     = 'paths';
$components = array();

$return = 0;






if ($argc == 1) {
    $components = getAll($phpunit_conf);
} else {
    for ($i = 1; $i < $argc; $i++) {
        $arg = $argv[$i];
        switch ($arg) {
            case '-h':
            case '--html':
                $phpunit_coverage = '--coverage-html ' . $argv[++$i];
                break;
            case '-c':
            case '--clover':
                $phpunit_coverage = '--coverage-clover ' . $argv[++$i];
                break;
            case '-g':
            case '--groups':
                $run_as = 'groups';
                break;
            case 'all':
                if ($run_as == 'paths') {
                    $components = getAll($phpunit_conf);
                }
                break;
            case 'Akismet':
            case 'Amazon':
            case 'Amazon_Ec2':
            case 'Amazon_S3':
            case 'Amazon_Sqs':
            case 'Audioscrobbler':
            case 'Delicious':
            case 'Flickr':
            case 'GoGrid':
            case 'LiveDocx':
            case 'Nirvanix':
            case 'Rackspace':
            case 'ReCaptcha':
            case 'Simpy':
            case 'SlideShare':
            case 'StrikeIron':
            case 'Technorati':
            case 'Twitter':
            case 'WindowsAzure':
            case 'Yahoo':
                $components[] = 'Zend_Service_' . $arg;
                break;
            case 'Ec2':
            case 'S3':
                $components[] = 'Zend_Service_Amazon_' . $arg;
                break;
            case 'Search':
                $components[] = 'Zend_Search_Lucene';
                break;
            default:
                if (strpos($arg, 'Zend') !== false) {
                    $components[] = $arg;
                } else {
                    $components[] = 'Zend_' . $arg;
                }
        }
    }
}

$result = 0;
if ($run_as == 'groups') {
    $groups = join(',', $components);
    echo "$groups:\n";
    $cmd = "$phpunit_bin $phpunit_opts $phpunit_coverage --group " . $groups;
    echo "{$cmd}\n";
    system($cmd, $result);
    echo "\n\n";
} else {
    foreach ($components as $component) {
        $component =   'Zend/' . basename(str_replace('_', '/', $component));
        echo "$component:\n";
        $cmd = "$phpunit_bin $phpunit_opts $phpunit_coverage " . __DIR__ . '/' . $component;
        echo "{$cmd}\n";
        system($cmd, $c_result);
        echo "\n\n";
        if ($c_result) {
            $result = $c_result;
        }
    }
}

exit($result);

// Functions
function getAll($phpunit_conf) {
    $components = array();
    $conf = simplexml_load_file($phpunit_conf);
    $excludes = $conf->xpath('/phpunit/testsuites/testsuite/exclude/text()');
    for($i = 0; $i < count($excludes); $i++) {
        $excludes[$i] = basename($excludes[$i]);
    }
    if ($handle = opendir(__DIR__ . '/Zend/')) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != '.' && $entry != '..' && !in_array($entry, $excludes)) {
                $components[] = $entry;
            }
        }
        closedir($handle);
    }
    sort($components);
    return $components;
}