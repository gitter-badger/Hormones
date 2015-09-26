<?php

$dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . "Hormones";

$opts = getopt("", ["out:"]);
if(!isset($opts["out"])){
	die("Usage: " . PHP_BINARY . " --out <output file>");
}
/** @noinspection PhpUsageOfSilenceOperatorInspection */
@unlink($opts["out"]);
/** @noinspection PhpUsageOfSilenceOperatorInspection */
@mkdir(dirname($opts["out"]));
$phar = new Phar($opts["out"]);
$phar->setStub('<?php require_once "phar://" . __FILE__ . "/entry.php"; __HALT_COMPILER();');
$phar->setSignatureAlgorithm(Phar::SHA1);
$phar->startBuffering();
$phar->buildFromDirectory($dir);
$phar->stopBuffering();
exec("git add -A", $output);
echo "Build created at " . realpath($opts["out"]);
