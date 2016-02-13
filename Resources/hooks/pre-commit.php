#!/usr/bin/php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Application;

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

    const PHP_FILES_IN_SRC = '/^.*(\.php)$/';
	const BIN_DIR = "vendor/bin";
	
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

        //$this->checkComposer($files);
        $this->phpLint($files);
        $this->jsonLint($files);
        $this->codeStyleFixer($files);
		$this->codeSnifferFixer($files);
        $this->codeStylePsr($files);
        $this->phPmd($files);
		$this->unitTests();

        $output->writeln('<info>Good job!</info>');
        $output->writeln('<fg=white;options=bold;bg=red>###Prosodie Code Quality Tool: END###</fg=white;options=bold;bg=red>');
    }

    protected function extractCommitedFiles()
    {
        $this->writeln('________________');
        $this->writeInfo('Fetching files');

        $output = array();
        exec("git diff --staged --name-status --diff-filter=ACM HEAD", $output);

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

    protected function jsonLint($files)
    {
        $this->writeln('__________________');
        $this->writeInfo('Running JSON Lint');

        $needle = '/(\.json)$/';

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }
			$processBuilder = new ProcessBuilder(array('php', self::BIN_DIR.'/jsonlint', $file));
			$process = $processBuilder->getProcess();
			$process->run();
			
			if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf("ERROR IN FILE (%s)", $file));
                $this->writeError($process->getErrorOutput());
                $this->writeInfo($process->getOutput());
                throw new Exception('There are some JSON syntax errors!');
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
            $processBuilder = new ProcessBuilder(['php', self::BIN_DIR.'/phpmd', $file, 'text', 'controversial', 'unusedcode']);
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

    protected function codeStyleFixer(array $files)
    {
        $this->writeInfo('___________________');
        $this->writeInfo('Checking with PHP-CS-FIXER');

        $files = $this->filterFiles($files, array(self::PHP_FILES_IN_SRC));
		
		$fixers = 'eof_ending,indentation,linefeed,lowercase_keywords,trailing_spaces,short_tag,php_closing_tag,extra_empty_lines,elseif,function_declaration';
		
        foreach ($files as $file) {
            $commandLineOptions = array('php', self::BIN_DIR.'/php-cs-fixer', '--verbose', 'fix', $file, '--level=psr2', '--fixers='.$fixers);
            $processBuilder = new ProcessBuilder($commandLineOptions);

            $processBuilder->setWorkingDirectory(__DIR__ . '/../../');
            $process = $processBuilder->getProcess();
            $process->setTimeout(null);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf("ERROR IN FILE (%s)", $file));
                $this->writeError($process->getErrorOutput());
                $this->writeInfo($process->getOutput());
            }
        }
		
		$this->stageFiles($files);
		
    }

	protected function codeSnifferFixer(array $files)
    {
        $this->writeln('________________________________');
        $this->writeInfo('Fixing files with PHPCBF');

        $files = $this->filterFiles($files, array(self::PHP_FILES_IN_SRC));

        foreach ($files as $file) {
            $commandLineOptions = array('php', self::BIN_DIR.'/phpcbf', '--standard=PSR2', '-n', $file);

            $processBuilder = new ProcessBuilder($commandLineOptions);
            $processBuilder->setWorkingDirectory(__DIR__ . '/../../');
            $process = $processBuilder->getProcess();
            $process->setTimeout(null);
            $process->run();
        
			$this->writeInfo($process->getOutput());
        }
		
		$this->stageFiles($files);
    }
	
    protected function codeStylePsr(array $files)
    {
        $this->writeln('________________________________');
        $this->writeInfo('Checking code style with PHPCS');

        $files = $this->filterFiles($files, array(self::PHP_FILES_IN_SRC));

        foreach ($files as $file) {
            $commandLineOptions = array('php', self::BIN_DIR.'/phpcs', '--standard=PSR2', '-n', $file);

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
	
	protected function unitTests()
    {
		$this->writeln('________________________________');
        $this->writeInfo('Execution of unit tests');
        $processBuilder = new ProcessBuilder(array('phpunit', '--testsuite', 'unitaire', '--stderr'));
        $processBuilder->setWorkingDirectory(__DIR__ . '/../..');
        $processBuilder->setTimeout(3600);
        $phpunit = $processBuilder->getProcess();
 
        $phpunit->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
 
        if(!$phpunit->isSuccessful()) {
			throw new Exception(sprintf('Failed to run unit tests!'));
		}
    }

	/**
	* stage files for commit after changes
	*/
	protected function stageFiles($files)
	{
		foreach ($files as $file) {
            $commandLineOptions = array('git', 'add', $file);

            $processBuilder = new ProcessBuilder($commandLineOptions);
            $processBuilder->setWorkingDirectory(__DIR__ . '/../../');
            $process = $processBuilder->getProcess();
            $process->setTimeout(null);
            $process->run();
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