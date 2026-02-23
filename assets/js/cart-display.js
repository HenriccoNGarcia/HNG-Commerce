/**
 * HNG Commerce Cart Display
 * Gerencia interações para todos os tipos de layout do carrinho
 * 
 * Suporte para: sidebar, drawer, modal, sticky, popup
 */

(function() {
    'use strict';

    // Verificar se a config existe
    if (typeof hngCartDisplay === 'undefined') {
        console.warn('HNG Cart Display: Config não encontrada');
        return;
    }

    const HNGCartDisplay = {
        // Config do plugin
        config: hngCartDisplay,
        
        // Estado
        state: {
            isOpen: false,
            layoutType: 'sidebar',
            cartItems: [],
            cartTotal: 0,
            overlayListenerAttached: false
        },

        /**
         * Inicializa o sistema de carrinho
         */
        init() {
            this.state.layoutType = this.config.type || 'sidebar';
            this.attachEventListeners();
            this.updateCart();
        },

        /**
         * Anexa ouvintes de eventos
         */
        attachEventListeners() {
            // Abrir carrinho
            document.addEventListener('click', (e) => {
                // Ignorar se clicou em botão de fechar
                if (e.target.closest('.hng-cart-close, .hng-cart-close-modal')) {
                    return;
                }
                
                if (e.target.closest('.hng-cart-trigger, .hng-cart-sticky-button, .hng-cart-floating-icon')) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleCart();
                }
            });

            // Fechar carrinho
            document.addEventListener('click', (e) => {
                const closeBtn = e.target.closest('.hng-cart-close, .hng-cart-close-modal');
                
                if (closeBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Verificar se é do mini carrinho sticky
                    const stickyExpanded = closeBtn.closest('.hng-cart-sticky-expanded');
                    if (stickyExpanded) {
                        // Fechar o sticky expanded via closeSticky()
                        this.state.isOpen = false;
                        this.closeSticky();
                    } else {
                        // Fechar carrinho normal
                        this.closeCart();
                    }
                    return;
                }
                
                // Fechar modal ao clicar FORA do conteúdo (no fundo escuro)
                const modal = e.target.closest('.hng-cart-modal');
                const modalContent = e.target.closest('.hng-cart-modal-content');
                
                // Se clicou no modal mas NÃO no conteúdo, fecha
                if (modal && !modalContent && e.target.classList.contains('hng-cart-modal')) {
                    e.preventDefault();
                    this.closeCart();
                    return;
                }
                
                // Fechar drawer ao clicar no overlay (fundo escuro)
                const drawer = e.target.closest('.hng-cart-drawer');
                const drawerContent = e.target.closest('.hng-cart-drawer-header, .hng-cart-drawer-content, .hng-cart-drawer-footer');
                
                // Se clicou no drawer overlay (pseudo-element)
                if (drawer && drawer.classList.contains('active') && !drawerContent) {
                    // Verifica se clicou na área do overlay (fora do drawer visível)
                    const rect = drawer.getBoundingClientRect();
                    if (e.clientY < rect.top) {
                        e.preventDefault();
                        this.closeCart();
                        return;
                    }
                }

                // Fechar sidebar ao clicar no overlay (pseudo-element ::before)
                const sidebar = e.target.closest('.hng-cart-sidebar');
                const sidebarContent = e.target.closest('.hng-cart-sidebar-header, .hng-cart-sidebar-content, .hng-cart-sidebar-footer');
                if (sidebar && sidebar.classList.contains('active') && !sidebarContent) {
                    e.preventDefault();
                    this.closeCart();
                    return;
                }
            });

            // Remover item do carrinho
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-remove-cart]')) {
                    e.preventDefault();
                    const productId = e.target.closest('[data-remove-cart]').dataset.removeCart;
                    this.removeFromCart(productId);
                }
            });

            // Atualizar quantidade
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-qty-minus], [data-qty-plus]')) {
                    e.preventDefault();
                    const btn = e.target.closest('[data-qty-minus], [data-qty-plus]');
                    const itemId = btn.dataset.itemId;
                    const operation = btn.dataset.qtyMinus ? 'minus' : 'plus';
                    this.updateQuantity(itemId, operation);
                }
            });

            // Checkout
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-checkout-btn]')) {
                    e.preventDefault();
                    this.checkout();
                }
            });

            // Fechar drawer ao pressionar ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeCart();
                }
            });

            // Observar mudanças no carrinho (update via AJAX)
            if (typeof jQuery !== 'undefined') {
                jQuery(document).on('added_to_cart removed_from_cart', () => {
                    this.updateCart();
                });
            }
        },

        /**
         * Alterna abrir/fechar o carrinho
         */
        toggleCart() {
            if (this.state.isOpen) {
                this.closeCart();
            } else {
                this.openCart();
            }
        },

        /**
         * Abre o carrinho (layout específico)
         */
        openCart() {
            switch (this.state.layoutType) {
                case 'sidebar':
                    this.openSidebar();
                    break;
                case 'drawer':
                    this.openDrawer();
                    break;
                case 'modal':
                    this.openModal();
                    break;
                case 'popup':
                    this.openPopup();
                    break;
                case 'sticky':
                    this.openSticky();
                    break;
            }
            this.state.isOpen = true;
        },

        /**
         * Abre o sidebar
         */
        openSidebar() {
            const sidebar = document.querySelector('.hng-cart-sidebar');
            const overlay = document.querySelector('.hng-cart-overlay');
            
            if (sidebar) {
                sidebar.classList.add('active');
                if (overlay) {
                    overlay.classList.add('active');
                    // Adiciona listener apenas uma vez
                    if (!this.state.overlayListenerAttached) {
                        overlay.addEventListener('click', () => this.closeCart());
                        this.state.overlayListenerAttached = true;
                    }
                }
            }

            // Animação de entrada
            this.animateSlideIn(sidebar);
        },

        /**
         * Abre o drawer
         */
        openDrawer() {
            const drawer = document.querySelector('.hng-cart-drawer');
            if (drawer) {
                drawer.classList.add('active');
                this.animateSlideUp(drawer);
            }
        },

        /**
         * Abre o modal
         */
        openModal() {
            const modal = document.querySelector('.hng-cart-modal');
            if (modal) {
                modal.classList.add('active');
            }
        },

        /**
         * Abre o popup flutuante
         */
        openPopup() {
            const popup = document.querySelector('.hng-cart-popup');
            if (popup) {
                popup.classList.add('active');
            }
        },

        /**
         * Abre o sticky badge expandido
         */
        openSticky() {
            const sticky = document.querySelector('.hng-cart-sticky');
            if (sticky) {
                sticky.classList.remove('force-close');
                sticky.classList.add('active');
            }
        },

        /**
         * Fecha o carrinho
         */
        closeCart() {
            switch (this.state.layoutType) {
                case 'sidebar':
                    this.closeSidebar();
                    break;
                case 'drawer':
                    this.closeDrawer();
                    break;
                case 'modal':
                    this.closeModal();
                    break;
                case 'popup':
                    this.closePopup();
                    break;
                case 'sticky':
                    this.closeSticky();
                    break;
            }
            this.state.isOpen = false;
        },

        /**
         * Fecha o sidebar
         */
        closeSidebar() {
            const sidebar = document.querySelector('.hng-cart-sidebar');
            const overlay = document.querySelector('.hng-cart-overlay');
            
            if (sidebar) {
                sidebar.classList.remove('active');
            }
            if (overlay) {
                overlay.classList.remove('active');
            }
        },

        /**
         * Fecha o drawer
         */
        closeDrawer() {
            const drawer = document.querySelector('.hng-cart-drawer');
            if (drawer) {
                drawer.classList.remove('active');
            }
        },

        /**
         * Fecha o modal
         */
        closeModal() {
            const modal = document.querySelector('.hng-cart-modal');
            if (modal) {
                modal.classList.remove('active');
            }
        },

        /**
         * Fecha o popup
         */
        closePopup() {
            const popup = document.querySelector('.hng-cart-popup');
            if (popup) {
                popup.classList.remove('active');
            }
        },

        /**
         * Fecha o sticky badge expandido
         */
        closeSticky() {
            const sticky = document.querySelector('.hng-cart-sticky');
            if (sticky) {
                sticky.classList.remove('active');
                sticky.classList.add('force-close');
                setTimeout(() => {
                    sticky.classList.remove('force-close');
                }, 300);
            }
        },

        /**
         * Anima entrada do slideshow (sidebar)
         */
        animateSlideIn(element) {
            if (!element) return;
            element.style.animation = 'slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        },

        /**
         * Anima entrada do drawer
         */
        animateSlideUp(element) {
            if (!element) return;
            element.style.animation = 'slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        },

        /**
         * Atualiza os dados do carrinho
         */
        updateCart() {
            // Buscar dados do carrinho via AJAX se disponível
            if (!this.config.ajaxUrl || !this.config.nonce || typeof jQuery === 'undefined') {
                return;
            }
            
            jQuery.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hng_get_cart_data',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.state.cartItems = response.data.items || [];
                        this.state.cartTotal = response.data.total || 0;
                        this.renderCart();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('HNG Cart: Erro ao atualizar carrinho', error);
                }
            });
        },

        /**
         * Renderiza o conteúdo do carrinho
         */
        renderCart() {
            const isEmpty = this.state.cartItems.length === 0;
            
            // Atualizar badge
            this.updateBadge();

            // Renderizar items
            if (!isEmpty) {
                this.renderCartItems();
            } else {
                this.renderEmptyCart();
            }

            // Atualizar totais
            this.updateTotals();
        },

        /**
         * Atualiza o badge com quantidade de items
         */
        updateBadge() {
            const badge = document.querySelector('.hng-cart-badge, .hng-cart-sticky-badge');
            if (badge) {
                const count = this.state.cartItems.reduce((total, item) => total + item.quantity, 0);
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
        },

        /**
         * Renderiza items do carrinho
         */
        renderCartItems() {
            // Diferentes seletores para diferentes layouts
            const layouts = {
                sidebar: '.hng-cart-sidebar-content',
                drawer: '.hng-cart-drawer-content',
                modal: '.hng-cart-modal-body',
                popup: '.hng-cart-popup-body',
                sticky: '.hng-mini-cart-content'
            };

            const container = document.querySelector(layouts[this.state.layoutType]);
            if (!container) return;

            let html = '';
            this.state.cartItems.forEach(item => {
                html += this.renderCartItem(item);
            });

            container.innerHTML = html;
        },

        /**
         * Template para um item do carrinho
         */
        renderCartItem(item) {
            return `
                <div class="hng-cart-item" data-product-id="${item.id}">
                    <div class="hng-cart-item-image">
                        <img src="${item.image}" alt="${item.name}">
                    </div>
                    <div class="hng-cart-item-detail">
                        <div class="hng-cart-item-name">${item.name}</div>
                        <div class="hng-cart-item-price">R$ ${item.price}</div>
                        <div class="hng-cart-item-qty">
                            <button data-qty-minus data-item-id="${item.id}" class="qty-btn">−</button>
                            <span>${item.quantity}</span>
                            <button data-qty-plus data-item-id="${item.id}" class="qty-btn">+</button>
                        </div>
                        <button data-remove-cart="${item.id}" class="hng-remove-item">Remover</button>
                    </div>
                </div>
            `;
        },

        /**
         * Renderiza carrinho vazio
         */
        renderEmptyCart() {
            const layouts = {
                sidebar: '.hng-cart-sidebar-content',
                drawer: '.hng-cart-drawer-content',
                modal: '.hng-cart-modal-body',
                popup: '.hng-cart-popup-body',
                sticky: '.hng-mini-cart-content'
            };

            const container = document.querySelector(layouts[this.state.layoutType]);
            if (container) {
                container.innerHTML = `
                    <div class="hng-cart-empty">
                        <div class="hng-cart-empty-icon">🛒</div>
                        <p>Seu carrinho está vazio</p>
                    </div>
                `;
            }
        },

        /**
         * Atualiza totais do carrinho
         */
        updateTotals() {
            // Implementar lógica de cálculo de totais, impostos, frete, etc.
            // Por enquanto, apenas atualizar o valor total
            const totalsContainers = document.querySelectorAll('.hng-cart-total-amount, .hng-modal-summary-row.total, .hng-drawer-total-item.total');
            totalsContainers.forEach(el => {
                el.textContent = `R$ ${this.state.cartTotal.toFixed(2)}`;
            });
        },

        /**
         * Remove item do carrinho
         */
        removeFromCart(productId) {
            if (!this.config.ajaxUrl || typeof jQuery === 'undefined') {
                console.warn('HNG Cart: AJAX não disponível');
                return;
            }
            
            jQuery.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hng_remove_from_cart',
                    product_id: productId,
                    nonce: this.config.nonce
                },
                success: () => {
                    this.updateCart();
                },
                error: (xhr, status, error) => {
                    console.error('HNG Cart: Erro ao remover item', error);
                }
            });
        },

        /**
         * Atualiza quantidade de um item
         */
        updateQuantity(itemId, operation) {
            if (!this.config.ajaxUrl || typeof jQuery === 'undefined') {
                console.warn('HNG Cart: AJAX não disponível');
                return;
            }
            
            jQuery.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hng_update_cart_qty',
                    item_id: itemId,
                    operation: operation,
                    nonce: this.config.nonce
                },
                success: () => {
                    this.updateCart();
                },
                error: (xhr, status, error) => {
                    console.error('HNG Cart: Erro ao atualizar quantidade', error);
                }
            });
        },

        /**
         * Vai para o checkout
         */
        checkout() {
            const checkoutUrl = this.config.checkoutUrl || '/checkout';
            window.location.href = checkoutUrl;
        },

        /**
         * Inicializa calculadora de frete
         */
        initShippingCalculator() {
            // Calcular frete ao clicar no botão
            document.addEventListener('click', (e) => {
                if (e.target.closest('.hng-calc-shipping-btn')) {
                    e.preventDefault();
                    this.calculateShipping();
                }
            });

            // Calcular frete ao pressionar Enter no campo de CEP
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && e.target.matches('.hng-cart-cep-input')) {
                    e.preventDefault();
                    this.calculateShipping();
                }
            });

            // Máscara de CEP
            document.addEventListener('input', (e) => {
                if (e.target.matches('.hng-cart-cep-input')) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 5) {
                        value = value.substring(0, 5) + '-' + value.substring(5, 8);
                    }
                    e.target.value = value;
                }
            });

            // Selecionar opção de frete
            document.addEventListener('change', (e) => {
                if (e.target.matches('input[name="hng_shipping_rate"]')) {
                    this.selectShippingRate(e.target.value);
                }
            });
        },

        /**
         * Calcula frete via AJAX
         */
        calculateShipping() {
            const cepInput = document.querySelector('.hng-cart-cep-input');
            const btn = document.querySelector('.hng-calc-shipping-btn');
            const optionsContainer = document.querySelector('.hng-shipping-options');
            const errorContainer = document.querySelector('.hng-shipping-error');
            const messageContainer = document.querySelector('.hng-shipping-message');
            const i18n = this.config.i18n || {};

            if (!cepInput) return;

            const cep = cepInput.value.replace(/\D/g, '');

            // Validar CEP
            if (cep.length !== 8) {
                this.showShippingError(i18n.invalidCep || 'CEP inválido');
                return;
            }

            // Estado de loading
            if (btn) {
                btn.disabled = true;
                btn.querySelector('.btn-text').style.display = 'none';
                btn.querySelector('.btn-loading').style.display = 'inline-flex';
            }

            // Esconder mensagens anteriores
            if (errorContainer) {
                errorContainer.style.display = 'none';
            }
            if (messageContainer) {
                messageContainer.style.display = 'none';
            }

            // Fazer requisição AJAX
            jQuery.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hng_calculate_shipping',
                    postcode: cep,
                    nonce: this.config.shippingNonce || this.config.nonce
                },
                success: (response) => {
                    // Aceitar tanto 'methods' (retorno do AJAX) quanto 'rates' (fallback)
                    const rates = response.data?.methods || response.data?.rates || [];
                    if (response.success && rates.length > 0) {
                        this.renderShippingOptions(rates);
                        // Auto-selecionar a primeira opção se nenhuma estiver selecionada
                        const firstRate = rates[0];
                        this.selectShippingRate(firstRate.id);
                    } else {
                        this.showShippingError(response.data?.message || i18n.noShipping || 'Nenhuma opção de frete disponível');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('HNG Cart: Erro ao calcular frete', error);
                    this.showShippingError('Erro ao calcular frete. Tente novamente.');
                },
                complete: () => {
                    // Restaurar estado do botão
                    if (btn) {
                        btn.disabled = false;
                        btn.querySelector('.btn-text').style.display = 'inline';
                        btn.querySelector('.btn-loading').style.display = 'none';
                    }
                }
            });
        },

        /**
         * Renderiza opções de frete
         */
        renderShippingOptions(rates) {
            const container = document.querySelector('.hng-shipping-options');
            const i18n = this.config.i18n || {};

            if (!container) return;

            // Limpar container
            container.innerHTML = '';

            // Se não há opções
            if (!rates || rates.length === 0) {
                this.showShippingMessage(i18n.noShipping || 'Nenhuma opção de frete disponível para este CEP.');
                return;
            }

            // Renderizar cada opção
            rates.forEach((rate, index) => {
                const label = document.createElement('label');
                label.className = 'hng-shipping-option';
                if (index === 0) label.classList.add('selected');

                const price = parseFloat(rate.cost || 0);
                const priceFormatted = price > 0 
                    ? this.formatCurrency(price) 
                    : (i18n.freeShipping || 'Grátis');

                const deliveryTime = rate.delivery_time || rate.prazo || '';
                const rateName = rate.label || rate.service_name || 'Frete';

                label.innerHTML = `
                    <input type="radio" name="hng_shipping_rate" value="${rate.id || ''}" ${index === 0 ? 'checked' : ''}>
                    <span class="shipping-info">
                        <span class="shipping-label">${rateName}</span>
                        ${deliveryTime ? `<span class="shipping-time">${deliveryTime}</span>` : ''}
                    </span>
                    <span class="shipping-price">${priceFormatted}</span>
                `;

                container.appendChild(label);
            });

            // Mostrar container
            container.style.display = 'flex';
        },

        /**
         * Mostra mensagem informativa de frete
         */
        showShippingMessage(message) {
            const container = document.querySelector('.hng-shipping-message');
            const optionsContainer = document.querySelector('.hng-shipping-options');
            const errorContainer = document.querySelector('.hng-shipping-error');

            if (errorContainer) {
                errorContainer.style.display = 'none';
            }

            if (container) {
                container.textContent = message;
                container.style.display = 'block';
            }

            if (optionsContainer) {
                optionsContainer.style.display = 'none';
            }
        },

        /**
         * Seleciona uma opção de frete
         */
        selectShippingRate(rateId) {
            // Atualizar visual
            document.querySelectorAll('.hng-shipping-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.querySelector(`input[value="${rateId}"]`)) {
                    opt.classList.add('selected');
                }
            });

            // Enviar seleção via AJAX
            jQuery.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hng_update_cart_shipping',
                    method_id: rateId,
                    nonce: this.config.updateShippingNonce || this.config.shippingNonce || this.config.nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        // Atualizar valores de frete e total na UI
                        const shippingTotal = response.data.shipping_total || '0';
                        const cartTotal = response.data.cart_total || '0';
                        this.updateShippingDisplayFormatted(shippingTotal, cartTotal);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('HNG Cart: Erro ao selecionar frete', error);
                }
            });
        },

        /**
         * Atualiza display de frete e total (valores já formatados)
         */
        updateShippingDisplayFormatted(shippingFormatted, totalFormatted) {
            const shippingEl = document.querySelector('.hng-shipping-value');
            const totalEl = document.querySelector('.hng-total-value');

            if (shippingEl) {
                shippingEl.textContent = shippingFormatted;
            }
            if (totalEl) {
                totalEl.textContent = totalFormatted;
            }
        },

        /**
         * Mostra erro de frete
         */
        showShippingError(message) {
            const container = document.querySelector('.hng-shipping-error');
            const optionsContainer = document.querySelector('.hng-shipping-options');
            const messageContainer = document.querySelector('.hng-shipping-message');

            if (messageContainer) {
                messageContainer.style.display = 'none';
            }

            if (container) {
                container.textContent = message;
                container.style.display = 'block';
            }

            if (optionsContainer) {
                optionsContainer.style.display = 'none';
            }
        },

        /**
         * Formata valor como moeda BRL
         */
        formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        }
    };

    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            HNGCartDisplay.init();
            HNGCartDisplay.initShippingCalculator();
        });
    } else {
        HNGCartDisplay.init();
        HNGCartDisplay.initShippingCalculator();
    }

    // Expor globalmente
    window.HNGCartDisplay = HNGCartDisplay;
})();
