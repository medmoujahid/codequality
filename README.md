CodeQuality
=============

This bundle provides various tools ensure the code quality before each commit

The tools provided are:

- checkComposer: to ensure that the composer.lock is commited each time the composer.json is commited (disabled because composer.lock is ignored !!)
- phpLint: to ensure that the files have the right format
- jsonLint: to ensure that the files have the right format
- codeStyle: to execute php-cs-fixer tool on php files and fix them
- codeSnifferFixer execute phpcbf on php files and fix them (new)
- codeStylePsr: to ensure that the file respect the PSR-2
- phPmd: to check the phpmd [controversial rules](http://phpmd.org/rules/controversial.html)
- phpunit : ensure unit tests passed (phpunit --testsuite unitaire --stderr)

Installation
------------

The first step is to add the repo to your composer.json

```json
  "repositories": {
    	"med/codequelity": {
    		"url": "git@github.com:medmoujahid/codequality.git",
    		"type":"git"
    	}
    },
```

```json
    "require-dev": {
        "med/codequality": "dev-master"
    },
```

You have to add after this the script to install the hooks like this:

```json
    "scripts": {
        "post-install-cmd": [
            "Med\\Codequality\\Composer\\Script\\Hooks::setHooks"
        ],
        "post-update-cmd": [
            "Med\\Codequality\\Composer\\Script\\Hooks::setHooks"
        ]
    },
```

Usage:
-------

The hook is automatically launched before each commit.

The hook will check each php file that will be commited to ensure the code quality.

Exemple of executions:

![PHPCS errors](https://github.com/medmoujahid/codequality/blob/master/Resources/doc/phpcs.png)

![PHPCBF fix](https://github.com/medmoujahid/codequality/blob/master/Resources/doc/alltests.png)


TODO:
-------
- Implement an additional Hook to check the commit message format