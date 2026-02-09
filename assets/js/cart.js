(function(window, document, $){
  'use strict';
  // Stub inicial para evitar 404 e permitir futura expansão.
  var HNGCart = {
    initialized: false,
    init: function(){
      if(this.initialized) return;
      this.initialized = true;
      // Exemplo: bind genérico para adicionar ao carrinho futuramente
      $(document).on('click','.hng-add-to-cart', function(e){
        e.preventDefault();
        var productId = $(this).data('product-id');
        HNGCart.add(productId, 1);
      });
      if(window.hngCommerce && window.hngCommerce.i18n){
        console.log('[HNGCart] carregado');
      }
    },
    add: function(productId, qty){
      // Placeholder de chamada AJAX futura
      console.log('[HNGCart] add()', productId, qty);
      // Suporte para feedback imediato
      if(window.hngCommerce && window.hngCommerce.i18n){
        HNGCart.notice(window.hngCommerce.i18n.added_to_cart || 'Adicionado');
      }
    },
    notice: function(msg){
      if(!msg) return;
      var el = document.createElement('div');
      el.className = 'hng-cart-notice';
      el.textContent = msg;
      document.body.appendChild(el);
      setTimeout(function(){
        el.classList.add('visible');
      }, 10);
      setTimeout(function(){
        el.classList.remove('visible');
        setTimeout(function(){
          if(el.parentNode){ el.parentNode.removeChild(el); }
        }, 400);
      }, 3000);
    },
    refresh: function(){
      console.log('[HNGCart] refresh()');
    }
  };
  window.HNGCart = HNGCart;
  $(function(){ HNGCart.init(); });
})(window, document, jQuery);
