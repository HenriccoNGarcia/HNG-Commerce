/**
 * HNG Admin Appointments Page Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        /**
         * Get status badge HTML
         */
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span class="badge" style="background:#ffc107;color:#000;padding:3px 8px;border-radius:3px;font-size:11px;">Pendente</span>',
                'confirmed': '<span class="badge" style="background:#28a745;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">Confirmado</span>',
                'completed': '<span class="badge" style="background:#17a2b8;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">Completo</span>',
                'cancelled': '<span class="badge" style="background:#dc3545;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">Cancelado</span>'
            };
            return badges[status] || status;
        }

        /**
         * Load appointments from server
         */
        function loadAppointments() {
            const status = $('#hng-appointments-status-filter').val();
            const dateFilter = $('#hng-appointments-date-filter').val();
            
            $('#hng-appointments-table tbody').html('<tr><td colspan="8" style="text-align:center;">Carregando...</td></tr>');
            
            $.post(hngAppointmentsPage.ajaxUrl, {
                action: 'hng_admin_fetch_appointments',
                nonce: hngAppointmentsPage.nonce,
                status: status,
                date_filter: dateFilter
            })
            .done(function(resp) {
                if (!resp.success) {
                    const errorMsg = (resp.data && resp.data.message) ? resp.data.message : hngAppointmentsPage.i18n.loadError;
                    $('#hng-appointments-table tbody').html('<tr><td colspan="8" style="text-align:center;color:#a00;">'+errorMsg+'</td></tr>');
                    return;
                }
                
                if (resp.data && resp.data.length > 0) {
                    let rows = '';
                    resp.data.forEach(function(appt) {
                        let actionButtons = '';
                        
                        if (appt.status === 'pending') {
                            actionButtons += '<button class="button button-small hng-appt-action" data-id="'+appt.id+'" data-action="confirmed" title="Confirmar">✓</button>';
                        }
                        if (appt.status === 'confirmed') {
                            actionButtons += '<button class="button button-small hng-appt-action" data-id="'+appt.id+'" data-action="completed" title="Marcar Completo">✓✓</button>';
                        }
                        if (appt.status !== 'cancelled' && appt.status !== 'completed') {
                            actionButtons += '<button class="button button-small hng-appt-action" data-id="'+appt.id+'" data-action="cancelled" title="Cancelar" style="color:#dc3545;">✕</button>';
                        }
                        actionButtons += '<button class="button button-small hng-appt-email" data-id="'+appt.id+'" title="Enviar E-mail">✉</button>';
                        
                        rows += '<tr>'+
                            '<td>'+appt.id+'</td>'+
                            '<td><strong>'+appt.customer_name+'</strong><br><small>'+appt.customer_email+'</small></td>'+
                            '<td>'+appt.product_name+'</td>'+
                            '<td>'+appt.date+'</td>'+
                            '<td>'+appt.time+'</td>'+
                            '<td>'+appt.duration+' min</td>'+
                            '<td>'+getStatusBadge(appt.status)+'</td>'+
                            '<td><div style="display:flex;gap:5px;flex-wrap:wrap;">'+actionButtons+'</div></td>'+
                            '</tr>';
                    });
                    $('#hng-appointments-table tbody').html(rows);
                } else {
                    $('#hng-appointments-table tbody').html('<tr><td colspan="8" style="text-align:center;">'+hngAppointmentsPage.i18n.noAppointments+'</td></tr>');
                }
            })
            .fail(function() {
                $('#hng-appointments-table tbody').html('<tr><td colspan="8" style="text-align:center;color:#a00;">'+hngAppointmentsPage.i18n.loadError+'</td></tr>');
            });
        }

        // Apply filter button
        $('#hng-appointments-apply-filter').on('click', loadAppointments);

        // Update appointment status
        $(document).on('click', '.hng-appt-action', function() {
            const id = $(this).data('id');
            const action = $(this).data('action');
            const confirmMsg = action === 'cancelled' ? 
                hngAppointmentsPage.i18n.confirmCancel : 
                hngAppointmentsPage.i18n.confirmStatus;
            
            if (!confirm(confirmMsg)) return;
            
            $.post(hngAppointmentsPage.ajaxUrl, {
                action: 'hng_admin_update_appointment_status',
                nonce: hngAppointmentsPage.nonce,
                appointment_id: id,
                new_status: action
            })
            .done(function(resp) {
                if (!resp.success) {
                    const errorMsg = (resp.data && resp.data.message) ? resp.data.message : hngAppointmentsPage.i18n.updateError;
                    alert(errorMsg);
                    return;
                }
                alert(hngAppointmentsPage.i18n.updateSuccess);
                loadAppointments();
            })
            .fail(function() {
                alert(hngAppointmentsPage.i18n.updateError);
            });
        });

        // Send appointment email
        $(document).on('click', '.hng-appt-email', function() {
            const id = $(this).data('id');
            
            if (!confirm(hngAppointmentsPage.i18n.confirmEmail)) return;
            
            $.post(hngAppointmentsPage.ajaxUrl, {
                action: 'hng_admin_send_appointment_email',
                nonce: hngAppointmentsPage.nonce,
                appointment_id: id
            })
            .done(function(resp) {
                if (!resp.success) {
                    const errorMsg = (resp.data && resp.data.message) ? resp.data.message : hngAppointmentsPage.i18n.emailError;
                    alert(errorMsg);
                    return;
                }
                alert(hngAppointmentsPage.i18n.emailSuccess);
            })
            .fail(function() {
                alert(hngAppointmentsPage.i18n.emailError);
            });
        });

        // Modal handling
        $('#hng-add-appointment-btn').on('click', function() {
            $('#hng-appointment-modal').fadeIn();
            $('#hng-modal-backdrop').fadeIn();
        });

        $('.hng-modal-close, [data-modal="hng-appointment-modal"]').on('click', function() {
            $('#hng-appointment-modal').fadeOut();
            $('#hng-modal-backdrop').fadeOut();
        });

        $('#hng-modal-backdrop').on('click', function() {
            $('#hng-appointment-modal').fadeOut();
            $(this).fadeOut();
        });

        // Create new appointment
        $('#hng-new-appointment-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'hng_admin_create_appointment',
                nonce: hngAppointmentsPage.nonce,
                product_id: $('#hng-appt-product-id').val(),
                customer_name: $('#hng-appt-customer-name').val(),
                customer_email: $('#hng-appt-customer-email').val(),
                appointment_date: $('#hng-appt-date').val(),
                appointment_time: $('#hng-appt-time').val(),
                duration: $('#hng-appt-duration').val(),
                status: $('#hng-appt-status').val(),
                professional_id: $('#hng-appt-professional-id').val(),
                notes: $('#hng-appt-notes').val()
            };
            
            $.post(hngAppointmentsPage.ajaxUrl, formData)
            .done(function(resp) {
                if (!resp.success) {
                    const errorMsg = (resp.data && resp.data.message) ? resp.data.message : 'Erro ao criar agendamento.';
                    alert(errorMsg);
                    return;
                }
                alert(resp.data.message || 'Agendamento criado com sucesso!');
                $('#hng-appointment-modal').fadeOut();
                $('#hng-modal-backdrop').fadeOut();
                $('#hng-new-appointment-form')[0].reset();
                loadAppointments();
            })
            .fail(function() {
                alert('Erro ao criar agendamento.');
            });
        });

        // Load on page ready
        loadAppointments();
    });

})(jQuery);

