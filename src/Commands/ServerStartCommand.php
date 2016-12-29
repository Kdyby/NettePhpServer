<?php

namespace Kdyby\NettePhpServer\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;


/**
 * Runs PHP's built-in web server in a background process.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 * @author Tomas Jacik <tomas@jacik.cz>
 */
class ServerStartCommand extends ServerCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('address', InputArgument::OPTIONAL, 'Address:port', '127.0.0.1'),
                new InputOption('port', 'p', InputOption::VALUE_REQUIRED, 'Address port number', '8000'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force web server startup'),
            ])
            ->setName('server:start')
            ->setDescription('Starts PHP built-in web server in the background')
            ->setHelp(<<<EOF
The <info>%command.name%</info> runs PHP's built-in web server:

  <info>php %command.full_name%</info>

To change the default bind address and the default port use the <info>address</info> argument:

  <info>php %command.full_name% 127.0.0.1:8080</info>

See also: http://www.php.net/manual/en/features.commandline.webserver.php

EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $cliOutput = $output);

        if (!extension_loaded('pcntl')) {
            $io->error([
                'This command needs the pcntl extension to run.',
                'You can either install it or use the "server:run" command instead to run the built-in web server.',
            ]);

            if (strtolower($io->ask('Do you want to execute server:run immediately? ', 'y')) === 'y') {
                $command = $this->getApplication()->find('server:run');

                return $command->run($input, $cliOutput);
            }

            return 1;
        }

        $documentRoot = $this->getDocumentRoot();

        if (!is_dir($documentRoot)) {
            $io->error(sprintf("Document root directory '%s' does not exist", $documentRoot));

            return 1;
        }

        $address = $input->getArgument('address');

        if (FALSE === strpos($address, ':')) {
            $address = $address . ':' . $input->getOption('port');
        }

        if (!$input->getOption('force') && $this->isOtherServerProcessRunning($address)) {
            $io->error([
                sprintf('A process is already listening on http://%s.', $address),
                'Use the --force option if the server process terminated unexpectedly to start a new web server process.',
            ]);

            return 1;
        }

        if ($this->getHelper('container')->getParameter('productionMode')) {
            $io->error('Running PHP built-in server in production environment is NOT recommended!');
        }

        $pid = pcntl_fork();

        if ($pid < 0) {
            $io->error('Unable to start the server process.');

            return 1;
        }

        if ($pid > 0) {
            $io->success(sprintf('Web server listening on http://%s', $address));

            return;
        }

        if (posix_setsid() < 0) {
            $io->error('Unable to set the child process as session leader');

            return 1;
        }

        if (NULL === $process = $this->createServerProcess($io, $address, $documentRoot)) {
            return 1;
        }

        $process->disableOutput();
        $process->start();
        $lockFile = $this->getLockFile($address);
        touch($lockFile);

        if (!$process->isRunning()) {
            $io->error('Unable to start the server process');
            unlink($lockFile);

            return 1;
        }

        // stop the web server when the lock file is removed
        while ($process->isRunning()) {
            if (!file_exists($lockFile)) {
                $process->stop();
            }

            sleep(1);
        }
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

        $script = implode(' ', array_map(['Symfony\Component\Process\ProcessUtils', 'escapeArgument'], [
            $binary,
            '-S',
            $address,
            '-t',
            $documentRoot,
        ]));

        return new Process('exec '.$script, $documentRoot, NULL, NULL, NULL);
    }
}
