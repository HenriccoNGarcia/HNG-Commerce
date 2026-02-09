<?php
/**
 * HNG Admin - Professionals Page
 *
 * Página de gerenciamento centralizado de profissionais
 *
 * @package HNG_Commerce
 * @subpackage Admin/Pages
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Admin_Professionals_Page {
    
    /**

     * Renderizar página

     */

    public static function render() {

        // Enqueue scripts and styles

        self::enqueue_scripts();

        

        echo '<div class="hng-wrap">';

        self::render_header();

        self::render_modal();

        self::render_table();

        echo '</div>';

    }

    

    /**

     * Enqueue scripts and styles

     */

    private static function enqueue_scripts() {

        wp_enqueue_script('jquery');

        wp_enqueue_script('hng-admin-professionals', 

            HNG_COMMERCE_URL . 'assets/js/admin-professionals.js',

            ['jquery'],

            HNG_COMMERCE_VERSION,

            true

        );

        

        wp_localize_script('hng-admin-professionals', 'hngProfessionalsPage', [

            'ajaxUrl' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce('hng-commerce-admin'),

            'i18n' => [

                'newProfessional' => __('Novo Profissional', 'hng-commerce'),

                'editProfessional' => __('Editar Profissional', 'hng-commerce'),

                'noProfessionals' => __('Nenhum profissional cadastrado ainda.', 'hng-commerce'),

                'saving' => __('Salvando...', 'hng-commerce'),

                'save' => __('Salvar', 'hng-commerce'),

                'cancel' => __('Cancelar', 'hng-commerce'),

                'delete' => __('Excluir', 'hng-commerce'),

                'edit' => __('Editar', 'hng-commerce'),

                'active' => __('Ativo', 'hng-commerce'),

                'inactive' => __('Inativo', 'hng-commerce'),

                /* translators: %s: professional name */

                'deleteConfirm' => __('Tem certeza que deseja excluir "%s"?', 'hng-commerce'),

                'error' => __('Erro ao processar a solicitação.', 'hng-commerce'),

                'invalidEmail' => __('E-mail inválido.', 'hng-commerce'),

            ]

        ]);

    }

    

    /**

     * Render page header

     */

    private static function render_header() {

        ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">

            <h1><?php esc_html_e('Profissionais', 'hng-commerce'); ?></h1>

            <button id="hng-new-professional-btn" class="button button-primary" style="font-size: 14px; padding: 8px 16px;">

                <?php esc_html_e('+ Novo Profissional', 'hng-commerce'); ?>

            </button>

        </div>

        <div id="hng-alert-container"></div>

        <?php

    }

    

    /**

     * Render table

     */

    private static function render_table() {

        ?>

        <table class="wp-list-table widefat striped">

            <thead>

                <tr>

                    <th><?php esc_html_e('ID', 'hng-commerce'); ?></th>

                    <th><?php esc_html_e('Nome', 'hng-commerce'); ?></th>

                    <th><?php esc_html_e('E-mail', 'hng-commerce'); ?></th>

                    <th><?php esc_html_e('Usuário WordPress', 'hng-commerce'); ?></th>

                    <th><?php esc_html_e('Telefone', 'hng-commerce'); ?></th>

                    <th><?php esc_html_e('Status', 'hng-commerce'); ?></th>

                    <th><?php esc_html_e('Ações', 'hng-commerce'); ?></th>

                </tr>

            </thead>

            <tbody id="hng-professionals-tbody">

                <tr><td colspan="7" style="text-align: center; padding: 20px;"><?php esc_html_e('Carregando...', 'hng-commerce'); ?></td></tr>

            </tbody>

        </table>

        <?php

        self::render_modal();

    }

    

    /**

     * Render modal for create/edit professional

     */

    private static function render_modal() {

        ?>

        <div id="hng-modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999;"></div>

        

        <div id="hng-new-professional-modal" class="hng-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10000; min-width: 500px; max-height: 90vh; overflow-y: auto;">

            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">

                <h2 style="margin: 0;"><?php esc_html_e('Novo Profissional', 'hng-commerce'); ?></h2>

                <button type="button" class="hng-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>

            </div>

            

            <div class="modal-body" style="padding: 20px;">

                <form id="hng-new-professional-form">

                    <input type="hidden" id="hng-prof-id" name="id" value="">

                    

                    <div style="margin-bottom: 15px;">

                        <label for="hng-prof-name" style="display: block; margin-bottom: 5px; font-weight: 500;">

                            <?php esc_html_e('Nome *', 'hng-commerce'); ?>

                        </label>

                        <input type="text" id="hng-prof-name" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">

                    </div>

                    

                    <div style="margin-bottom: 15px;">

                        <label for="hng-prof-email" style="display: block; margin-bottom: 5px; font-weight: 500;">

                            <?php esc_html_e('E-mail *', 'hng-commerce'); ?>

                        </label>

                        <input type="email" id="hng-prof-email" name="email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">

                    </div>

                    

                    <div style="margin-bottom: 15px;">

                        <label for="hng-prof-phone" style="display: block; margin-bottom: 5px; font-weight: 500;">

                            <?php esc_html_e('Telefone', 'hng-commerce'); ?>

                        </label>

                        <input type="tel" id="hng-prof-phone" name="phone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">

                    </div>

                    

                    <div style="margin-bottom: 15px;">

                        <label for="hng-prof-wp-user" style="display: block; margin-bottom: 5px; font-weight: 500;">

                            <?php esc_html_e('Usuário WordPress', 'hng-commerce'); ?>

                        </label>

                        <select id="hng-prof-wp-user" name="wp_user_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">

                            <option value=""><?php esc_html_e('-- Nenhum usuário --', 'hng-commerce'); ?></option>

                            <?php self::render_wordpress_users_options(); ?>

                        </select>

                    </div>

                    

                    <div style="margin-bottom: 15px;">

                        <label for="hng-prof-notes" style="display: block; margin-bottom: 5px; font-weight: 500;">

                            <?php esc_html_e('Anotações', 'hng-commerce'); ?>

                        </label>

                        <textarea id="hng-prof-notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>

                    </div>

                    

                    <div style="margin-bottom: 15px;">

                        <label style="display: flex; align-items: center; font-weight: 500;">

                            <input type="checkbox" id="hng-prof-active" name="active" checked style="margin-right: 8px;">

                            <?php esc_html_e('Ativo', 'hng-commerce'); ?>

                        </label>

                    </div>

                    

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">

                        <button type="button" class="button" id="hng-modal-cancel-btn" style="cursor: pointer;">

                            <?php esc_html_e('Cancelar', 'hng-commerce'); ?>

                        </button>

                        <button type="submit" class="button button-primary" style="cursor: pointer;">

                            <?php esc_html_e('Salvar Profissional', 'hng-commerce'); ?>

                        </button>

                    </div>

                </form>

            </div>

        </div>

        

        <script>

        jQuery(function($) {

            $('#hng-modal-cancel-btn').on('click', function() {

                $('#hng-new-professional-modal').fadeOut();

                $('#hng-modal-backdrop').fadeOut();

            });

        });

        </script>

        <?php

    }

    

    /**

     * Render WordPress users options

     */

    private static function render_wordpress_users_options() {

        $users = get_users(['role__not_in' => 'subscriber']);

        

        foreach ($users as $user) {

            echo '<option value="' . intval($user->ID) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</option>';

        }

    }

}

