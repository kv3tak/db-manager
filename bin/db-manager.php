#!/usr/bin/env php
<?php
// application.php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \Kv3tak\Command\ExportCommand());
$application->add(new \Kv3tak\Command\ImportCommand());

$application->run();


