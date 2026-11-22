<?php

declare(strict_types=1);

namespace PreCommande\PostType;

use PreCommande\Admin\AdminMenu;
use PreCommande\HookableInterface;
use PreCommande\Money;
use PreCommande\Repository\ModelRepository;
use PreCommande\TemplateRenderer;

/**
 * Type de contenu « Modèle » : titre, description, photo (image mise en avant),
 * liste de tailles et statut actif/inactif.
 */
final class ModelPostType implements HookableInterface
{
    private const NONCE = 'precommande_modele_meta';

    public function __construct(private TemplateRenderer $renderer)
    {
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('add_meta_boxes_' . ModelRepository::POST_TYPE, [$this, 'addMetaBox']);
        add_action('save_post_' . ModelRepository::POST_TYPE, [$this, 'saveMeta'], 10, 2);
        add_filter('manage_' . ModelRepository::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . ModelRepository::POST_TYPE . '_posts_custom_column', [$this, 'columnContent'], 10, 2);
    }

    public function registerPostType(): void
    {
        register_post_type(ModelRepository::POST_TYPE, [
            'labels' => [
                'name' => 'Modèles',
                'singular_name' => 'Modèle',
                'add_new' => 'Ajouter un modèle',
                'add_new_item' => 'Ajouter un modèle',
                'edit_item' => 'Modifier le modèle',
                'all_items' => 'Modèles',
                'featured_image' => 'Photo du modèle',
                'set_featured_image' => 'Choisir la photo',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => AdminMenu::SLUG,
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
            'capability_type' => 'post',
        ]);
    }

    public function addMetaBox(): void
    {
        add_meta_box('precommande_modele', 'Précommande', [$this, 'renderMetaBox'], null, 'side', 'high');
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $actif = get_post_meta($post->ID, ModelRepository::META_ACTIF, true);

        echo $this->renderer->render('admin/metabox', [
            'nonce' => wp_create_nonce(self::NONCE),
            'tailles' => ModelRepository::formatSizes(ModelRepository::parseSizes((string) get_post_meta($post->ID, ModelRepository::META_TAILLES, true))),
            'prix' => Money::toInput(Money::parse((string) get_post_meta($post->ID, ModelRepository::META_PRIX, true))),
            'actif' => $actif === '' || $actif === '1', // actif par défaut à la création
        ]);
    }

    public function saveMeta(int $postId, \WP_Post $post): void
    {
        if (
            !isset($_POST['_pc_meta_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_pc_meta_nonce'])), self::NONCE)
            || (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $postId)
        ) {
            return;
        }

        $raw = sanitize_textarea_field(wp_unslash($_POST['pc_tailles'] ?? ''));
        update_post_meta($postId, ModelRepository::META_TAILLES, ModelRepository::formatSizes(ModelRepository::parseSizes($raw)));

        $prix = Money::parse(sanitize_text_field(wp_unslash($_POST['pc_prix'] ?? '')));
        update_post_meta($postId, ModelRepository::META_PRIX, $prix === null ? '' : (string) $prix);
        update_post_meta($postId, ModelRepository::META_ACTIF, isset($_POST['pc_actif']) ? '1' : '0');
    }

    /** @param array<string,string> $columns */
    public function columns(array $columns): array
    {
        $date = $columns['date'] ?? null;
        unset($columns['date']);
        $columns['pc_photo'] = 'Photo';
        $columns['pc_prix'] = 'Prix';
        $columns['pc_tailles'] = 'Tailles';
        $columns['pc_actif'] = 'Proposé';
        if ($date) {
            $columns['date'] = $date;
        }
        return $columns;
    }

    public function columnContent(string $column, int $postId): void
    {
        switch ($column) {
            case 'pc_photo':
                echo get_the_post_thumbnail($postId, [48, 48]);
                break;
            case 'pc_prix':
                echo esc_html(Money::format(Money::parse((string) get_post_meta($postId, ModelRepository::META_PRIX, true))) ?: '—');
                break;
            case 'pc_tailles':
                $sizes = ModelRepository::parseSizes((string) get_post_meta($postId, ModelRepository::META_TAILLES, true));
                $labels = [];
                foreach ($sizes as $label => $price) {
                    $labels[] = $price === null ? $label : $label . ' (' . Money::format($price) . ')';
                }
                echo esc_html($labels ? implode(', ', $labels) : ModelRepository::TAILLE_UNIQUE);
                break;
            case 'pc_actif':
                echo get_post_meta($postId, ModelRepository::META_ACTIF, true) === '1' ? '✔' : '—';
                break;
        }
    }
}
