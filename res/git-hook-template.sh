#!/usr/bin/env bash

# e.g. "pre-commit"
hook_type=$(basename $0)

# git hooks are always executed inside the repository root
repo_dir=$(pwd)

# Use PHP to extract the list of hooks to run
hooks=$(php -- $repo_dir $hook_type <<'ENDPHP'
<?php
$repoDir = $argv[1];
$hookType = $argv[2];
$rawHookCollection = file_get_contents($repoDir . '/.git/hooks/viison-hooks.json');
$hookCollection = json_decode($rawHookCollection, true);
foreach ($hookCollection[$hookType] as $packageName => $hooks) {
    foreach ($hooks as $hook) {
        echo "${repoDir}/.git/hooks/${hook}\n";
    }
}
ENDPHP
)

for hook in $hooks
do
    echo "Executing git ${hook_type} hook ${hook}..."
    ${hook}
    hook_result=$?
    if [ ! $hook_result -eq 0 ]
    then
        echo "Hook failed with exit code ${hook_result}."
        exit ${hook_result}
    fi
done;

echo "All ${hook_type} hooks passed."
