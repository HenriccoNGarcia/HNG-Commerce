/**
 * Product Type Fields Handler
 * 
 * Gerencia a interatividade do editor de tipos de produto
 */

(function($) {
    'use strict';

    const ProductTypeEditor = {
        // Configurações
        config: {
            typeRadioSelector: '.hng-type-radio',
            typeCardSelector: '.hng-type-card',
            fieldWrapperSelector: '.hng-field-wrapper',
            fieldsSectionSelector: '.hng-fields-section',
            typeFieldsContainerSelector: '.hng-type-fields-container'
        },

        /**
         * Inicializar o editor
         */
        init: function() {
            console.log('[HNG] Product Type Editor initializing...');
            
            if (typeof hngProductFieldsData === 'undefined') {
                console.warn('[HNG] hngProductFieldsData not defined - showing all fields');
                // Show all fields as fallback
                $(this.config.fieldWrapperSelector).removeClass('hng-hidden').css('display', 'block');
                $(this.config.fieldsSectionSelector).removeClass('hng-hidden').css('display', 'block');
                return;
            }
            
            console.log('[HNG] Product types loaded:', Object.keys(hngProductFieldsData.types));

            this.cacheElements();
            this.bindEvents();
            this.updateFieldsVisibility();
            
            // Forçar atualização de visibilidade dos campos de frete para assinatura
            console.log('[HNG] Calling updateSubscriptionShippingVisibility...');
            this.updateSubscriptionShippingVisibility();
            
            this.initFieldValidation();
            this.initFileUpload();
            this.initRepeater();
            
            console.log('[HNG] Product Type Editor initialized successfully');
        },

        /**
         * Cache elementos do DOM
         */
        cacheElements: function() {
            this.$document = $(document);
            this.$typeRadios = $(this.config.typeRadioSelector);
            this.$typeCards = $(this.config.typeCardSelector);
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;

            // Quando tipo é alterado
            this.$typeRadios.on('change', function() {
                self.handleTypeChange($(this).val());
            });

            // Click em card de tipo
            this.$typeCards.on('click', function(e) {
                if (!$(e.target).is('input')) {
                    const $radio = $(this).find(self.config.typeRadioSelector);
                    $radio.prop('checked', true).trigger('change');
                }
            });

            // Toggle shipping fields for assinatura
            this.$document.on('change', '#hng_field_subscription_requires_shipping', function() {
                self.updateSubscriptionShippingVisibility();
            });

            // Real-time validation
            this.$document.on('change input', '.hng-input, .hng-textarea, .hng-select', function() {
                self.validateField($(this));
            });
        },

        /**
         * Handle type change
         */
        handleTypeChange: function(newType) {
            // Update UI
            this.updateTypeCardUI(newType);
            this.updateFieldsVisibility(newType);
            this.animateFieldsChange();
        },

        /**
         * Update type card UI
         */
        updateTypeCardUI: function(activeType) {
            this.$typeCards.removeClass('active');
            this.$typeCards.filter('[data-type="' + activeType + '"]').addClass('active');
        },

        /**
         * Update fields visibility based on type
         */
        updateFieldsVisibility: function(type) {
            type = type || this.getCurrentType();
            
            // Get allowed fields for this type
            const allowedFields = this.getAllowedFieldsForType(type);
            
            // Use CSS class instead of hide/show for better specificity
            $(this.config.fieldWrapperSelector).each(function() {
                const $wrapper = $(this);
                const fieldName = $wrapper.data('field');
                
                if (allowedFields.includes(fieldName)) {
                    $wrapper.removeClass('hng-hidden').css('display', 'block');
                } else {
                    $wrapper.addClass('hng-hidden').css('display', 'none');
                }
            });
            
            // Update sections visibility based on visible fields
            $(this.config.fieldsSectionSelector).each(function() {
                const $section = $(this);
                const hasVisibleFields = $section.find(ProductTypeEditor.config.fieldWrapperSelector + ':not(.hng-hidden)').length > 0;
                
                if (hasVisibleFields) {
                    $section.removeClass('hng-hidden').css('display', 'block');
                    ProductTypeEditor.updateFieldAttributes($section, type);
                } else {
                    $section.addClass('hng-hidden').css('display', 'none');
                }
            });

            this.updateSubscriptionShippingVisibility(type);
        },

        /**
         * Show/hide shipping fields when subscription requires delivery
         */
        updateSubscriptionShippingVisibility: function(currentType) {
            currentType = currentType || this.getCurrentType();
            console.log('[HNG] updateSubscriptionShippingVisibility called, type:', currentType);
            
            if (currentType !== 'subscription') {
                console.log('[HNG] Not subscription type, skipping shipping visibility update');
                return;
            }

            const $checkbox = $('#hng_field_subscription_requires_shipping');
            console.log('[HNG] Checkbox found:', $checkbox.length > 0);
            const enabled = $checkbox.is(':checked');
            const shippingFields = ['weight', 'length', 'width', 'height'];
            const $shippingSection = $(this.config.fieldsSectionSelector + '[data-section="shipping"]');

            console.log('[HNG] Subscription shipping checkbox checked:', enabled);

            shippingFields.forEach((field) => {
                const $wrapper = $(this.config.fieldWrapperSelector + '[data-field="' + field + '"]');
                if (!$wrapper.length) {
                    console.log('[HNG] Field wrapper not found:', field);
                    return;
                }

                if (enabled) {
                    $wrapper.removeClass('hng-hidden').css('display', 'block');
                    console.log('[HNG] Showing field:', field);
                } else {
                    $wrapper.addClass('hng-hidden').css('display', 'none');
                    console.log('[HNG] Hiding field:', field);
                }
            });

            if ($shippingSection.length) {
                // Conta apenas campos visíveis excluindo o checkbox
                const visibleCount = $shippingSection.find(this.config.fieldWrapperSelector + ':not(.hng-hidden)').not('[data-field="subscription_requires_shipping"]').length;
                console.log('[HNG] Visible shipping fields count:', visibleCount);
                
                // Sempre mostrar a seção se o checkbox existir
                $shippingSection.removeClass('hng-hidden').css('display', 'block');
            }
        },

        /**
         * Get all allowed fields for a type
         */
        getAllowedFieldsForType: function(type) {
            if (typeof hngProductFieldsData === 'undefined' || !hngProductFieldsData.types) {
                return [];
            }

            const typeConfig = hngProductFieldsData.types[type];
            if (!typeConfig || !typeConfig.fields) {
                return [];
            }

            const allowedFields = [];
            for (const section in typeConfig.fields) {
                if (typeConfig.fields.hasOwnProperty(section)) {
                    allowedFields.push(...typeConfig.fields[section]);
                }
            }
            return allowedFields;
        },

        /**
         * Check if section should be visible for type
         */
        isSectionVisibleForType: function(section, type) {
            if (typeof hngProductFieldsData === 'undefined' || !hngProductFieldsData.types) {
                return true;
            }

            const typeConfig = hngProductFieldsData.types[type];
            if (!typeConfig || !typeConfig.fields) {
                return false;
            }

            return section in typeConfig.fields;
        },

        /**
         * Update field attributes based on product type
         */
        updateFieldAttributes: function($section, type) {
            // Add visual indicators for required fields based on type
            const requiredByType = {
                'simple': ['price'],
                'variable': ['product_attributes', 'product_variations'],
                'subscription': ['subscription_price', 'subscription_recurrence'],
                'digital': ['price', 'download_file'],
                'quote': [],
                'appointment': ['price', 'service_duration', 'appointment_professionals']
            };

            const required = requiredByType[type] || [];
            
            $section.find(this.config.fieldWrapperSelector).each(function() {
                const $wrapper = $(this);
                const fieldName = $wrapper.data('field');
                const $label = $wrapper.find('label:first');
                
                // Remove old required indicator
                $label.find('.required').remove();
                $wrapper.removeClass('required-field');
                
                // Add new required indicator if needed
                if (required.includes(fieldName)) {
                    $wrapper.addClass('required-field');
                    $label.append(' <span class="required">*</span>');
                }
            });
        },

        /**
         * Animate fields change
         */
        animateFieldsChange: function() {
            $(this.config.fieldsSectionSelector + ':visible').each(function(index) {
                $(this).css({
                    'animation': 'none',
                    'opacity': '0',
                    'transform': 'translateY(-10px)'
                });
                
                setTimeout(() => {
                    $(this).css({
                        'animation': 'slideDown 0.3s ease-out forwards',
                        'animation-delay': (index * 50) + 'ms'
                    });
                }, 10);
            });
        },

        /**
         * Get current selected type
         */
        getCurrentType: function() {
            return this.$typeRadios.filter(':checked').val() || 'simple';
        },

        /**
         * Validate field
         */
        validateField: function($field) {
            const type = $field.attr('type');
            const value = $field.val();
            const $wrapper = $field.closest(this.config.fieldWrapperSelector);

            // Clear previous error state
            $wrapper.removeClass('field-error');
            $wrapper.find('.field-error-message').remove();

            // Validation logic
            let isValid = true;
            let errorMessage = '';

            switch (type) {
                case 'number':
                    if (value && isNaN(value)) {
                        isValid = false;
                        errorMessage = 'Por favor, insira um número válido';
                    }
                    break;
                case 'url':
                    if (value && !this.isValidUrl(value)) {
                        isValid = false;
                        errorMessage = 'Por favor, insira uma URL válida';
                    }
                    break;
            }

            // Show error if invalid
            if (!isValid) {
                $wrapper.addClass('field-error');
                $field.after('<span class="field-error-message">' + errorMessage + '</span>');
            }

            return isValid;
        },

        /**
         * Validate URL
         */
        isValidUrl: function(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        },

        /**
         * Initialize field validation
         */
        initFieldValidation: function() {
            const self = this;
            
            // Validate on save
            $(document).on('submit', 'form#post', function(e) {
                const $form = $(this);
                let hasErrors = false;

                // Validate all visible fields
                $form.find('.hng-field-wrapper:visible').each(function() {
                    const $wrapper = $(this);
                    const $input = $wrapper.find('.hng-input, .hng-textarea, .hng-select');
                    
                    if ($input.length && !self.validateField($input)) {
                        hasErrors = true;
                    }
                });

                if (hasErrors) {
                    e.preventDefault();
                    alert('Por favor, corrija os erros abaixo antes de salvar.');
                    return false;
                }
            });
        },

        /**
         * Initialize file upload handlers
         */
        initFileUpload: function() {
            const self = this;
            
            // Upload button click
            $(document).on('click', '.hng-upload-button', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const targetId = $button.data('target');
                const $input = $('#' + targetId);
                const $fileName = $button.siblings('.hng-file-name');
                const $removeBtn = $button.siblings('.hng-remove-file');
                
                // Open WordPress media library
                const frame = wp.media({
                    title: 'Selecionar Arquivo',
                    button: {
                        text: 'Usar este arquivo'
                    },
                    multiple: false
                });
                
                // When an image is selected
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    
                    $input.val(attachment.id);
                    $fileName.text(attachment.filename || attachment.title);
                    $removeBtn.show();
                });
                
                frame.open();
            });
            
            // Remove file button
            $(document).on('click', '.hng-remove-file', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $wrapper = $button.closest('.hng-file-upload');
                const $input = $wrapper.find('.hng-file-input');
                const $fileName = $wrapper.find('.hng-file-name');
                
                $input.val('');
                $fileName.text('Nenhum arquivo selecionado');
                $button.hide();
            });
        },

        /**
         * Initialize repeater handlers
         */
        initRepeater: function() {
            const self = this;
            
            // Add new repeater item
            $(document).on('click', '.hng-add-repeater-item', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const fieldKey = $button.data('field');
                const $repeater = $button.closest('.hng-repeater');
                const $items = $repeater.find('.hng-repeater-items');
                const template = $('#hng-repeater-template-' + fieldKey).html();
                
                // Get next index
                const currentIndex = $items.children().length;
                
                // Replace {{INDEX}} placeholder with actual index
                const newItem = template.replace(/\{\{INDEX\}\}/g, currentIndex);
                
                // Append new item
                $items.append(newItem);
                
                // Update item numbers
                self.updateRepeaterNumbers($repeater);
            });
            
            // Remove repeater item
            $(document).on('click', '.hng-remove-repeater-item', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $item = $button.closest('.hng-repeater-item');
                const $repeater = $item.closest('.hng-repeater');
                
                // Confirm removal
                if (confirm('Tem certeza que deseja remover este campo?')) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        self.updateRepeaterNumbers($repeater);
                        self.reindexRepeaterItems($repeater);
                    });
                }
            });
            
            // Make repeater items sortable
            $('.hng-repeater-items').sortable({
                handle: '.hng-repeater-handle',
                placeholder: 'ui-sortable-placeholder',
                helper: 'clone',
                cursor: 'move',
                tolerance: 'pointer',
                update: function(event, ui) {
                    const $repeater = ui.item.closest('.hng-repeater');
                    self.updateRepeaterNumbers($repeater);
                    self.reindexRepeaterItems($repeater);
                }
            });
        },

        /**
         * Update repeater item numbers
         */
        updateRepeaterNumbers: function($repeater) {
            $repeater.find('.hng-repeater-item').each(function(index) {
                $(this).find('.hng-repeater-title').text('Campo #' + (index + 1));
            });
        },

        /**
         * Reindex repeater items (update name attributes)
         */
        reindexRepeaterItems: function($repeater) {
            const fieldKey = $repeater.data('field');
            
            $repeater.find('.hng-repeater-item').each(function(index) {
                $(this).attr('data-index', index);
                
                // Update all input names within this item
                $(this).find('input, select, textarea').each(function() {
                    const $field = $(this);
                    const name = $field.attr('name');
                    
                    if (name) {
                        // Replace index in name attribute
                        const newName = name.replace(/\[(\d+)\]/, '[' + index + ']');
                        $field.attr('name', newName);
                    }
                });
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ProductTypeEditor.init();
    });

})(jQuery);
