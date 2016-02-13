<?php

namespace codequalit\Composer\Script;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class Hooks
{
    const GIT_HOOK_PRECOMMIT = 'pre-commit';
    const QUALITY_FIXER_FILE = 'fixPHPCsFile.ssh';

    const GIT_HOOK_PATH      = '/../../../../../../../.git/hooks/';
    const BIN_PATH           = '/../../../../../../../bin/';
    const QUALITY_HOOK       = '/../../Resources/hooks/pre-commit.php';
    const QUALITY_FIXER_PATH = '/../../Resources/scripts/';

    public static function setHooks(Event $event)
    {
        $fs = new Filesystem();

        if (false === $event->isDevMode()) {
            return;
        }

        $hookdir = self::getAbsolutePath(self::GIT_HOOK_PATH);

        //nothing to do we are not in a git project
        if (false == $fs->exists($hookdir)) {
            return;
        }

        $event->getIO()->write('Installing the CodeQuality HOOKS');

        $gitHookPath = sprintf('%s%s', $hookdir, self::GIT_HOOK_PRECOMMIT);

        $gitHook = @file_get_contents($gitHookPath);
        $docHook = @file_get_contents(self::getAbsolutePath(self::QUALITY_HOOK));

        if ($gitHook !== $docHook) {
            file_put_contents($gitHookPath, $docHook);
        }

        $fixScript = @file_get_contents(self::getAbsolutePath(self::QUALITY_FIXER_PATH . self::QUALITY_FIXER_FILE));
        $path      = self::getAbsolutePath(self::BIN_PATH . self::QUALITY_FIXER_FILE);
        file_put_contents($path, $fixScript);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public static function getAbsolutePath($path)
    {
        return __DIR__ . $path;
    }
}
