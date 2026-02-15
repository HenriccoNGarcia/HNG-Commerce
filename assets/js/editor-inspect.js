(function(window,$){
  'use strict';
  var started = Date.now();
  function safe(fn){try{fn();}catch(e){console.warn('[HNG Inspect] erro:', e);} }

  // Capturar erros globais de script (carregamento, falta de recursos)
  window.addEventListener('error', function(ev){
    if(ev && ev.message){
      console.warn('[HNG Inspect] window.error capturado:', ev.message, ev.filename || '');
    }
  }, true);
  window.addEventListener('unhandledrejection', function(ev){
    console.warn('[HNG Inspect] Promise rejeitada sem handler:', ev.reason);
  });

  function snapshot(label){
    var el = window.elementor;
    var common = window.elementorCommon;
    console.groupCollapsed('[HNG Inspect] '+label+' t+'+(Date.now()-started)+'ms');
    console.log('elementor:', el);
    console.log('elementorCommon:', common);
    if(el && el.panelsManager){
      console.log('Painéis keys:', Object.keys(el.panelsManager.panels||{}));
      console.log('__createPanel type:', typeof el.panelsManager.__createPanel);
    } else {
      console.warn('panelsManager ausente');
    }
    var heartbeat = common && common.heartbeat;
    console.log('heartbeat objeto:', heartbeat);
    if(heartbeat && typeof heartbeat.connectNow !== 'function'){
      console.warn('heartbeat.connectNow ausente');
    }
    console.groupEnd();
  }

  // Snapshot inicial (sincrono)
  safe(function(){ snapshot('snapshot inicial'); });

  // Retry progressivo para observar momento em que APIs ficam disponíveis
  var attempts = 0;
  var maxAttempts = 6; // ~6 * 800ms = ~5s
  function retry(){
    attempts++;
    safe(function(){ snapshot('retry '+attempts); });
    // parar se já temos createPanel e heartbeat.connectNow
    var ready = window.elementor && window.elementor.panelsManager && typeof window.elementor.panelsManager.__createPanel === 'function' && window.elementorCommon && window.elementorCommon.heartbeat && typeof window.elementorCommon.heartbeat.connectNow === 'function';
    if(!ready && attempts < maxAttempts){
      setTimeout(retry, 800);
    } else if(!ready) {
      console.warn('[HNG Inspect] APIs Elementor não estabilizaram após tentativas. Investigar conflitos de minificação/caching.');
    }
  }
  setTimeout(retry, 500);

  // Sinal DOM pronto
  $(function(){ console.log('[HNG Inspect] DOM pronto t+'+(Date.now()-started)+'ms'); });
})(window,jQuery);
