<?php

namespace Kdyby\NettePhpServer\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;


/**
 * Runs Nette application using PHP built-in web server.
 *
 * @author Michał Pipa <michal.pipa.xsolve@gmail.com>
 * @author Tomas Jacik <tomas@jacik.cz>
 */
class ServerRunCommand extends ServerCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setDefinition([
                new InputArgument('address', InputArgument::OPTIONAL, 'Address:port', '127.0.0.1'),
                new InputOption('port', 'p', InputOption::VALUE_REQUIRED, 'Address port number', '8000'),
            ])
            ->setName('server:run')
            ->setDescription('Runs PHP built-in web server')
            ->setHelp(<<<EOF
The <info>%command.name%</info> runs PHP built-in web server:

  <info>%command.full_name%</info>

To change default bind address and port use the <info>address</info> argument:

  <info>%command.full_name% 127.0.0.1:8080</info>

See also: http://www.php.net/manual/en/features.commandline.webserver.php

EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $documentRoot = $this->getDocumentRoot();

        if (!is_dir($documentRoot)) {
            $io->error(sprintf("Document root directory '%s' does not exist", $documentRoot));

            return 1;
        }

        $address = $input->getArgument('address');

        if (FALSE === strpos($address, ':')) {
            $address = $address . ':' . $input->getOption('port');
        }

        if ($this->isOtherServerProcessRunning($address)) {
            $io->error(sprintf('A process is already listening on http://%s.', $address));

            return 1;
        }

        if ($this->getHelper('container')->getParameter('productionMode')) {
            $io->error('Running PHP built-in server in production environment is NOT recommended!');
        }

        $io->success(sprintf('Server running on http://%s', $address));
        $io->comment('Quit the server with CONTROL-C.');

        if (NULL === $process = $this->createServerProcess($io, $address, $documentRoot)) {
            return 1;
        }

        $callback = NULL;

        if (OutputInterface::VERBOSITY_NORMAL > $output->getVerbosity()) {
            $process->disableOutput();
        } else {
            try {
                $process->setTty(TRUE);
            } catch (RuntimeException $e) {
                $callback = function ($type, $buffer) use ($output) {
                    if (Process::ERR === $type && $output instanceof ConsoleOutputInterface) {
                        $output = $output->getErrorOutput();
                    }
                    $output->write($buffer, FALSE, OutputInterface::OUTPUT_RAW);
                };
            }
        }

        $process->run($callback);

        if (!$process->isSuccessful()) {
            $errorMessages = ['Built-in server terminated unexpectedly.'];

            if ($process->isOutputDisabled()) {
                $errorMessages[] = 'Run the command again with -v option for more details.';
            }

            $io->error($errorMessages);
        }

        return $process->getExitCode();
    }

    /**
     * Creates a process to start PHP's built-in web server.
     *
     * @param SymfonyStyle $io           A SymfonyStyle instance
     * @param string       $address      IP address and port to listen to
     * @param string       $documentRoot The application's document root
     *
     * @return Process The process
     */
    private function createServerProcess(SymfonyStyle $io, $address, $documentRoot)
    {
        $finder = new PhpExecutableFinder();

        if (FALSE === $binary = $finder->find()) {
            $io->error('Unable to find PHP binary to run server.');

            return NULL;
        }

        $builder = new ProcessBuilder([$binary, '-S', $address, '-t', $documentRoot]);
        $builder->setWorkingDirectory($documentRoot);
        $builder->setTimeout(NULL);

        return $builder->getProcess();
    }
}
