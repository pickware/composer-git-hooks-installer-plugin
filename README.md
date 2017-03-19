# VIISON/composer-git-hooks-installer-plugin
A composer installer plugin that installs required git hooks upon executing `composer install` or `composer update`.

**Don't require this package in your project, to which git hooks should be added. Instead require it in all packages that provide installable git hooks.**

## Supported git hooks

This plugin supports any hook files that are supported by git. That is, you can use any files to your hook paths, as long as they are executable in the shell and match one of the following filenames:

* `applypatch-msg`
* `commit-msg`
* `post-update`
* `pre-applypatch`
* `pre-commit`
* `pre-push`
* `pre-rebase`
* `pre-receive`
* `prepare-commit-msg`
* `update`

## Usage

1. This plugin is not published on [Packagist](https://packagist.org/) yet. To add it to your Composer-based project, add the corresponding repository for it to your `composer.json`. Composer's documentation describes how to [work with repositories](https://getcomposer.org/doc/05-repositories.md#vcs).
2. Set the `type` in your `composer.json` to `viison-git-hooks`
3. Add the plugin to your `composer.json` as a dependency:

    ```json
        ...
        "require": {
            ...
            "viison/composer-git-hooks-installer-plugin": "^1.0",
            ...
        },
        ...
    ```

4. Define the paths to all available groups of git hooks in your `composer.json`. The path of a hook group must point to a directory in your package using a path relative from the package root:

    ```json
        ...
        "extra": {
            ...
            "available-viison-git-hooks": {
                "php-project": "git-hooks/php-project/"
            },
            ...
        },
        ...
    ```

5. In the package you wish to use git hooks in, require all necessary `viison-git-hooks` packages and specify the desired hooks:

    ```json
        ...
        "require": {
            ...
            "vendor/some-package-containing-hooks": "^1.0",
            "vendor/another-package-containing-hooks": "^2.0",
            ...
        },
        ...
        "extra": {
            ...
            "required-viison-git-hooks": {
                "vendor/some-package-containing-hooks": ["php-project"]
                "vendor/another-package-containing-hooks": ["javascrip-project", "spell-checker"]
            },
            ...
        },
        ...
    ```
