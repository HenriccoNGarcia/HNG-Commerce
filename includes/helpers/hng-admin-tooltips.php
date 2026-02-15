<?php
/**
 * HNG Commerce - Admin Tooltips Helper
 * 
 * Funções para criar tooltips informativos nas páginas de admin
 * 
 * @package HNG_Commerce
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderiza um ícone de ajuda com tooltip
 * 
 * @param string $title Título do tooltip
 * @param array $sections Array de seções com 'title', 'icon', 'pros', 'cons', 'content'
 * @param array $recommendation Array com 'title' e 'items' para recomendações
 * @param int $width Largura do tooltip em pixels
 * @return string HTML do tooltip
 */
function hng_admin_tooltip($title, $sections = [], $recommendation = null, $width = 450) {
    static $tooltip_id = 0;
    $tooltip_id++;
    
    ob_start();
    ?>
    <span class="hng-help-icon" id="hng-tooltip-<?php echo esc_attr($tooltip_id); ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; background: #2271b1; color: #fff; border-radius: 50%; font-size: 12px; font-weight: bold; cursor: help; margin-left: 8px; position: relative;">?
        <div class="hng-help-tooltip" style="display: none; position: absolute; top: 30px; left: 50%; transform: translateX(-50%); width: <?php echo esc_attr($width); ?>px; background: #1d2327; color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 9999; font-weight: normal; font-size: 13px; line-height: 1.6; text-align: left;">
            <div style="position: absolute; top: -8px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent; border-bottom: 8px solid #1d2327;"></div>
            
            <?php if ($title): ?>
            <h4 style="margin: 0 0 15px 0; color: #72aee6; font-size: 15px; border-bottom: 1px solid #3c434a; padding-bottom: 10px;"><?php echo esc_html($title); ?></h4>
            <?php endif; ?>
            
            <?php foreach ($sections as $section): ?>
            <div style="margin-bottom: 15px;">
                <?php if (isset($section['title'])): ?>
                <strong style="color: #72aee6;"><?php echo isset($section['icon']) ? esc_html($section['icon']) . ' ' : ''; ?><?php echo esc_html($section['title']); ?></strong>
                <?php endif; ?>
                
                <?php if (isset($section['pros']) || isset($section['cons'])): ?>
                <div style="margin: 5px 0 0 0; padding-left: 10px; border-left: 2px solid #3c434a;">
                    <?php if (isset($section['pros'])): ?>
                    <div style="color: #8bc34a;">✅ <?php echo esc_html($section['pros']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($section['cons'])): ?>
                    <div style="color: #ff9800;">⚠️ <?php echo esc_html($section['cons']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($section['content'])): ?>
                <div style="margin: 5px 0 0 0; color: #c3c4c7;"><?php echo wp_kses_post($section['content']); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php if ($recommendation): ?>
            <div style="background: #2c3338; padding: 12px; border-radius: 6px; margin-top: 15px;">
                <?php if (isset($recommendation['title'])): ?>
                <strong style="color: #f0b849; display: block; margin-bottom: 8px;"><?php echo esc_html($recommendation['title']); ?></strong>
                <?php endif; ?>
                
                <?php if (isset($recommendation['items'])): ?>
                <div style="font-size: 12px;">
                    <?php foreach ($recommendation['items'] as $item): ?>
                    <div style="margin-bottom: 6px;">
                        <?php if (isset($item['label'])): ?>
                        <strong style="color: #fff;"><?php echo esc_html($item['label']); ?>:</strong><br>
                        <?php endif; ?>
                        <?php if (isset($item['text'])): ?>
                        <span style="color: #8bc34a;"><?php echo esc_html($item['text']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </span>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza um tooltip simples (apenas texto)
 * 
 * @param string $text Texto do tooltip
 * @param int $width Largura do tooltip em pixels
 * @return string HTML do tooltip
 */
function hng_admin_tooltip_simple($text, $width = 300) {
    static $simple_id = 0;
    $simple_id++;
    
    ob_start();
    ?>
    <span class="hng-help-icon" id="hng-simple-tooltip-<?php echo esc_attr($simple_id); ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; background: #72aee6; color: #fff; border-radius: 50%; font-size: 11px; font-weight: bold; cursor: help; margin-left: 6px; position: relative;">?
        <div class="hng-help-tooltip" style="display: none; position: absolute; top: 26px; left: 50%; transform: translateX(-50%); width: <?php echo esc_attr($width); ?>px; background: #1d2327; color: #c3c4c7; padding: 12px 15px; border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.25); z-index: 9999; font-weight: normal; font-size: 12px; line-height: 1.5; text-align: left;">
            <div style="position: absolute; top: -6px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-bottom: 6px solid #1d2327;"></div>
            <?php echo wp_kses_post($text); ?>
        </div>
    </span>
    <?php
    return ob_get_clean();
}

/**
 * Adiciona o CSS para os tooltips (chamar uma vez por página)
 */
function hng_admin_tooltip_styles() {
    static $styles_added = false;
    if ($styles_added) return;
    $styles_added = true;
    ?>
    <style>
        .hng-help-icon:hover .hng-help-tooltip {
            display: block !important;
        }
        .hng-help-icon .hng-help-tooltip {
            max-height: 80vh;
            overflow-y: auto;
        }
        /* Ajuste para tooltips que ficam muito à direita */
        @media (max-width: 1200px) {
            .hng-help-icon .hng-help-tooltip {
                left: auto !important;
                right: 0;
                transform: none !important;
            }
            .hng-help-icon .hng-help-tooltip > div:first-child {
                left: auto !important;
                right: 10px;
                transform: none !important;
            }
        }
    </style>
    <?php
}

/**
 * Registra o helper para ser carregado
 */
add_action('admin_head', 'hng_admin_tooltip_styles');
