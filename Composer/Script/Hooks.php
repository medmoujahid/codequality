<?php

namespace Med\Codequality\Composer\Script;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class Hooks
{
    const GIT_HOOK_PRECOMMIT = 'pre-commit';

    const GIT_HOOK_PATH      = '/../../../../../.git/hooks/';
    const QUALITY_HOOK       = '/../../Resources/hooks/pre-commit.php';

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
			exec("chmod +x ".$gitHookPath);
        }

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
