<?php
/**
 * CSS para Email Customizer Page v2
 * Estilos visuais da interface de customização de emails
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>

/* Layout Principal */
.hng-email-customizer-v2 { margin: 20px 20px 20px 0; }
.hng-page-title { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 28px; }

/* Barra de Templates */
.hng-email-templates-bar { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
.templates-label { font-weight: bold; margin-bottom: 10px; color: #333; }
.templates-list { display: flex; gap: 10px; flex-wrap: wrap; }
.template-item { padding: 8px 12px; border: 2px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; transition: all 0.3s; cursor: pointer; display: flex; align-items: center; gap: 5px; }
.template-item:hover { border-color: #0073aa; background: #f0f8ff; }
.template-item.active { border-color: #0073aa; background: #e7f3ff; color: #0073aa; font-weight: bold; }

/* Layout Email Editor */
.hng-email-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }

/* Editor Container */
.hng-email-editor { background: white; border: 1px solid #ddd; border-radius: 4px; padding: 20px; max-height: calc(100vh - 200px); overflow-y: auto; }

/* Tabs */
.hng-editor-tabs { display: flex; gap: 5px; border-bottom: 2px solid #ddd; margin-bottom: 20px; }
.tab-btn { padding: 12px 20px; border: none; background: transparent; cursor: pointer; border-bottom: 3px solid transparent; font-size: 14px; font-weight: 500; color: #666; transition: all 0.3s; }
.tab-btn:hover { color: #0073aa; }
.tab-btn.active { color: #0073aa; border-bottom-color: #0073aa; }

/* Tab Content */
.tab-content { display: none; }
.tab-content.active { display: block; animation: fadeIn 0.3s; }

/* Form Groups */
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
.form-group input[type="text"],
.form-group input[type="email"],
.form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; }
.form-group input[type="text"]:focus,
.form-group input[type="email"]:focus,
.form-group textarea:focus { outline: none; border-color: #0073aa; box-shadow: 0 0 5px rgba(0, 115, 170, 0.2); }

/* Color Grid */
.color-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

/* Color Picker */
.color-picker { width: 100%; height: 40px; cursor: pointer; }

/* WordPress Color Picker Fix */
.wp-picker-container { position: relative; }
.wp-picker-container .wp-picker-holder { position: absolute; z-index: 100; }
.wp-picker-container .wp-color-result { height: 40px; }
.form-group .wp-picker-container { margin-bottom: 0; }

/* Esconder paleta de cores padrão */
.iris-palette-container { display: none !important; }

/* Logo Uploader */
.logo-uploader { border: 2px dashed #ddd; border-radius: 4px; padding: 20px; text-align: center; }
.logo-preview-box { margin-bottom: 15px; }
.logo-img { max-width: 100%; height: auto; max-height: 100px; }
.logo-placeholder { background: #f5f5f5; padding: 30px; border-radius: 4px; }
.logo-placeholder .dashicons { font-size: 48px; width: 48px; height: 48px; color: #999; }
.logo-actions { display: flex; gap: 10px; justify-content: center; }

/* Editor Actions */
.hng-editor-actions { display: flex; gap: 10px; padding-top: 20px; border-top: 1px solid #ddd; }
.hng-editor-actions .button { margin-right: 10px; }

/* Preview Container */
.hng-email-preview { background: white; border: 1px solid #ddd; border-radius: 4px; padding: 20px; max-height: calc(100vh - 200px); overflow-y: auto; }
.preview-header { border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 15px; }
.preview-header h3 { margin: 0 0 15px 0; }
.preview-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

/* Preview Mode Toggle */
.preview-mode-toggle { display: flex; border: 1px solid #ddd; border-radius: 4px; }
.mode-btn { padding: 8px 12px; background: white; border: none; cursor: pointer; font-size: 12px; transition: all 0.3s; }
.mode-btn.active { background: #0073aa; color: white; }

/* Preview Device Tabs */
.preview-device-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #ddd; }
.device-tab { padding: 10px 15px; border: none; background: transparent; cursor: pointer; color: #666; border-bottom: 2px solid transparent; }
.device-tab.active { color: #0073aa; border-bottom-color: #0073aa; }

/* Preview Container */
.preview-container { position: relative; min-height: 300px; }
.preview-viewport { background: white; border: 1px solid #ddd; border-radius: 4px; padding: 20px; overflow: auto; }
.preview-viewport.desktop { max-height: 600px; }
.preview-viewport.mobile { max-width: 375px; }

/* Email Preview Editor */
.email-preview-editor { min-height: 300px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: text; outline: none; }
.email-preview-editor:focus { outline: 2px solid #0073aa; }

/* Code Editor */
.code-editor { width: 100%; height: 400px; padding: 15px; font-family: monospace; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; }

/* Preview Loading */
.preview-loading { display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 10; background: rgba(255,255,255,0.9); padding: 30px; border-radius: 8px; }
.preview-loading .spinner { margin: 0 auto 10px; }
.preview-loading p { color: #666; margin: 0; }

/* Variables List */
.variables-help { background: #f0f8ff; border-left: 4px solid #0073aa; padding: 15px; border-radius: 4px; }
.variables-help h4 { margin-top: 0; }
.variables-help ul { margin: 10px 0; padding-left: 20px; }

/* Variables Draggable */
.variables-draggable-list { display: flex; flex-direction: column; gap: 10px; }
.variable-item { padding: 10px; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; gap: 10px; cursor: move; transition: all 0.3s; }
.variable-item:hover { background: #f0f8ff; border-color: #0073aa; }
.drag-handle { cursor: grab; color: #999; }
.variable-code { background: #f5f5f5; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
.variable-desc { color: #666; font-size: 12px; flex: 1; }
.copy-var-btn { background: none; border: none; cursor: pointer; padding: 0; color: #0073aa; }
.copy-var-btn:hover { color: #005a87; }

/* Responsive */
@media (max-width: 1200px) {
    .hng-email-layout { grid-template-columns: 1fr; }
}

/* Editor Resize */
.editor-resize-wrapper { position: relative; }
.editor-resize-handle { text-align: center; padding: 10px; background: #f5f5f5; border-top: 1px solid #ddd; cursor: row-resize; color: #999; font-size: 12px; }
.editor-resize-handle:hover { background: #eee; }

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Description Text */
.description { font-size: 12px; color: #666; margin-top: 5px; }

/* Select Styling */
select, input[type="text"], textarea { box-sizing: border-box; }

/* WP Editor Override */
#wp-email-content-editor-wrap { margin: 0; }
#wp-email-content-editor-wrap .wp-editor-container { height: 400px; }

</style>
