<?php
/**
 * Plugin Name:       PréCommande
 * Description:       Sondage / précommande de vêtements et d'objets (modèles, tailles, prix, résultats, contact des participants).
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Text Domain:       precommande
 * License:           GPL-2.0-or-later
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/*
 * Les dépendances Symfony sont recopiées et renommées dans vendor-prefixed/
 * (espace de noms PreCommande\Vendor\Symfony\...) par Strauss lors du
 * « composer install » : aucun conflit possible avec un autre plugin
 * embarquant sa propre version de Symfony.
 */
if (!is_file(__DIR__ . '/vendor-prefixed/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>PréCommande : dépendances absentes, lancez <code>composer install --no-dev</code> dans le dossier du plugin.</p></div>';
    });
    return;
}

require __DIR__ . '/vendor-prefixed/autoload.php';
require __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, [\PreCommande\Installer::class, 'activate']);

add_action('plugins_loaded', static function (): void {
    \PreCommande\Kernel::boot(__FILE__)
        ->get(\PreCommande\Plugin::class)
        ->register();
});
