<?php
/**
 * @var string $nonce
 * @var string $tailles
 * @var string $prix
 * @var bool   $actif
 */
defined('ABSPATH') || exit;
?>
<input type="hidden" name="_pc_meta_nonce" value="<?php echo esc_attr($nonce); ?>">
<p>
    <label for="pc_prix"><strong>Prix unitaire (€)</strong></label><br>
    <input type="text" id="pc_prix" name="pc_prix" class="widefat" inputmode="decimal" value="<?php echo esc_attr($prix); ?>" placeholder="25,00">
    <span class="description">Facultatif. Laisser vide si aucun prix n'est annoncé.</span>
</p>
<p>
    <label for="pc_tailles"><strong>Tailles</strong></label><br>
    <textarea id="pc_tailles" name="pc_tailles" class="widefat" rows="6" placeholder="S&#10;M&#10;L&#10;XXL = 27,50"><?php echo esc_textarea($tailles); ?></textarea>
    <span class="description">Une taille par ligne, dans l'ordre d'affichage. Pour un prix différent : <code>XXL = 27,50</code>. Vide = taille unique.</span>
</p>
<p>
    <label><input type="checkbox" name="pc_actif" value="1" <?php checked($actif); ?>> Proposé dans le formulaire</label>
</p>
<p class="description">La photo est l'« image mise en avant ». L'ordre d'affichage se règle via « Attributs › Ordre ».</p>
