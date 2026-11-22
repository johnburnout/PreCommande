<?php

declare(strict_types=1);

namespace PreCommande;

use PreCommande\Admin\AdminMenu;

final class Assets implements HookableInterface
{
    public const FRONT = 'precommande-front';
    public const ADMIN = 'precommande-admin';

    public function __construct(private string $pluginFile)
    {
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', function (): void {
            // Enregistré seulement : le shortcode l'ajoute quand il est utilisé.
            wp_register_style(self::FRONT, plugins_url('assets/front.css', $this->pluginFile), [], Plugin::VERSION);
            wp_register_script(self::FRONT, plugins_url('assets/front.js', $this->pluginFile), [], Plugin::VERSION, ['strategy' => 'defer', 'in_footer' => true]);
            wp_localize_script(self::FRONT, 'PreCommande', ['currency' => Money::CURRENCY]);
        });

        add_action('admin_enqueue_scripts', function (string $hook): void {
            if (str_contains($hook, AdminMenu::SLUG)) {
                wp_enqueue_style(self::ADMIN, plugins_url('assets/admin.css', $this->pluginFile), [], Plugin::VERSION);
            }
        });
    }
}
