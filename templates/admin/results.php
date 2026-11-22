<?php
/**
 * @var int    $count
 * @var array  $stats
 * @var array  $participations
 * @var array  $models
 * @var string $exportUrl
 * @var string $deleteAction
 * @var bool   $deleted
 */
defined('ABSPATH') || exit;

use PreCommande\Money;

$grandTotal = array_sum(array_column($stats, 'total'));
$grandAmount = array_sum(array_column($stats, 'montant'));
?>
<div class="wrap pc-admin">
    <h1 class="wp-heading-inline">PréCommande — Résultats</h1>
    <a href="<?php echo esc_url($exportUrl); ?>" class="page-title-action">Exporter en CSV</a>
    <hr class="wp-header-end">

    <?php if ($deleted) : ?>
        <div class="notice notice-success is-dismissible"><p>Fiche supprimée.</p></div>
    <?php endif; ?>

    <div class="pc-kpis">
        <div class="pc-kpi"><span class="pc-kpi__value"><?php echo esc_html(number_format_i18n($count)); ?></span><span class="pc-kpi__label">fiches saisies</span></div>
        <div class="pc-kpi"><span class="pc-kpi__value"><?php echo esc_html(number_format_i18n($grandTotal)); ?></span><span class="pc-kpi__label">articles demandés</span></div>
        <div class="pc-kpi"><span class="pc-kpi__value"><?php echo esc_html(Money::format($grandAmount)); ?></span><span class="pc-kpi__label">montant estimé</span></div>
        <div class="pc-kpi"><span class="pc-kpi__value"><?php echo esc_html(number_format_i18n(count($models))); ?></span><span class="pc-kpi__label">modèles</span></div>
    </div>

    <h2>Synthèse par modèle et par taille</h2>
    <?php if (!$stats) : ?>
        <p>Aucun modèle défini. <a href="<?php echo esc_url(admin_url('post-new.php?post_type=pc_modele')); ?>">Ajouter un modèle</a></p>
    <?php else : ?>
    <div class="pc-stats">
        <?php foreach ($stats as $model) : $max = max(1, ...array_column($model['tailles'], 'total')); ?>
        <div class="pc-stat card">
            <div class="pc-stat__head">
                <?php if ($model['image']) : ?><img src="<?php echo esc_url($model['image']); ?>" alt=""><?php endif; ?>
                <h3><?php echo esc_html($model['titre']); ?></h3>
                <span class="pc-stat__total">
                    <?php echo esc_html(number_format_i18n($model['total'])); ?>
                    <?php if ($model['montant'] > 0) : ?><small><?php echo esc_html(Money::format($model['montant'])); ?></small><?php endif; ?>
                </span>
            </div>
            <table class="widefat striped">
                <thead><tr><th>Taille</th><th>Prix</th><th>Quantité</th><th>Montant</th><th>Participants</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($model['tailles'] as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['taille']); ?></td>
                        <td><?php echo esc_html(Money::format($row['prix']) ?: '—'); ?></td>
                        <td><strong><?php echo (int) $row['total']; ?></strong></td>
                        <td><?php echo esc_html($row['montant'] > 0 ? Money::format($row['montant']) : '—'); ?></td>
                        <td><?php echo (int) $row['participants']; ?></td>
                        <td class="pc-bar-cell"><span class="pc-bar" style="width:<?php echo esc_attr((string) round($row['total'] / $max * 100)); ?>%"></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2>Détail des fiches</h2>
    <table class="widefat striped pc-detail">
        <thead>
            <tr><th>Date</th><th>Nom</th><th>Prénom</th><th>E-mail</th><th>Téléphone</th><th>Articles</th><th>Total</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$participations) : ?>
            <tr><td colspan="8">Aucune fiche pour le moment.</td></tr>
        <?php endif; ?>
        <?php foreach ($participations as $p) : ?>
            <tr>
                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' H:i', $p['created_at'])); ?></td>
                <td><?php echo esc_html($p['nom']); ?></td>
                <td><?php echo esc_html($p['prenom']); ?></td>
                <td><a href="mailto:<?php echo esc_attr($p['email']); ?>"><?php echo esc_html($p['email']); ?></a></td>
                <td><?php echo esc_html($p['telephone']); ?></td>
                <td>
                    <?php foreach ($p['lignes'] as $l) : ?>
                        <div><?php echo esc_html(sprintf('%s — %s × %d', $models[$l['modele_id']]['titre'] ?? ('#' . $l['modele_id']), $l['taille'], $l['quantite'])
                            . ($l['prix_unitaire'] !== null ? ' à ' . Money::format($l['prix_unitaire']) : '')); ?></div>
                    <?php endforeach; ?>
                </td>
                <td><?php echo esc_html($p['total'] > 0 ? Money::format($p['total']) : '—'); ?></td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Supprimer cette fiche ?');">
                        <input type="hidden" name="action" value="<?php echo esc_attr($deleteAction); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                        <?php wp_nonce_field($deleteAction); ?>
                        <button type="submit" class="button-link button-link-delete">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
