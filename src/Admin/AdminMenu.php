<?php

declare(strict_types=1);

namespace PreCommande\Admin;

use PreCommande\HookableInterface;

final class AdminMenu implements HookableInterface
{
    public const SLUG = 'precommande';
    public const CAPABILITY = 'manage_options';

    public function __construct(
        private ResultsPage $results,
        private ContactPage $contact,
    ) {
    }

    public function register(): void
    {
        // Priorité 9 : le menu parent doit exister avant le sous-menu « Modèles » du type de contenu.
        add_action('admin_menu', [$this, 'addMenus'], 9);
    }

    public function addMenus(): void
    {
        add_menu_page('PréCommande', 'PréCommande', self::CAPABILITY, self::SLUG, [$this->results, 'render'], 'dashicons-cart', 26);
        add_submenu_page(self::SLUG, 'Résultats', 'Résultats', self::CAPABILITY, self::SLUG, [$this->results, 'render']);
        add_submenu_page(self::SLUG, 'Contacter les participants', 'Contacter', self::CAPABILITY, self::SLUG . '-contact', [$this->contact, 'render']);
    }
}
