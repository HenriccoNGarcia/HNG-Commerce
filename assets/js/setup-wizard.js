/**
 * HNG Commerce - Setup Wizard JavaScript
 */

(function($) {
    'use strict';

    const HNGSetupWizard = {
        
        init: function() {
            this.form = $('#wizardForm');
            this.currentStep = this.form.find('input[name="step"]').val();
            
            this.bindEvents();
            this.initMasks();
            this.togglePixConfig();
        },
        
        bindEvents: function() {
            const self = this;
            
            // Form submit (next step)
            this.form.on('submit', function(e) {
                e.preventDefault();
                self.saveStep();
            });
            
            // Skip wizard
            $('#skipWizard').on('click', function() {
                self.skipWizard();
            });
            
            // Complete wizard
            $('#completeWizard').on('click', function() {
                self.completeWizard();
            });
            
            // CEP search
            $('#searchCep').on('click', function() {
                self.searchCep($('#store_zipcode').val());
            });
            
            // Toggle PIX config when checked
            $('input[value="pix"]').on('change', function() {
                self.togglePixConfig();
            });
            
            // Toggle PIX installment options
            $('#pix_installment_enabled').on('change', function() {
                self.togglePixInstallment();
            });
            
            // Auto-fill origin zipcode from store zipcode
            $('#store_zipcode').on('blur', function() {
                const val = $(this).val();
                if (val && !$('#origin_zipcode').val()) {
                    // Will be auto-filled when advancing to shipping step
                }
            });
        },
        
        initMasks: function() {
            // CEP mask
            $('#store_zipcode, #origin_zipcode').on('input', function() {
                let val = $(this).val().replace(/\D/g, '');
                if (val.length > 5) {
                    val = val.substring(0, 5) + '-' + val.substring(5, 8);
                }
                $(this).val(val);
            });
            
            // Phone mask
            $('#store_phone').on('input', function() {
                let val = $(this).val().replace(/\D/g, '');
                if (val.length > 10) {
                    val = '(' + val.substring(0, 2) + ') ' + val.substring(2, 7) + '-' + val.substring(7, 11);
                } else if (val.length > 6) {
                    val = '(' + val.substring(0, 2) + ') ' + val.substring(2, 6) + '-' + val.substring(6, 10);
                } else if (val.length > 2) {
                    val = '(' + val.substring(0, 2) + ') ' + val.substring(2);
                } else if (val.length > 0) {
                    val = '(' + val;
                }
                $(this).val(val);
            });
            
            // CNPJ/CPF mask
            $('#store_cnpj').on('input', function() {
                let val = $(this).val().replace(/\D/g, '');
                if (val.length > 14) {
                    val = val.substring(0, 14);
                }
                if (val.length > 11) {
                    // CNPJ
                    val = val.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
                } else if (val.length > 9) {
                    // CPF
                    val = val.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
                }
                $(this).val(val);
            });
        },
        
        togglePixConfig: function() {
            const isChecked = $('input[value="pix"]').is(':checked');
            $('.pix-config').slideToggle(isChecked ? 'fast' : 0);
        },
        
        togglePixInstallment: function() {
            const isChecked = $('#pix_installment_enabled').is(':checked');
            if (isChecked) {
                $('.pix-installment-options').slideDown('fast');
            } else {
                $('.pix-installment-options').slideUp('fast');
            }
        },
        
        searchCep: function(cep) {
            cep = cep.replace(/\D/g, '');
            
            if (cep.length !== 8) {
                this.showToast('Digite um CEP válido com 8 dígitos', 'error');
                return;
            }
            
            const $btn = $('#searchCep');
            const originalText = $btn.text();
            $btn.text('...').prop('disabled', true);
            
            $.getJSON(`https://viacep.com.br/ws/${cep}/json/`, (data) => {
                if (data.erro) {
                    this.showToast('CEP não encontrado', 'error');
                } else {
                    $('#store_address').val(data.logradouro || '');
                    $('#store_district').val(data.bairro || '');
                    $('#store_city').val(data.localidade || '');
                    $('#store_state').val(data.uf || '');
                    $('#store_number').focus();
                    this.showToast('Endereço preenchido!', 'success');
                }
            }).fail(() => {
                this.showToast('Erro ao buscar CEP', 'error');
            }).always(() => {
                $btn.text(originalText).prop('disabled', false);
            });
        },
        
        saveStep: function() {
            const self = this;
            const $btn = $('#nextStep');
            const formData = this.form.serialize();
            
            // Validate required fields
            const requiredFields = this.form.find('[required]');
            let isValid = true;
            
            requiredFields.each(function() {
                const $field = $(this);
                const $panel = $field.closest('.hng-wizard-panel');
                
                // Only validate visible panel fields
                if ($panel.is(':visible') && !$field.val()) {
                    $field.css('border-color', '#ef4444');
                    isValid = false;
                } else {
                    $field.css('border-color', '');
                }
            });
            
            if (!isValid) {
                this.showToast('Preencha os campos obrigatórios', 'error');
                return;
            }
            
            $btn.addClass('loading');
            
            // Usar FormData para enviar corretamente os dados aninhados
            const ajaxData = formData + '&action=hng_wizard_save_step&nonce=' + hng_wizard.nonce;
            
            $.ajax({
                url: hng_wizard.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        // Navigate to next step
                        if (response.data && response.data.next_url) {
                            window.location.href = response.data.next_url;
                        }
                    } else {
                        self.showToast(response.data || 'Erro ao salvar', 'error');
                        $btn.removeClass('loading');
                    }
                },
                error: function() {
                    self.showToast('Erro de conexão', 'error');
                    $btn.removeClass('loading');
                }
            });
        },
        
        skipWizard: function() {
            if (!confirm('Deseja realmente pular a configuração? Você precisará configurar manualmente.')) {
                return;
            }
            
            $.ajax({
                url: hng_wizard.ajax_url,
                type: 'POST',
                data: {
                    action: 'hng_wizard_skip',
                    nonce: hng_wizard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url || hng_wizard.admin_url;
                    }
                }
            });
        },
        
        completeWizard: function() {
            const $btn = $('#completeWizard');
            $btn.addClass('loading');
            
            $.ajax({
                url: hng_wizard.ajax_url,
                type: 'POST',
                data: {
                    action: 'hng_wizard_complete',
                    nonce: hng_wizard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url || hng_wizard.admin_url;
                    } else {
                        $btn.removeClass('loading');
                    }
                },
                error: function() {
                    $btn.removeClass('loading');
                }
            });
        },
        
        parseFormData: function(formDataString) {
            const result = {};
            const pairs = formDataString.split('&');
            
            pairs.forEach(function(pair) {
                const parts = pair.split('=');
                const key = decodeURIComponent(parts[0]);
                const value = decodeURIComponent(parts[1] || '');
                
                // Handle nested notation (e.g., data[store_name] or data[gateways][])
                const match = key.match(/^(\w+)\[(\w+)\](\[\])?$/);
                if (match) {
                    const baseKey = match[1];      // e.g., "data"
                    const subKey = match[2];       // e.g., "store_name"
                    const isArray = match[3];      // e.g., "[]"
                    
                    if (!result[baseKey]) {
                        result[baseKey] = {};
                    }
                    
                    if (isArray) {
                        if (!result[baseKey][subKey]) {
                            result[baseKey][subKey] = [];
                        }
                        result[baseKey][subKey].push(value);
                    } else {
                        result[baseKey][subKey] = value;
                    }
                } else if (key.includes('[]')) {
                    // Handle simple arrays
                    const baseKey = key.replace('[]', '');
                    if (!result[baseKey]) {
                        result[baseKey] = [];
                    }
                    result[baseKey].push(value);
                } else {
                    result[key] = value;
                }
            });
            
            return result;
        },
        
        showToast: function(message, type = 'info') {
            // Remove existing toast
            $('.hng-toast').remove();
            
            const $toast = $('<div class="hng-toast"></div>')
                .addClass(type)
                .text(message)
                .appendTo('body');
            
            setTimeout(function() {
                $toast.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        HNGSetupWizard.init();
    });
    
})(jQuery);
