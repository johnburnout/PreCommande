<?php

declare(strict_types=1);

namespace PreCommande;

use PreCommande\DependencyInjection\HookablePass;
use PreCommande\Vendor\Symfony\Component\Config\FileLocator;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\Container;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\ContainerInterface;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Construit le conteneur Symfony. En production (WP_DEBUG désactivé),
 * le conteneur compilé est mis en cache dans var/cache/.
 *
 * Le nom du fichier de cache dépend de la version du plugin et des fichiers
 * qui servent à le construire : après une mise à jour, l'ancien cache est
 * ignoré puis supprimé. Un cache illisible (ex. compilé par une version
 * antérieure au préfixage Strauss) est reconstruit au lieu de provoquer une
 * erreur fatale.
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
        $debug = \defined('WP_DEBUG') && WP_DEBUG;

        $container = $debug ? self::build($pluginFile, $dir) : self::cached($pluginFile, $dir);
        $container->set(\wpdb::class, $GLOBALS['wpdb']);

        return self::$container = $container;
    }

    private static function cached(string $pluginFile, string $dir): ContainerInterface
    {
        $key = self::cacheKey($dir);
        $class = self::CACHE_CLASS . '_' . $key;
        $cacheFile = $dir . '/var/cache/container-' . $key . '.php';

        if (is_file($cacheFile)) {
            try {
                require_once $cacheFile;
                if (class_exists($class, false)) {
                    return new $class();
                }
            } catch (\Throwable) {
                // Cache corrompu ou incompatible : on le reconstruit.
            }
            @unlink($cacheFile);
        }

        $builder = self::build($pluginFile, $dir);
        self::dump($builder, $cacheFile, $class);

        return $builder;
    }

    private static function cacheKey(string $dir): string
    {
        $parts = [Plugin::VERSION];
        foreach (['/config/services.php', '/vendor-prefixed/autoload.php'] as $file) {
            $parts[] = (string) @filemtime($dir . $file);
        }

        return substr(md5(implode('|', $parts)), 0, 12);
    }

    private static function build(string $pluginFile, string $dir): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('plugin.file', $pluginFile);
        $builder->setParameter('plugin.dir', $dir);

        (new PhpFileLoader($builder, new FileLocator($dir . '/config')))->load('services.php');
        $builder->addCompilerPass(new HookablePass());
        $builder->compile();

        return $builder;
    }

    private static function dump(ContainerBuilder $builder, string $cacheFile, string $class): void
    {
        $cacheDir = \dirname($cacheFile);
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0775, true)) {
            return; // Dossier non inscriptible : on se passe du cache.
        }

        // Supprime les caches des versions précédentes (dont l'ancien container.php).
        foreach (glob($cacheDir . '/container*.php') ?: [] as $old) {
            if ($old !== $cacheFile) {
                @unlink($old);
            }
        }

        $code = self::prefixImports((new PhpDumper($builder))->dump(['class' => $class]));
        $tmp = $cacheFile . '.' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, $code) !== false) {
            @rename($tmp, $cacheFile); // Écriture atomique : pas de fichier à moitié écrit.
        }
    }

    /**
     * PhpDumper écrit ses « use Symfony\\… ; » sous forme de texte brut, que Strauss
     * ne préfixe pas : le conteneur en cache chercherait des classes qui n'existent
     * pas (Symfony\\Component\\DependencyInjection\\Container). On les recale sur
     * l'espace de noms réellement chargé.
     */
    private static function prefixImports(string $code): string
    {
        $prefix = substr(Container::class, 0, (int) strpos(Container::class, 'Symfony\\'));
        if ($prefix === '') {
            return $code; // Symfony non préfixé : rien à faire.
        }

        return (string) preg_replace('/^use (Symfony|Psr)\\\\/m', 'use ' . addcslashes($prefix, '\\') . '$1\\\\', $code);
    }
}
