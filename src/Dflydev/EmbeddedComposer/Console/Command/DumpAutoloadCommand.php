<?php

/*
 * This file is a part of dflydev/embedded-composer.
 *
 * (c) Dragonfly Development Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dflydev\EmbeddedComposer\Console\Command;

use Composer\IO\ConsoleIO;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Dflydev\EmbeddedComposer\Core\EmbeddedComposerAwareInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dump Autoload Command.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Beau Simensen <beau@dflydev.com>
 */
class DumpAutoloadCommand extends Command
{
    public function __construct($commandPrefix = 'composer:')
    {
        $this->commandPrefix = $commandPrefix;
        parent::__construct();
    }

    protected function configure()
    {
        $fullCommand = $this->commandPrefix.'dump-autoload';
        $this
            ->setName($fullCommand)
            ->setAliases(array($this->commandPrefix.'dumpautoload'))
            ->setDescription('Dumps the autoloader')
            ->setDefinition(array(
                new InputOption('optimize', 'o', InputOption::VALUE_NONE, 'Optimizes PSR0 packages to be loaded with classmaps too, good for production.'),
            ))
            ->setHelp(<<<EOT
The <info>${fullCommand} -o</info> command dumps an optimized autoloader.

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($this->getApplication() instanceof EmbeddedComposerAwareInterface)) {
            throw new \RuntimeException('Application must be instance of EmbeddedComposerAwareInterface');
        }

        $embeddedComposer = $this->getApplication()->getEmbeddedComposer();

        $io = new ConsoleIO($input, $output, $this->getApplication()->getHelperSet());
        $composer = $embeddedComposer->createComposer($io);

        $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'dump-autoload', $input, $output);
        $composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);

        $installationManager = $composer->getInstallationManager();
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $package = $composer->getPackage();
        $config = $composer->getConfig();

        $composer->getAutoloadGenerator()->dump($config, $localRepo, $package, $installationManager, 'composer', $input->getOption('optimize'));
    }
}
