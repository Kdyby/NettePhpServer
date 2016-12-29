<?php

namespace Kdyby\NettePhpServer\DI;

use Kdyby\Console\DI\ConsoleExtension;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\Utils\AssertionException;


/**
 * Nette extension for the registration of console commands.
 *
 * @author Tomas Jacik <tomas@jacik.cz>
 */
class PhpServerExtension extends CompilerExtension
{
    const CONSOLE_EXT = 'Kdyby\Console\DI\ConsoleExtension';

    private static $commands = [
        'cli.serverRun' => 'Kdyby\NettePhpServer\Commands\ServerRunCommand',
        'cli.serverStart' => 'Kdyby\NettePhpServer\Commands\ServerStartCommand',
        'cli.serverStop' => 'Kdyby\NettePhpServer\Commands\ServerStopCommand',
    ];

    /**
     * @var array
     */
    private $defaults = [
        'documentRoot' => '%appDir%/../www',
    ];


    public function loadConfiguration()
    {
        if (!$this->compiler->getExtensions(self::CONSOLE_EXT)) {
            throw new AssertionException(
                sprintf("You should register '%s' before '%s'.", self::CONSOLE_EXT, get_class($this)),
                E_USER_NOTICE
            );
        }

        $config = $this->getConfig($this->defaults);
        $builder = $this->getContainerBuilder();

        foreach (self::$commands as $alias => $class) {
            $builder->addDefinition($this->prefix($alias))
                ->setClass($class)
                ->addSetup('setDocumentRoot', [$config['documentRoot']])
                ->addTag(ConsoleExtension::TAG_COMMAND)
                ->setInject(FALSE); // lazy injects
        }
    }

    /**
     * @param Configurator $configurator
     */
    public static function register(Configurator $configurator)
    {
        $configurator->onCompile[] = function ($config, Compiler $compiler) {
            $compiler->addExtension('server', new self);
        };
    }
}
