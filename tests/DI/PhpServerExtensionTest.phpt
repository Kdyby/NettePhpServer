<?php

namespace Tests;

use Nette;
use Kdyby\NettePhpServer\DI\PhpServerExtension;
use Tester;
use Tester\Assert;


require_once __DIR__ . '/../bootstrap.php';


/**
 * @author Tomas Jacik <tomas@jacik.cz>
 */
class PhpServerExtensionTest extends Tester\TestCase
{
    /**
     * @return Nette\DI\Container
     */
    protected function createContainer()
    {
        $config = new Nette\Configurator();
        $config->setTempDirectory(TEMP_DIR);
        PhpServerExtension::register($config);
        $config->addConfig(__DIR__ . '/../config.neon');

        return $config->createContainer();
    }

    public function testFunctionality()
    {
        $dic = $this->createContainer();

        Assert::type('Kdyby\NettePhpServer\Commands\ServerRunCommand', $dic->getService('server.cli.serverRun'));
        Assert::type('Kdyby\NettePhpServer\Commands\ServerStartCommand', $dic->getService('server.cli.serverStart'));
        Assert::type('Kdyby\NettePhpServer\Commands\ServerStopCommand', $dic->getService('server.cli.serverStop'));
    }
}

(new PhpServerExtensionTest)->run();
