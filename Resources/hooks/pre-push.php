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
		$this->unitTests();
        $output->writeln('<info>Good job!</info>');
        $output->writeln('<fg=white;options=bold;bg=red>###Prosodie Code Quality Tool: END###</fg=white;options=bold;bg=red>');
    }
   
	
	protected function unitTests()
    {
		$this->writeln('________________________________');
        $this->writeInfo('Execution of unit tests');
        $processBuilder = new ProcessBuilder(array('php', self::BIN_DIR.'/phpunit', '--testsuite', 'unitaire', '--stderr'));
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
}
$console = new CodeQualityTool();
$console->run();