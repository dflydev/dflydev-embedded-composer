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

/**
 * Embedded Composer Interface.
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
interface EmbeddedComposerInterface
{
    /**
     * Class Loader
     *
     * @return ClassLoader
     */
    public function getClassLoader();

    /**
     * External Root Directory
     *
     * @return string
     */
    public function getExternalRootDir();

    /**
     * Package
     *
     * @return PackageInterface
     */
    public function getPackage();

    /**
     * Has an internal repository?
     *
     * @return bool
     */
    public function hasInternalRepository();

    /**
     * Composer file
     *
     * @return string
     */
    public function getComposerFile();

    /**
     * Get internal repository
     *
     * @return \Composer\Repository\RepositoryInterface;
     */
    public function getInternalRepository();

    /**
     * Error
     *
     * @return \Exception
     */
    public function getError();

    /**
     * Process external autoloads.
     */
    public function processExternalAutoloads();
}
