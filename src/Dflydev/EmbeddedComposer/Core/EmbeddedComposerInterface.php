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
     * Get the active class loader (may be internal or external)
     *
     * @return mixed
     */
    public function getClassLoader();

    /**
     * Find a package by name
     *
     * @param string $name Package name
     *
     * @return \Composer\Package\PackageInterface
     */
    public function findPackage($name);

    /**
     * Process any additional external Composer autoloader definitions
     */
    public function processAdditionalAutoloads();

    /**
     * Get a repository repository representing the external repository and
     * the internal repository if it exists.
     *
     * @return \Composer\Repository\RepositoryInterface;
     */
    public function getRepository();

    /**
     * Get the external repository
     *
     * @return \Composer\Repository\RepositoryInterface;
     */
    public function getExternalRepository();

    /**
     * Get external root directory
     *
     * @return string
     */
    public function getExternalRootDirectory();

    /**
     * Composer configuration
     *
     * @return \Composer\Config
     */
    public function getExternalComposerConfig();

    /**
     * Get the full path to the Composer file to process
     *
     * @return \Composer\Config
     */
    public function getExternalComposerFilename();

    /**
     * Get the internal repository
     *
     * @return \Composer\Repository\RepositoryInterface;
     */
    public function getInternalRepository();

    /**
     * Has an internal repository?
     *
     * @return bool
     */
    public function hasInternalRepository();
}
