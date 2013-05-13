<?php

/*
 * This file is a part of dflydev/embedded-composer.
 *
 * (c) Dragonfly Development Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dflydev\EmbeddedComposer\Core;

use Composer\Json\JsonFile;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Script\Event;

/**
 * Composer Script
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class Script
{
    public static function postAutoloadDump(Event $event)
    {
        $io = $event->getIO();
        $composer = $event->getComposer();
        $package = $composer->getPackage();
        $config = $composer->getConfig();
        $filename = $config->get('vendor-dir').'/dflydev/embedded-composer/.root_package.json';
        $io->write('Adding '.$package->getName().' ('.$package->getPrettyVersion().') to '.$filename);
        $jsonFile = new JsonFile($filename);
        $repository = new InstalledFilesystemRepository($jsonFile);
        $repository->addPackage(clone $package);
        $repository->write();
    }
}
