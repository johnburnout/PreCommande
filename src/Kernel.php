<?php

declare(strict_types=1);

namespace PreCommande;

use PreCommande\Vendor\Symfony\Component\Config\FileLocator;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\ContainerInterface;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Construit le conteneur Symfony. En production (WP_DEBUG désactivé),
 * le conteneur compilé est mis en cache dans var/cache/.
 */
final class Kernel
{
    private const CACHE_CLASS = 'PreCommandeCachedContainer';

    private static ?ContainerInterface $container = null;

    public static function boot(string $pluginFile): ContainerInterface
    {
        if (self::$container !== null) {
            return self::$container;
        }

        $dir = \dirname($pluginFile);
        $cacheFile = $dir . '/var/cache/container.php';
        $debug = \defined('WP_DEBUG') && WP_DEBUG;

        if (!$debug && is_file($cacheFile)) {
            require_once $cacheFile;
            $class = self::CACHE_CLASS;
            $container = new $class();
        } else {
            $container = self::build($pluginFile, $dir);
            if (!$debug) {
                self::dump($container, $cacheFile);
            }
        }

        $container->set(\wpdb::class, $GLOBALS['wpdb']);

        return self::$container = $container;
    }

    private static function build(string $pluginFile, string $dir): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('plugin.file', $pluginFile);
        $builder->setParameter('plugin.dir', $dir);

        (new PhpFileLoader($builder, new FileLocator($dir . '/config')))->load('services.php');
        $builder->compile();

        return $builder;
    }

    private static function dump(ContainerBuilder $builder, string $cacheFile): void
    {
        $cacheDir = \dirname($cacheFile);
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0775, true)) {
            return; // Dossier non inscriptible : on se passe du cache.
        }
        $code = (new PhpDumper($builder))->dump(['class' => self::CACHE_CLASS]);
        @file_put_contents($cacheFile, $code);
    }
}
