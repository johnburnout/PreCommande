<?php
/** @var int $count */
defined('ABSPATH') || exit;
?>
<p class="pc-counter">
    <strong><?php echo esc_html(number_format_i18n($count)); ?></strong>
    <?php echo esc_html(_n('fiche déjà saisie', 'fiches déjà saisies', $count, 'precommande')); ?>
</p>
