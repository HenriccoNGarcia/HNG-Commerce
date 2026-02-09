<?php
/**
 * Correções de Compatibilidade com Elementor
 * Resolve conflitos SEM bloquear ou suprimir warnings
 * 
 * @package HNG_Commerce
 * @since 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Commerce_Compatibility_V2 {

    /**
     * Inicialização simples de compatibilidade.
     * Não cria posts, não bloqueia Elementor, apenas evita que ele rode em telas onde não há contexto de post.
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'soft_isolation_for_hng_admin'), 5);
    }

    /**
     * Em telas do HNG (submenus do plugin), retirar ações específicas do Elementor que assumem existência de post.
     * Mantï¿½m editor Elementor funcional em pï¿½ginas/elementos reais.
     */
    public static function soft_isolation_for_hng_admin() {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || !isset($screen->id)) {
            return;
        }

        // IDs de telas do HNG (ajuste se necessï¿½rio)
        $hng_screens = array(
            'toplevel_page_hng-commerce',
            'hng-commerce_page_hng-products',
            'hng-commerce_page_hng-orders',
            'hng-commerce_page_hng-reports',
            'hng-commerce_page_hng-payment-settings',
            'hng-commerce_page_hng-merchant-registration',
        );

        if (in_array($screen->id, $hng_screens, true)) {
            // Remover apenas aï¿½ï¿½es administrativas que carregam UI de editor desnecessï¿½ria
            remove_all_actions('elementor/admin/after_create_settings');
            // Nï¿½o remover init/loaded para nï¿½o quebrar editor real
        }
    }
}

add_action('plugins_loaded', array('HNG_Commerce_Compatibility_V2', 'init'), 1);
