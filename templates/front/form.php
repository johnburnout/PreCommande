<?php
/**
 * Formulaire public de précommande.
 *
 * @var int    $count
 * @var array  $models
 * @var array  $errors
 * @var array  $old
 * @var bool   $success
 * @var string $action
 * @var string $formAction
 * @var string $redirectTo
 */
defined('ABSPATH') || exit;

use PreCommande\Money;

$hasPrices = (bool) array_filter(array_column($models, 'prix_max'), static fn ($p): bool => $p !== null);
?>
<div id="precommande" class="pc">
    <p class="pc-counter">
        <strong><?php echo esc_html(number_format_i18n($count)); ?></strong>
        <?php echo esc_html(_n('fiche déjà saisie', 'fiches déjà saisies', $count, 'precommande')); ?>
    </p>

    <?php if ($success) : ?>
        <div class="pc-notice pc-notice--success">Merci ! Votre participation a bien été enregistrée. Un e-mail de confirmation vous a été envoyé.</div>
    <?php endif; ?>

    <?php if ($errors) : ?>
        <div class="pc-notice pc-notice--error" role="alert">
            <ul><?php foreach ($errors as $error) : ?><li><?php echo esc_html($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (!$models) : ?>
        <p>Aucun modèle n'est proposé pour le moment.</p>
    <?php else : ?>
    <form class="pc-form" method="post" action="<?php echo esc_url($action); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr($formAction); ?>">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirectTo); ?>">
        <?php wp_nonce_field($formAction, '_pc_nonce'); ?>
        <p class="pc-hp" aria-hidden="true"><label>Site web <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></p>

        <fieldset class="pc-identity">
            <legend>Vos coordonnées</legend>
            <p><label>Nom *<br><input type="text" name="nom" required maxlength="100" value="<?php echo esc_attr($old['nom'] ?? ''); ?>" autocomplete="family-name"></label></p>
            <p><label>Prénom *<br><input type="text" name="prenom" required maxlength="100" value="<?php echo esc_attr($old['prenom'] ?? ''); ?>" autocomplete="given-name"></label></p>
            <p><label>E-mail *<br><input type="email" name="email" required maxlength="190" value="<?php echo esc_attr($old['email'] ?? ''); ?>" autocomplete="email"></label></p>
            <p><label>Téléphone<br><input type="tel" name="telephone" maxlength="30" value="<?php echo esc_attr($old['telephone'] ?? ''); ?>" autocomplete="tel"></label></p>
        </fieldset>

        <fieldset class="pc-models">
            <legend>Vos choix</legend>
            <div class="pc-grid">
            <?php foreach ($models as $model) : ?>
                <div class="pc-card">
                    <?php if ($model['image']) : ?>
                        <img class="pc-card__img" src="<?php echo esc_url($model['image']); ?>" alt="<?php echo esc_attr($model['titre']); ?>" loading="lazy">
                    <?php endif; ?>
                    <h3 class="pc-card__title"><?php echo esc_html($model['titre']); ?></h3>
                    <?php if ($model['prix_min'] !== null) : ?>
                        <p class="pc-card__price">
                            <?php echo $model['prix_min'] === $model['prix_max']
                                ? esc_html(Money::format($model['prix_min']))
                                : esc_html('dès ' . Money::format($model['prix_min'])); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($model['description']) : ?>
                        <div class="pc-card__desc"><?php echo wp_kses_post($model['description']); ?></div>
                    <?php endif; ?>
                    <div class="pc-sizes">
                    <?php foreach ($model['tailles'] as $i => $taille) :
                        $value = (int) ($old['qte'][$model['id']][$i] ?? 0);
                        $specific = $taille['prix'] !== null && $taille['prix'] !== $model['prix_min']; ?>
                        <label class="pc-size">
                            <span><?php echo esc_html($taille['label']); ?></span>
                            <input type="number" min="0" max="99" step="1" inputmode="numeric"
                                   name="qte[<?php echo (int) $model['id']; ?>][<?php echo (int) $i; ?>]"
                                   data-price="<?php echo esc_attr((string) ($taille['prix'] ?? 0)); ?>"
                                   value="<?php echo $value ?: ''; ?>" placeholder="0">
                            <?php if ($specific) : ?><small><?php echo esc_html(Money::format($taille['prix'])); ?></small><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="pc-summary" aria-live="polite">
            <span><strong data-pc-count>0</strong> article(s)</span>
            <?php if ($hasPrices) : ?>
                <span>Total estimé : <strong data-pc-total><?php echo esc_html(Money::format(0.0)); ?></strong></span>
            <?php endif; ?>
        </div>

        <p class="pc-mentions">* Champs obligatoires. Vos coordonnées servent uniquement à vous recontacter au sujet de cette précommande.</p>
        <p><button type="submit" class="pc-submit">Envoyer ma fiche</button></p>
    </form>
    <?php endif; ?>
</div>
