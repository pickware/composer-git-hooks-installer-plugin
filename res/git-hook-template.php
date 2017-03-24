#!/usr/bin/env php
<?php

// Collect all hooks of this hook type
$hookType = basename(__FILE__);
$rawHookCollection = file_get_contents(__DIR__ . '/viison-hooks.json');
$hookCollection = json_decode($rawHookCollection, true);
$flattenedHooks = array();
foreach ($hookCollection[$hookType] as $packageName => $hooks) {
    $flattenedHooks = array_merge($flattenedHooks, array_values($hooks));
}

// Execute collected hooks
$workingDir = getcwd();
foreach ($flattenedHooks as $hookPath) {
    echo sprintf("Executing git %s hook '%s'...\n", $hookType, $hookPath);
    $output = array();
    $return = null;
    exec(realpath(__DIR__ . '/' . $hookPath) . ' "' . $workingDir . '" 2>&1', $output, $return);
    if (count($output) > 0) {
        echo "\t" . implode("\n\t", $output) . "\n";
    }
    if ($return !== 0) {
        echo 'Hook failed with exit code ' . $return . "\n";
        exit($return);
    }
}

echo sprintf("All %s hooks passed.\n", $hookType);
exit(0);
