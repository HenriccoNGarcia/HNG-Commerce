/**
 * HNG Commerce - Roles Manager JavaScript
 */

jQuery(document).ready(function($) {
    let currentRoleSlug = null;
    let currentUserId = null;
    let currentMode = 'roles';

    // Alternar modo (funções ou usuários)
    $(document).on('change', 'input[name="hng-edit-mode"]', function() {
        setMode($(this).val());
    });

    function setMode(mode) {
        currentMode = mode;
        if (mode === 'roles') {
            $('.hng-roles-section').show();
            $('.hng-users-section').hide();
            $('.hng-role-editor-section').show();
            $('.hng-user-editor-section').hide();
            resetUserEditor();
            $('#hng-role-empty-state').show();
            $('#hng-role-editor').hide();
        } else {
            $('.hng-roles-section').hide();
            $('.hng-users-section').show();
            $('.hng-role-editor-section').hide();
            $('.hng-user-editor-section').show();
            resetUserEditor();
            fetchUsers();
        }
    }

    // Adicionar nova função
    $(document).on('click', '.hng-add-role-btn', function() {
        if (currentMode !== 'roles') {
            return;
        }
        currentRoleSlug = null;
        $('#hng-role-name').val('');
        $('#hng-role-slug').val('').prop('disabled', false);
        $('#hng-capabilities-list').empty();
        $('#hng-role-editor').show();
        $('#hng-role-empty-state').hide();
        $('.hng-role-item').removeClass('active');
    });

    // Editar função
    $(document).on('click', '.hng-edit-role-btn', function(e) {
        if (currentMode !== 'roles') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        const roleSlug = $(this).data('role');
        currentRoleSlug = roleSlug;

        $.ajax({
            url: hngRolesManager.ajaxurl,
            type: 'POST',
            data: {
                action: 'hng_get_role_capabilities',
                role_slug: roleSlug,
                nonce: hngRolesManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    $('#hng-role-name').val(data.role_name);
                    $('#hng-role-slug').val(data.role_slug).prop('disabled', data.is_protected);
                    renderCapabilities(data.capabilities, data.role_capabilities);
                    $('#hng-role-editor').show();
                    $('#hng-role-empty-state').hide();
                    $('.hng-role-item').removeClass('active');
                    $('.hng-role-item[data-role="' + roleSlug + '"]').addClass('active');
                } else {
                    showNotice(response.data.message || hngRolesManager.i18n.error, 'error');
                }
            },
            error: function() {
                showNotice(hngRolesManager.i18n.error, 'error');
            }
        });
    });

    // Deletar função
    $(document).on('click', '.hng-delete-role-btn', function(e) {
        if (currentMode !== 'roles') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        const roleSlug = $(this).data('role');

        if (!confirm(hngRolesManager.i18n.confirmDelete)) {
            return;
        }

        $.ajax({
            url: hngRolesManager.ajaxurl,
            type: 'POST',
            data: {
                action: 'hng_delete_role',
                role_slug: roleSlug,
                nonce: hngRolesManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.hng-role-item[data-role="' + roleSlug + '"]').fadeOut(function() {
                        $(this).remove();
                    });

                    $('#hng-role-editor').hide();
                    $('#hng-role-empty-state').show();
                    currentRoleSlug = null;

                    showNotice(hngRolesManager.i18n.deleted, 'success');
                } else {
                    showNotice(response.data.message || hngRolesManager.i18n.error, 'error');
                }
            },
            error: function() {
                showNotice(hngRolesManager.i18n.error, 'error');
            }
        });
    });

    // Cancelar edição de função
    $(document).on('click', '.hng-cancel-edit-btn', function() {
        $('#hng-role-editor').hide();
        $('#hng-role-empty-state').show();
        $('.hng-role-item').removeClass('active');
        currentRoleSlug = null;
    });

    // Salvar função
    $(document).on('click', '.hng-save-role-btn', function() {
        if (currentMode !== 'roles') {
            return;
        }

        const roleName = $('#hng-role-name').val().trim();
        const roleSlug = $('#hng-role-slug').val().trim();
        const capabilities = [];

        $('#hng-capabilities-list input[type="checkbox"]:checked').each(function() {
            capabilities.push($(this).val());
        });

        if (!roleName) {
            showNotice('Nome da função é obrigatório.', 'error');
            return;
        }

        if (!roleSlug) {
            showNotice('Identificador (Slug) é obrigatório.', 'error');
            return;
        }

        if (!/^[a-z0-9_]+$/.test(roleSlug)) {
            showNotice('Identificador inválido. Use apenas letras, números e underscores.', 'error');
            return;
        }

        if (capabilities.length === 0) {
            showNotice('Selecione pelo menos uma capacidade.', 'error');
            return;
        }

        $.ajax({
            url: hngRolesManager.ajaxurl,
            type: 'POST',
            data: {
                action: 'hng_save_role',
                role_slug: roleSlug,
                role_name: roleName,
                capabilities: capabilities,
                nonce: hngRolesManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (!currentRoleSlug) {
                        const roleItem = $(
                            '<div class="hng-role-item" data-role="' + response.data.role_slug + '">' +
                            '<div class="hng-role-header">' +
                            '<span class="hng-role-name">' + escapeHtml(roleName) + '</span>' +
                            '</div>' +
                            '<div class="hng-role-actions">' +
                            '<button type="button" class="button button-small hng-edit-role-btn" data-role="' + response.data.role_slug + '">' +
                            '<span class="dashicons dashicons-edit"></span> Editar' +
                            '</button>' +
                            '<button type="button" class="button button-small button-link-delete hng-delete-role-btn" data-role="' + response.data.role_slug + '">' +
                            '<span class="dashicons dashicons-trash"></span> Remover' +
                            '</button>' +
                            '</div>' +
                            '</div>'
                        );
                        $('.hng-roles-list').append(roleItem);
                    }

                    showNotice(hngRolesManager.i18n.saved, 'success');

                    setTimeout(function() {
                        $('#hng-role-editor').hide();
                        $('#hng-role-empty-state').show();
                        $('.hng-role-item').removeClass('active');
                        currentRoleSlug = null;
                    }, 1000);
                } else {
                    showNotice(response.data.message || hngRolesManager.i18n.error, 'error');
                }
            },
            error: function() {
                showNotice(hngRolesManager.i18n.error, 'error');
            }
        });
    });

    // Buscar usuários
    $(document).on('click', '.hng-user-search-btn', function() {
        if (currentMode === 'users') {
            fetchUsers();
        }
    });

    // Selecionar usuário
    $(document).on('click', '.hng-user-item', function(e) {
        if (currentMode !== 'users') {
            return;
        }
        e.preventDefault();

        const userId = $(this).data('user-id');
        currentUserId = userId;

        $('.hng-user-item').removeClass('active');
        $(this).addClass('active');

        loadUserRoles(userId);
    });

    // Cancelar edição de usuário
    $(document).on('click', '.hng-cancel-user-btn', function() {
        resetUserEditor();
        $('.hng-user-item').removeClass('active');
        currentUserId = null;
        $('#hng-user-empty-state').show();
    });

    // Salvar capacidades do usuário
    $(document).on('click', '.hng-save-user-roles-btn', function() {
        if (!currentUserId) {
            showNotice(hngRolesManager.i18n.selectUser, 'error');
            return;
        }

        const capabilities = [];
        $('#hng-user-capabilities-list input[type="checkbox"]:checked').each(function() {
            capabilities.push($(this).val());
        });

        $.ajax({
            url: hngRolesManager.ajaxurl,
            type: 'POST',
            data: {
                action: 'hng_save_user_roles',
                user_id: currentUserId,
                capabilities: capabilities,
                nonce: hngRolesManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(hngRolesManager.i18n.userSaved, 'success');
                } else {
                    showNotice(response.data.message || hngRolesManager.i18n.error, 'error');
                }
            },
            error: function() {
                showNotice(hngRolesManager.i18n.error, 'error');
            }
        });
    });

    function renderCapabilities(allCapabilities, roleCapabilities) {
        const container = $('#hng-capabilities-list');
        container.empty();

        const capabilities = Object.keys(allCapabilities).sort();

        capabilities.forEach(function(cap) {
            const label = allCapabilities[cap];
            const isChecked = roleCapabilities.includes(cap);

            const html =
                '<div class="hng-capability-item">' +
                '<input type="checkbox" id="cap-' + escapeAttr(cap) + '" value="' + escapeAttr(cap) + '"' + (isChecked ? ' checked' : '') + ' />' +
                '<label for="cap-' + escapeAttr(cap) + '">' + escapeHtml(label) + ' <code style="font-size: 10px; color: #999;">(' + escapeHtml(cap) + ')</code></label>' +
                '</div>';

            container.append(html);
        });
    }

    function fetchUsers() {
        const name = $('#hng-user-filter-name').val().trim();
        const email = $('#hng-user-filter-email').val().trim();

        $('.hng-users-empty-state').hide();
        const list = $('.hng-users-list');
        list.empty().append('<div class="hng-loading">Carregando usuários...</div>');

        $.ajax({
            url: hngRolesManager.ajaxurl,
            type: 'POST',
            data: {
                action: 'hng_search_users',
                name: name,
                email: email,
                nonce: hngRolesManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderUsers(response.data.users || []);
                } else {
                    list.empty();
                    showNotice(response.data.message || hngRolesManager.i18n.error, 'error');
                }
            },
            error: function() {
                list.empty();
                showNotice(hngRolesManager.i18n.error, 'error');
            }
        });
    }

    function renderUsers(users) {
        const list = $('.hng-users-list');
        list.empty();

        if (!users.length) {
            $('.hng-users-empty-state').show();
            return;
        }

        $('.hng-users-empty-state').hide();

        users.forEach(function(user) {
            const rolesText = user.roles && user.roles.length ? user.roles.join(', ') : '—';
            const item = $(
                '<div class="hng-user-item" data-user-id="' + escapeAttr(user.id) + '">' +
                    '<div class="hng-user-main">' +
                        '<div class="hng-user-title">' + escapeHtml(user.name) + '</div>' +
                        '<div class="hng-user-email">' + escapeHtml(user.email || '') + '</div>' +
                    '</div>' +
                    '<div class="hng-user-meta">' + escapeHtml(rolesText) + '</div>' +
                '</div>'
            );
            list.append(item);
        });
    }

    function loadUserRoles(userId) {
        $('#hng-user-editor').hide();
        $('#hng-user-empty-state').show();

        $.ajax({
            url: hngRolesManager.ajaxurl,
            type: 'POST',
            data: {
                action: 'hng_get_user_roles',
                user_id: userId,
                nonce: hngRolesManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    $('#hng-user-name').text(data.user.name);
                    $('#hng-user-email').text(data.user.email);
                    renderUserCapabilities(data.available_capabilities, data.user_capabilities);
                    $('#hng-user-empty-state').hide();
                    $('#hng-user-editor').show();
                    showNotice(hngRolesManager.i18n.userLoaded, 'info');
                } else {
                    showNotice(response.data.message || hngRolesManager.i18n.error, 'error');
                }
            },
            error: function() {
                showNotice(hngRolesManager.i18n.error, 'error');
            }
        });
    }

    function renderUserCapabilities(availableCaps, userCaps) {
        const container = $('#hng-user-capabilities-list');
        container.empty();

        Object.keys(availableCaps).forEach(function(cap) {
            const label = availableCaps[cap];
            const isChecked = (userCaps || []).includes(cap);

            const html =
                '<div class="hng-capability-item">' +
                    '<input type="checkbox" id="user-cap-' + escapeAttr(cap) + '" value="' + escapeAttr(cap) + '"' + (isChecked ? ' checked' : '') + ' />' +
                    '<label for="user-cap-' + escapeAttr(cap) + '">' + escapeHtml(label) + ' <code style="font-size: 10px; color: #999;">(' + escapeHtml(cap) + ')</code></label>' +
                '</div>';

            container.append(html);
        });
    }

    function resetUserEditor() {
        $('#hng-user-editor').hide();
        $('#hng-user-name').text('');
        $('#hng-user-email').text('');
        $('#hng-user-capabilities-list').empty();
        $('#hng-user-empty-state').show();
    }

    function showNotice(message, type) {
        const cssClass = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'error');
        const icon = type === 'success' ? '✓' : (type === 'info' ? 'ℹ' : '✕');

        const notice = $('<div class="hng-notice ' + cssClass + '" style="animation: slideDown 0.3s ease;">' +
            '<strong>' + icon + '</strong> ' + escapeHtml(message) +
            '</div>');

        $('.hng-role-editor-panel').prepend(notice);

        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) {
            return map[m];
        });
    }

    function escapeAttr(text) {
        return String(text).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Inicializa modo padrão
    setMode('roles');
});
