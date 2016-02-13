#!/usr/bin/php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Parser;

/**
 * This class provide several test to ensure the code quality
 *
 * This class is based from the article
 * http://carlosbuenosvinos.com/write-your-git-hooks-in-php-and-keep-them-under-git-control/
 *
 */
class CodeQualityTool extends Application
{
    protected $output;
    protected $input;

    const PHP_FILES_IN_SRC = '/^src\/(.*)(\.php)$/';
    const PHP_FILES_IN_APP = '/^app\/(.*)(\.php)$/';

    public function __construct()
    {
        parent::__construct('Code Quality Tool', '1.0.0');
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        $this->writeln('<fg=white;options=bold;bg=red>###Prosodie Code Quality Tool: START###</fg=white;options=bold;bg=red>');

        $files = $this->extractCommitedFiles();

        if (empty($files)) {
            $this->writeInfo('No files to check');
            $output->writeln('<fg=white;options=bold;bg=red>###Prosodie Code Quality Tool: END###</fg=white;options=bold;bg=red>');

            return;
        }

        $this->checkComposer($files);
        $this->phpLint($files);
        $this->ymlLint($files);
        $this->codeStyle($files);
        $this->codeStylePsr($files);
        $this->phPmd($files);

        $output->writeln('<info>Good job!</info>');
        $output->writeln('<fg=white;options=bold;bg=red>###Prosodie Code Quality Tool: END###</fg=white;options=bold;bg=red>');
    }

    protected function extractCommitedFiles()
    {
        $this->writeln('________________');
        $this->writeInfo('Fetching files');

        $output = array();
        exec("git diff-index --name-status --diff-filter=ACM HEAD", $output);

        $output = array_map(function ($file) {
            return preg_replace('/^[A|C|M]\s*(.+)/', '$1', $file);
        }, $output);

        return $output;
    }

    protected function checkComposer($files)
    {
        $this->writeln('________________');
        $this->writeInfo('Check composer');

        $composerJsonDetected = false;
        $composerLockDetected = false;

        foreach ($files as $file) {
            if ($file === 'composer.json') {
                $composerJsonDetected = true;
            }

            if ($file === 'composer.lock') {
                $composerLockDetected = true;
            }
        }

        if ($composerJsonDetected && !$composerLockDetected) {
            throw new Exception('composer.lock must be commited if composer.json is modified!');
        }
    }

    protected function phpLint($files)
    {
        $this->writeln('_________________');
        $this->writeInfo('Running PHPLint');

        $needle = '/(\.php)|(\.inc)$/';

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $processBuilder = new ProcessBuilder(array('php', '-l', $file));
            $process        = $processBuilder->getProcess();
            $process->setTimeout(null);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf("ERROR IN FILE (%s)", $file));
                $this->writeError($process->getErrorOutput());
                $this->writeInfo($process->getOutput());

                throw new Exception('There are some PHP syntax errors!');
            }
        }
    }

    protected function ymlLint($files)
    {
        $this->writeln('__________________');
        $this->writeInfo('Running YML Lint');

        $needle = '/(\.yml)$/';

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $parser = new Parser();
            try {
                $parser->parse(file_get_contents($file));
            } catch (ParseException $e) {
                $this->output->writeln(sprintf("ERROR IN FILE (%s)", $file));
                $this->writeError($e->getMessage());

                throw new Exception('There are some Yml syntax errors!');
            }
        }
    }

    protected function phPmd($files)
    {
        $this->writeInfo('_____________________________');
        $this->writeInfo('Checking code mess with PHPMD');

        $rootPath = realpath(__DIR__ . '/../../');

        $files = $this->filterFiles($files, array(self::PHP_FILES_IN_SRC));

        foreach ($files as $file) {
            $processBuilder = new ProcessBuilder(['php', 'bin/phpmd', $file, 'text', 'controversial']);
            $processBuilder->setWorkingDirectory($rootPath);
            $process = $processBuilder->getProcess();
            $process->setTimeout(null);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf("ERROR IN FILE (%s)", $file));
                $this->writeError($process->getErrorOutput());
                $this->writeInfo($process->getOutput());

                throw new Exception(sprintf('There are PHPMD violations!'));
            }
        }
    }

    protected function codeStyle(array $files)
    {
        $this->writeInfo('___________________');
        $this->writeInfo('Checking Symfony code style');

        $files = $this->filterFiles($files, array(self::PHP_FILES_IN_SRC, self::PHP_FILES_IN_APP));

        foreach ($files as $file) {
            $commandLineOptions = array('bin/php-cs-fixer', '--verbose', 'fix', $file, '--level=symfony', '--fixers=-unused_use', '--config=sf23', '--dry-run');

            $processBuilder = new ProcessBuilder($commandLineOptions);

            $processBuilder->setWorkingDirectory(__DIR__ . '/../../');
            $process = $processBuilder->getProcess();
            $process->setTimeout(null);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf("ERROR IN FILE (%s)", $file));
                $this->writeError($process->getErrorOutput());
                $this->writeInfo($process->getOutput());

                //we remove --dry-run
                array_pop($commandLineOptions);

                throw new Exception(sprintf(
                    'There are php-cs-fixer coding standards violations!%srun the following commands to check them, remove (--dry-run) to fix them!! %s%s%s',
                    PHP_EOL,
                    PHP_EOL,
                    PHP_EOL,
                    join(PHP_EOL, array_map(function($file) {
                        return sprintf('./bin/fixPHPCsFile.ssh %s', $file);
                    }, $files))
                ));
            }
        }
    }

    protected function codeStylePsr(array $files)
    {
        $this->writeln('________________________________');
        $this->writeInfo('Checking code style with PHPCS');

        $files = $this->filterFiles($files, array(self::PHP_FILES_IN_SRC));

        foreach ($files as $file) {
            $commandLineOptions = array('bin/phpcs', '--standard=PSR2', '-n', $file);

            $processBuilder = new ProcessBuilder($commandLineOptions);
            $processBuilder->setWorkingDirectory(__DIR__ . '/../../');
            $process = $processBuilder->getProcess();
            $process->setTimeout(null);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf("ERROR IN FILE (%s)", $file));
                $this->writeError($process->getErrorOutput());
                $this->writeInfo($process->getOutput());

                throw new Exception(sprintf(
                    'There are PHPCS coding standards violations!%srun (%s) to check it!',
                    PHP_EOL,
                    join(' ', $commandLineOptions)
                ));
            }
        }
    }

    /**
     * @param  string $msg
     *
     * @return $this
     */
    protected function writeInfo($msg)
    {
        $this->output->writeln(sprintf('<info>%s</info>', trim($msg)));

        return $this;
    }

    /**
     * @param  string $msg
     *
     * @return $this
     */
    protected function writeln($msg)
    {
        $this->output->writeln($msg);

        return $this;
    }

    /**
     * @param  string $msg
     *
     * @return $this
     */
    protected function writeError($msg)
    {
        $this->output->writeln(sprintf('<error>%s</error>', trim($msg)));

        return $this;
    }

    /**
     * Filter files depending of filters
     *
     * @param array $files        The array of files to filter
     * @param array $filters      The pattern of files to conserve
     *
     * @return array
     */
    protected function filterFiles($files, $filters)
    {
        $files = array_filter($files, function($file) use ($filters) {
            foreach ($filters as $filter) {
                if (preg_match($filter, $file)) {
                    return true;
                }
            }

            return false;
        });

        return $files;
    }
}

$console = new CodeQualityTool();
$console->run();