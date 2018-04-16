<?php

namespace Andreybolonin\RatchetBundle;

use Andreybolonin\RatchetBundle\DependencyInjection\RatchetBundleExtension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

class AndreyboloninRatchetBundle extends Bundle
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('ratchet_bundle.yaml');
    }

    public function getContainerExtension()
    {
        return new RatchetBundleExtension();
    }
}
