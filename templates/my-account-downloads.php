<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * My Account - Downloads Page
 * 
 * Shows customer's available downloads
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$customer_email = wp_get_current_user()->user_email;
$downloads = HNG_Digital_Product::get_customer_downloads($customer_email);
?>

<div class="hng-my-downloads" role="main" aria-label="Meus downloads" tabindex="0">
    <h2><?php esc_html_e( 'Meus Downloads', 'hng-commerce'); ?></h2>
    

        <div class="hng-my-downloads" role="main" aria-label="Meus downloads">
            <h2><?php esc_html_e( 'Meus Downloads', 'hng-commerce'); ?></h2>
            <?php do_action('hng_before_my_downloads'); ?>
            <?php if (empty($downloads)) : ?>
                <p class="hng-no-downloads" aria-live="polite"><?php esc_html_e( 'Você ainda não possui downloads disponíveis.', 'hng-commerce'); ?></p>
            <?php else : ?>
                <div class="hng-downloads-table-responsive" tabindex="0" aria-label="Tabela de downloads - role region">
                <table class="hng-downloads-table" role="table" aria-label="Tabela de downloads">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Produto', 'hng-commerce'); ?></th>
                            <th scope="col"><?php esc_html_e( 'Arquivo', 'hng-commerce'); ?></th>
                            <th scope="col"><?php esc_html_e( 'Expira em', 'hng-commerce'); ?></th>
                            <th scope="col"><?php esc_html_e( 'Ações', 'hng-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                <?php foreach ($downloads as $download) : 
                    $expired = $download['expires_at'] && strtotime($download['expires_at']) < time();
                    $limit_reached = $download['download_limit'] > 0 && $download['download_count'] >= $download['download_limit'];
                    $can_download = !$expired && !$limit_reached;
                ?>
                <tr class="<?php echo esc_attr( $can_download ? '' : 'download-unavailable' ); ?>">
                    <td class="product-name">
                        <strong><?php echo esc_html($download['product_name']); ?></strong>
                    </td>
                    <td class="order-id">
                        #<?php echo esc_html($download['order_id']); ?>
                    </td>
                    <td class="download-count">
                        <?php 
                        if ( $download['download_limit'] == -1 ) {
                            /* translators: %d: placeholder */
                            printf( esc_html__( '%d downloads', 'hng-commerce'), esc_html( number_format_i18n( intval( $download['download_count'] ) ) ) );
                        } else {
                            /* translators: %1$d: current download count, %2$d: download limit */
                        printf( esc_html__( '%1$d / %2$d', 'hng-commerce'), intval( $download['download_count'] ), intval( $download['download_limit'] ) );
                        }
                        ?>
                    </td>
                    <td class="expiry">
                        <?php 
                        if ($download['expires_at']) {
                            $expiry_date = strtotime($download['expires_at']);
                            if ( $expired ) {
                                echo '<span class="expired">' . esc_html__( 'Expirado', 'hng-commerce') . '</span>';
                            } else {
                                echo esc_html( date_i18n( get_option( 'date_format' ), $expiry_date ) );
                            }
                        } else {
                            echo esc_html__( 'Nunca', 'hng-commerce');
                        }
                        ?>
                    </td>
                    <td class="download-action">
                        <?php if ($can_download) : ?>
                            <a href="<?php echo esc_url(home_url('/download/' . $download['download_id'])); ?>" 
                               class="button hng-download-button">
                                <?php esc_html_e( 'Baixar', 'hng-commerce'); ?>
                            </a>
                        <?php else : ?>
                            <span class="unavailable">
                                <?php 
                                if ($expired) {
                                    esc_html_e( 'Expirado', 'hng-commerce');
                                } elseif ($limit_reached) {
                                    esc_html_e( 'Limite atingido', 'hng-commerce');
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
                </table>
                </div>
    <?php endif; ?>
</div>

<style>
.hng-downloads-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.hng-downloads-table th,
.hng-downloads-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.hng-downloads-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.hng-downloads-table tr.download-unavailable {
    opacity: 0.6;
    background-color: #fafafa;
}

.hng-downloads-table .expired {
    color: #d32f2f;
    font-weight: bold;
}

.hng-download-button {
    display: inline-block;
    padding: 8px 16px;
    background-color: #2196F3;
    color: white;
    text-decoration: none;
    border-radius: 4px;
}

.hng-download-button:hover {
    background-color: #1976D2;
}

.hng-no-downloads {
    padding: 40px;
    text-align: center;
    background-color: #f5f5f5;
    border-radius: 4px;
}
</style>
