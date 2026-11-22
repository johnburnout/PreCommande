<?php

declare(strict_types=1);

namespace PreCommande;

use PreCommande\Repository\ParticipationRepository;

/**
 * Création / mise à jour des tables à l'activation et lors des montées de version.
 */
final class Installer implements HookableInterface
{
    private const DB_VERSION = '2';
    private const OPTION = 'precommande_db_version';

    public function __construct(private \wpdb $db)
    {
    }

    public static function activate(): void
    {
        (new self($GLOBALS['wpdb']))->install();
    }

    public function register(): void
    {
        add_action('admin_init', function (): void {
            if (get_option(self::OPTION) !== self::DB_VERSION) {
                $this->install();
            }
        });
    }

    public function install(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->db->get_charset_collate();
        $p = $this->db->prefix . ParticipationRepository::TABLE_PARTICIPATIONS;
        $l = $this->db->prefix . ParticipationRepository::TABLE_LIGNES;

        dbDelta("CREATE TABLE {$p} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            nom varchar(100) NOT NULL,
            prenom varchar(100) NOT NULL,
            email varchar(190) NOT NULL,
            telephone varchar(30) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) {$charset};");

        dbDelta("CREATE TABLE {$l} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            participation_id bigint(20) unsigned NOT NULL,
            modele_id bigint(20) unsigned NOT NULL,
            taille varchar(30) NOT NULL DEFAULT '',
            quantite smallint(5) unsigned NOT NULL,
            prix_unitaire decimal(10,2) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY participation_id (participation_id),
            KEY modele_id (modele_id)
        ) {$charset};");

        update_option(self::OPTION, self::DB_VERSION);
    }
}
