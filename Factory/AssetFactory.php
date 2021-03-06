<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory;

use Assetic\Factory\AssetFactory as BaseAssetFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Assetic\Asset\AssetCollection;

/**
 * Loads asset formulae from the filesystem.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AssetFactory extends BaseAssetFactory
{
    private $kernel;
    private $container;
    private $parameterBag;

    /**
     * Constructor.
     *
     * @param KernelInterface       $kernel       The kernel is used to parse bundle notation
     * @param ContainerInterface    $container    The container is used to load the managers lazily, thus avoiding a circular dependency
     * @param ParameterBagInterface $parameterBag The container parameter bag
     * @param string                $baseDir      The base directory for relative inputs
     * @param Boolean               $debug        The current debug mode
     */
    public function __construct(KernelInterface $kernel, ContainerInterface $container, ParameterBagInterface $parameterBag, $baseDir, $debug = false)
    {
        $this->kernel = $kernel;
        $this->container = $container;
        $this->parameterBag = $parameterBag;

        parent::__construct($baseDir, $debug);
    }

    /**
     * Adds support for bundle notation file and glob assets and parameter placeholders.
     *
     * FIXME: This is a naive implementation of globs in that it doesn't
     * attempt to support bundle inheritance within the glob pattern itself.
     */
    protected function parseInput($input, array $options = array())
    {
        $input = $this->parameterBag->resolveValue($input);

        // expand bundle notation
        if ('@' == $input[0] && false !== strpos($input, '/')) {
            // use the bundle path as this asset's root
            $bundle = substr($input, 1);
            if (false !== $pos = strpos($bundle, '/')) {
                $bundle = substr($bundle, 0, $pos);
            }
            $options['root'] = array($this->kernel->getBundle($bundle)->getPath());

            // canonicalize the input
            if (false !== strpos($input, '*')) {
                // locateResource() does not support globs so we provide a naive implementation here
                list($before, $after) = explode('*', $input, 2);
                // support globs like /path/to/bootstrap/js/bootstrap-*.js
                if (false !== $pos = strrpos($before, '/')) {
                    $pos++;
                    $after = substr($before, $pos).'*'.$after;
                    $before = substr($before, 0, $pos);
                } else {
                    $after = '*'.$after;
                }
                // retrieve all related folders for current bundle notation
                $globAssetFolders = $this->kernel->locateResource($before, $this->container->getParameter('kernel.root_dir').'/Resources', false);
                if (count($globAssetFolders) == 1) {
                    $input = $globAssetFolders[0].$after;
                } else {
                    $collection = new AssetCollection();
                    foreach ($globAssetFolders as $globAssetFolder) {
                        $input = $globAssetFolder.$after;
                        $collection->add(parent::parseInput($input, $options));
                    }
                    return $collection;
                }
            } else {
                $input = $this->kernel->locateResource($input, $this->container->getParameter('kernel.root_dir').'/Resources');
            }
        }

        return parent::parseInput($input, $options);
    }

    protected function createAssetReference($name)
    {
        if (!$this->getAssetManager()) {
            $this->setAssetManager($this->container->get('assetic.asset_manager'));
        }

        return parent::createAssetReference($name);
    }

    protected function getFilter($name)
    {
        if (!$this->getFilterManager()) {
            $this->setFilterManager($this->container->get('assetic.filter_manager'));
        }

        return parent::getFilter($name);
    }
}
