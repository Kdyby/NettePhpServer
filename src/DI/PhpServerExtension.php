<?php

namespace Sunfox\PhpServer\DI;

use Kdyby\Console\DI\ConsoleExtension;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\Utils\AssertionException;


/**
 * Nette extension for the registration of console commands.
 *
 * @author Tomas Jacik <tomas.jacik@sunfox.cz>
 */
class PhpServerExtension extends CompilerExtension
{
    const CONSOLE_EXT = 'Kdyby\Console\DI\ConsoleExtension';

    private static $commands = [
        'cli.serverRun' => 'Sunfox\PhpServer\Commands\ServerRunCommand',
        'cli.serverStart' => 'Sunfox\PhpServer\Commands\ServerStartCommand',
        'cli.serverStop' => 'Sunfox\PhpServer\Commands\ServerStopCommand',
    ];


    public function loadConfiguration()
    {
        if (!$this->compiler->getExtensions(self::CONSOLE_EXT)) {
            throw new AssertionException(
                sprintf("You should register '%s' before '%s'.", self::CONSOLE_EXT, get_class($this)),
                E_USER_NOTICE
            );
        }

        $builder = $this->getContainerBuilder();

        foreach (self::$commands as $alias => $class) {
            $builder->addDefinition($this->prefix($alias))
                ->addTag(ConsoleExtension::TAG_COMMAND)
                ->setClass($class)
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
