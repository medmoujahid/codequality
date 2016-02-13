CodeQuality
=============

This bundle provides various tools ensure the code quality before each commit

The tool provided are:

- checkComposer: to ensure that the composer.lock is commited each time the composer.json is commited
- phpLint: to ensure that the files have the right format
- ymlLint: to ensure that the files have the right format
- codeStyle: to ensure that the file respect the php-cs-fixer tool provided by Fabien Potencier, the symfony owner
- codeStylePsr: to ensure that the file respect the PSR-2
- phPmd: to check the phpmd [controversial rules](http://phpmd.org/rules/controversial.html)

Installation
------------

The first step is to add the bundle to your composer.json

```json
    "require-dev": {
        "codequality": "dev-master"
    },
```

You have to add after this the script to install the hooks like this:

```json
    "scripts": {
        "post-install-cmd": [
            ...
            "codequality\\Composer\\Script\\Hooks::setHooks"
        ],
        "post-update-cmd": [
            ...
            "codequality\\Composer\\Script\\Hooks::setHooks"
        ]
    },
```

Usage:
-------

The hook is automatically launched before each commit.
The hook will check each php file that will be commited to ensure the code quality.

Exemple of executions:



TODO:
-------

- check on Windows computers if this is promptly executed without additional tools (cygwin) or (msysgit) because the hook contain a PHP [Shebang](http://fr.wikipedia.org/wiki/Shebang)
- Implement an additional Hook to check the commit message format