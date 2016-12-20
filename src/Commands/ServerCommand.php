<?php

namespace Sunfox\PhpServer\Commands;

use Symfony\Component\Console\Command\Command;


/**
 * Base methods for commands related to PHP's built-in web server.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 * @author Tomas Jacik <tomas.jacik@sunfox.cz>
 */
abstract class ServerCommand extends Command
{
    /**
     * @var string
     */
    private $documentRoot;

    /**
     * @return string
     */
    public function getDocumentRoot()
    {
        return $this->documentRoot;
    }

    /**
     * @param string $documentRoot
     */
    public function setDocumentRoot($documentRoot)
    {
        $this->documentRoot = $documentRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        if (defined('HHVM_VERSION')) {
            return FALSE;
        }

        if (!class_exists('Symfony\Component\Process\Process')) {
            return FALSE;
        }

        return parent::isEnabled();
    }

    /**
     * Determines the name of the lock file for a particular PHP web server process.
     *
     * @param string $address An address/port tuple
     *
     * @return string The filename
     */
    protected function getLockFile($address)
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . strtr($address, '.:', '--') . '.pid';
    }

    protected function isOtherServerProcessRunning($address)
    {
        $lockFile = $this->getLockFile($address);

        if (file_exists($lockFile)) {
            return TRUE;
        }

        list($hostname, $port) = explode(':', $address);

        $fp = @fsockopen($hostname, $port, $errno, $errstr, 5);

        if ($fp !== FALSE) {
            fclose($fp);

            return TRUE;
        }

        return FALSE;
    }
}
