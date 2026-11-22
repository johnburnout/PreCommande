<?php
/**
 * @var array    $models
 * @var int      $total
 * @var string   $action
 * @var int|null $sent
 * @var bool     $error
 */
defined('ABSPATH') || exit;
?>
<div class="wrap pc-admin">
    <h1>Contacter les participants</h1>

    <?php if ($sent !== null) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf('%d e-mail(s) envoyé(s).', $sent)); ?></p></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div class="notice notice-error is-dismissible"><p>Le sujet et le message sont obligatoires.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Envoyer ce message ?');">
        <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
        <?php wp_nonce_field($action); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="pc-modele">Destinataires</label></th>
                <td>
                    <select id="pc-modele" name="modele">
                        <option value="0">Tous les participants (<?php echo (int) $total; ?>)</option>
                        <?php foreach ($models as $model) : ?>
                            <option value="<?php echo (int) $model['id']; ?>">Ceux ayant choisi : <?php echo esc_html($model['titre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pc-sujet">Sujet</label></th>
                <td><input type="text" id="pc-sujet" name="sujet" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="pc-message">Message</label></th>
                <td>
                    <textarea id="pc-message" name="message" rows="10" class="large-text" required>Bonjour {prenom},

</textarea>
                    <p class="description">Variables : <code>{prenom}</code>, <code>{nom}</code>. Un e-mail individuel est envoyé à chaque participant.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Envoyer'); ?>
    </form>
</div>
