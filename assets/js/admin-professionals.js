/**
 * HNG Commerce Professionals Admin
 * 
 * JavaScript for professionals management page
 */

jQuery(function($) {
    
    if (typeof hngProfessionalsPage === 'undefined') {
        return;
    }
    
    // Load professionals on page init
    loadProfessionals();
    
    // New professional button
    $('#hng-new-professional-btn').on('click', function() {
        clearProfessionalForm();
        $('#hng-new-professional-modal').fadeIn();
        $('#hng-modal-backdrop').fadeIn();
    });
    
    // Modal close buttons
    $('#hng-modal-backdrop, .hng-modal-close').on('click', function(e) {
        if ($(this).is('#hng-modal-backdrop') || $(this).hasClass('hng-modal-close')) {
            $('#hng-new-professional-modal').fadeOut();
            $('#hng-modal-backdrop').fadeOut();
        }
    });
    
    // Professional form submission
    $('#hng-new-professional-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('[type="submit"]');
        const originalText = $submitBtn.text();
        
        // Disable submit button
        $submitBtn.prop('disabled', true).text(hngProfessionalsPage.i18n.saving);
        
        // Get form data
        const formData = {
            action: 'hng_admin_save_professional',
            nonce: hngProfessionalsPage.nonce,
            id: $('#hng-prof-id').val(),
            name: $('#hng-prof-name').val(),
            email: $('#hng-prof-email').val(),
            phone: $('#hng-prof-phone').val(),
            wp_user_id: $('#hng-prof-wp-user').val(),
            notes: $('#hng-prof-notes').val(),
            active: $('#hng-prof-active').is(':checked') ? 1 : 0,
        };
        
        // Validate email
        if (!isValidEmail(formData.email)) {
            showAlert(hngProfessionalsPage.i18n.invalidEmail, 'error');
            $submitBtn.prop('disabled', false).text(originalText);
            return;
        }
        
        $.ajax({
            url: hngProfessionalsPage.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.data.message, 'success');
                    $('#hng-new-professional-modal').fadeOut();
                    $('#hng-modal-backdrop').fadeOut();
                    loadProfessionals();
                } else {
                    showAlert(response.data.message || hngProfessionalsPage.i18n.error, 'error');
                }
            },
            error: function() {
                showAlert(hngProfessionalsPage.i18n.error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Edit professional
    $(document).on('click', '.hng-edit-professional', function() {
        const professionalId = $(this).data('id');
        editProfessional(professionalId);
    });
    
    // Delete professional
    $(document).on('click', '.hng-delete-professional', function() {
        const professionalId = $(this).data('id');
        const professionalName = $(this).data('name');
        
        if (confirm(hngProfessionalsPage.i18n.deleteConfirm.replace('%s', professionalName))) {
            deleteProfessional(professionalId);
        }
    });
    
    /**
     * Load professionals into table
     */
    function loadProfessionals() {
        $.ajax({
            url: hngProfessionalsPage.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hng_admin_fetch_professionals',
                nonce: hngProfessionalsPage.nonce,
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderProfessionalsTable(response.data);
                } else {
                    showAlert(hngProfessionalsPage.i18n.error, 'error');
                }
            },
            error: function() {
                showAlert(hngProfessionalsPage.i18n.error, 'error');
            }
        });
    }
    
    /**
     * Render professionals table
     */
    function renderProfessionalsTable(professionals) {
        const $tbody = $('#hng-professionals-tbody');
        
        if (professionals.length === 0) {
            $tbody.html(
                '<tr><td colspan="7" style="text-align:center; padding:20px;">' + 
                hngProfessionalsPage.i18n.noProfessionals + 
                '</td></tr>'
            );
            return;
        }
        
        let html = '';
        professionals.forEach(prof => {
            const statusBadge = prof.active 
                ? '<span class="badge badge-success">' + hngProfessionalsPage.i18n.active + '</span>'
                : '<span class="badge badge-danger">' + hngProfessionalsPage.i18n.inactive + '</span>';
            
            html += `<tr>
                <td>${prof.id}</td>
                <td><strong>${prof.name}</strong></td>
                <td><a href="mailto:${prof.email}">${prof.email}</a></td>
                <td>${prof.wp_user_name || '—'}</td>
                <td>${prof.phone || '—'}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-primary hng-edit-professional" data-id="${prof.id}">
                        ${hngProfessionalsPage.i18n.edit}
                    </button>
                    <button class="btn btn-sm btn-danger hng-delete-professional" data-id="${prof.id}" data-name="${prof.name}">
                        ${hngProfessionalsPage.i18n.delete}
                    </button>
                </td>
            </tr>`;
        });
        
        $tbody.html(html);
    }
    
    /**
     * Edit professional - load data into form
     */
    function editProfessional(professionalId) {
        $.ajax({
            url: hngProfessionalsPage.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hng_admin_fetch_professionals',
                nonce: hngProfessionalsPage.nonce,
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const professional = response.data.find(p => p.id == professionalId);
                    if (professional) {
                        $('#hng-prof-id').val(professional.id);
                        $('#hng-prof-name').val(professional.name);
                        $('#hng-prof-email').val(professional.email);
                        $('#hng-prof-phone').val(professional.phone);
                        $('#hng-prof-wp-user').val(professional.wp_user_id);
                        $('#hng-prof-active').prop('checked', professional.active === 1);
                        $('#hng-prof-notes').val(professional.notes);
                        
                        // Update modal title
                        $('#hng-new-professional-modal .modal-header h2').text(hngProfessionalsPage.i18n.editProfessional);
                        
                        // Show modal
                        $('#hng-new-professional-modal').fadeIn();
                        $('#hng-modal-backdrop').fadeIn();
                    }
                }
            }
        });
    }
    
    /**
     * Delete professional
     */
    function deleteProfessional(professionalId) {
        $.ajax({
            url: hngProfessionalsPage.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hng_admin_delete_professional',
                nonce: hngProfessionalsPage.nonce,
                professional_id: professionalId,
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.data.message, 'success');
                    loadProfessionals();
                } else {
                    showAlert(response.data.message || hngProfessionalsPage.i18n.error, 'error');
                }
            },
            error: function() {
                showAlert(hngProfessionalsPage.i18n.error, 'error');
            }
        });
    }
    
    /**
     * Clear professional form
     */
    function clearProfessionalForm() {
        $('#hng-new-professional-form')[0].reset();
        $('#hng-prof-id').val('');
        $('#hng-new-professional-modal .modal-header h2').text(hngProfessionalsPage.i18n.newProfessional);
    }
    
    /**
     * Validate email
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    /**
     * Show alert message
     */
    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alert = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        
        const $alertContainer = $('#hng-alert-container');
        if ($alertContainer.length) {
            $alertContainer.html(alert);
        } else {
            $('body').prepend(alert);
        }
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
});
