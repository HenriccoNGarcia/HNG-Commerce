/**
 * HNG Commerce - User Customer Relationship JavaScript
 *
 * Gerencia a busca e seleção de clientes na página de edição de usuários.
 */

jQuery(document).ready(function($) {
    const searchInput = $('#hng_customer_search');
    const customerIdInput = $('#hng_customer_id');
    const suggestionsContainer = $('#hng-customer-suggestions');
    const indicator = $('<div id="hng-customer-selection-indicator" style="margin-top:8px; color:#1e7e34; display:none; font-size:12px; font-weight:600;"></div>');
    if ($('#hng-customer-field').length) {
        $('#hng-customer-field').append(indicator);
    }
    
    let debounceTimer;
    
    // Evento de digitação
    searchInput.on('keyup', function() {
        clearTimeout(debounceTimer);
        const searchTerm = $(this).val().trim();
        
        if (searchTerm.length < 2) {
            suggestionsContainer.hide();
            return;
        }
        
        debounceTimer = setTimeout(function() {
            searchCustomers(searchTerm);
        }, 300);
    });
    
    // Buscar clientes via AJAX
    function searchCustomers(searchTerm) {
        $.ajax({
            url: hngUserCustomer.ajaxurl,
            type: 'POST',
            data: {
                action: 'hng_search_customers',
                search: searchTerm
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    renderSuggestions(response.data);
                } else {
                    suggestionsContainer.html(
                        '<div style="padding: 10px; color: #999;">Nenhum cliente encontrado</div>'
                    );
                }
                suggestionsContainer.show();
            },
            error: function(xhr, status, error) {
                console.error('HNG: AJAX error:', {xhr, status, error});
            }
        });
    }
    
    // Renderizar sugestões
    function renderSuggestions(customers) {
        let html = '';
        
        customers.forEach(function(customer) {
            const name = (customer.customer_name || '').trim();
            const email = (customer.email || '').trim();
            
            if (!email) return; // Skip se não houver email
            
            // Se tiver nome completo (não vazio), mostrar como "Nome (email)"
            // Se não tiver nome, mostrar apenas o email
            const displayName = name && name !== '' ? name : email;
            const displayEmail = email;
            
            html += '<div class="hng-customer-suggestion" data-id="' + customer.id + '" data-email="' + escapeHtml(email) + '" data-name="' + escapeHtml(displayName) + '" style="' +
                'padding: 10px; ' +
                'border-bottom: 1px solid #eee; ' +
                'cursor: pointer; ' +
                'transition: background-color 0.2s;' +
                '"><strong>' + escapeHtml(displayName) + '</strong><br/>' +
                (name && name !== '' ? '<small style="color: #666;">' + escapeHtml(email) + '</small>' : '') +
                '</div>';
        });
        
        suggestionsContainer.html(html);
    }
    
    // Ao clicar em uma sugestão
    $(document).on('click', '.hng-customer-suggestion', function() {
        const customerId = $(this).data('id');
        const customerEmail = $(this).data('email');
        const customerName = $(this).data('name');
        
        customerIdInput.val(customerId);
        
        // Se tem nome, mostrar "email (nome)", caso contrário apenas o email
        if (customerName && customerName !== customerEmail) {
            searchInput.val(customerEmail + ' (' + customerName + ')');
        } else {
            searchInput.val(customerEmail);
        }
        
        suggestionsContainer.hide();
        updateIndicator(customerEmail, customerId);
    });
    
    // Fechar sugestões ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#hng-customer-field').length) {
            suggestionsContainer.hide();
        }
    });
    
    // Hover nas sugestões
    $(document).on('mouseenter', '.hng-customer-suggestion', function() {
        $(this).css('background-color', '#f5f5f5');
    }).on('mouseleave', '.hng-customer-suggestion', function() {
        $(this).css('background-color', 'transparent');
    });

    // Atualiza indicador visual de vínculo
    function updateIndicator(email, customerId) {
        if (!indicator.length) return;
        if (customerId) {
            indicator.text('Cliente selecionado: ' + email + ' (ID #' + customerId + ')');
            indicator.show();
        } else {
            indicator.hide();
        }
    }

    // Exibir indicador inicial se já houver valor salvo
    if (customerIdInput.val()) {
        const initialEmail = (searchInput.val() || '').split(' (')[0] || 'Cliente vinculado';
        updateIndicator(initialEmail, customerIdInput.val());
    }
    
    // Escapar HTML
    function escapeHtml(text) {
        if (!text) return ''; // Retornar string vazia se nulo
        
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
