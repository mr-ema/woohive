<?php use WooHive\Config\Constants; ?>

<div class="wrap">
    <h1><?php echo esc_html( $error_title ); ?></h1>
    <p><?php echo esc_html( $error_message ); ?></p>
    <a href="<?php echo esc_url( $back_url ); ?>" class="button button-primary">
        <?php echo esc_html__( 'Volver', Constants::TEXT_DOMAIN ); ?>
    </a>
</div>

<style>
.wrap {
    background-color: #f8d7da;
    border: 1px solid #f5c2c7;
    padding: 2rem;
    border-radius: 4px;
}

.wrap h1 {
    color: #842029;
}

.wrap p {
    color: #842029;
}
</style>
