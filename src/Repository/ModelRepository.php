<?php

declare(strict_types=1);

namespace PreCommande\Repository;

use PreCommande\Money;

/**
 * Accès aux modèles (vêtements / objets), stockés en type de contenu WordPress.
 *
 * Tailles saisies une par ligne ; un prix spécifique peut être indiqué :
 *   S
 *   M
 *   XXL = 27,50
 *
 * @phpstan-type Size array{label:string, prix:?float}
 * @phpstan-type Model array{id:int, titre:string, description:string, image:?string, prix:?float, prix_min:?float, prix_max:?float, tailles:list<Size>, actif:bool}
 */
final class ModelRepository
{
    public const POST_TYPE = 'pc_modele';
    public const META_TAILLES = '_pc_tailles';
    public const META_PRIX = '_pc_prix';
    public const META_ACTIF = '_pc_actif';
    public const TAILLE_UNIQUE = 'Taille unique';

    /** @return list<Model> */
    public function findActive(): array
    {
        return $this->query([
            'post_status' => 'publish',
            'meta_query' => [['key' => self::META_ACTIF, 'value' => '1']],
        ]);
    }

    /** @return array<int, Model> indexé par id */
    public function findAllIndexed(): array
    {
        $models = [];
        foreach ($this->query(['post_status' => ['publish', 'draft', 'private']]) as $model) {
            $models[$model['id']] = $model;
        }
        return $models;
    }

    /**
     * Une taille par ligne (« label » ou « label = prix »).
     * Compatibilité v0.1 : liste séparée par des virgules, sans prix.
     *
     * @return array<string, ?float> label => prix spécifique éventuel
     */
    public static function parseSizes(string $raw): array
    {
        $raw = str_replace("\r", '', $raw);
        $parts = (str_contains($raw, "\n") || str_contains($raw, '=')) ? explode("\n", $raw) : explode(',', $raw);

        $sizes = [];
        foreach ($parts as $part) {
            [$label, $price] = array_pad(explode('=', $part, 2), 2, null);
            $label = trim((string) $label);
            if ($label === '' || \array_key_exists($label, $sizes)) {
                continue;
            }
            $sizes[$label] = $price === null ? null : Money::parse($price);
        }
        return $sizes;
    }

    /** @param array<string, ?float> $sizes */
    public static function formatSizes(array $sizes): string
    {
        $lines = [];
        foreach ($sizes as $label => $price) {
            $lines[] = $price === null ? $label : $label . ' = ' . Money::toInput($price);
        }
        return implode("\n", $lines);
    }

    /** @return list<Model> */
    private function query(array $args): array
    {
        $posts = get_posts($args + [
            'post_type' => self::POST_TYPE,
            'numberposts' => -1,
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
        ]);

        return array_map(fn (\WP_Post $post): array => $this->toArray($post), $posts);
    }

    /** @return Model */
    private function toArray(\WP_Post $post): array
    {
        $base = Money::parse((string) get_post_meta($post->ID, self::META_PRIX, true));
        $parsed = self::parseSizes((string) get_post_meta($post->ID, self::META_TAILLES, true)) ?: [self::TAILLE_UNIQUE => null];

        $tailles = [];
        foreach ($parsed as $label => $price) {
            $tailles[] = ['label' => (string) $label, 'prix' => $price ?? $base];
        }

        $prices = array_values(array_filter(array_column($tailles, 'prix'), static fn ($p): bool => $p !== null));

        return [
            'id' => $post->ID,
            'titre' => get_the_title($post),
            'description' => wpautop(wp_kses_post($post->post_content)),
            'image' => get_the_post_thumbnail_url($post, 'medium_large') ?: null,
            'prix' => $base,
            'prix_min' => $prices ? min($prices) : null,
            'prix_max' => $prices ? max($prices) : null,
            'tailles' => $tailles,
            'actif' => get_post_meta($post->ID, self::META_ACTIF, true) === '1',
        ];
    }
}
