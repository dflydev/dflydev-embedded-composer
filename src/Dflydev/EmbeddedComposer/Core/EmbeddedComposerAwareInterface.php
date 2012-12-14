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
 * Embedded Composer Aware Interface.
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
interface EmbeddedComposerAwareInterface
{
    /**
     * Embedded Composer.
     *
     * @return EmbeddedComposer
     */
    public function getEmbeddedComposer();
}
