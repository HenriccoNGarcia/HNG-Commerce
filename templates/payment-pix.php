<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template de Pagamento PIX
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order lookup in payment page, no data modification
$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

if (!$order_id) {
    wp_die('Pedido não encontrado');
}

$order = new HNG_Order($order_id);
$charge_id = get_post_meta($order->get_post_id(), '_hng_pix_charge_id', true);
$expires_at = (int) get_post_meta($order->get_post_id(), '_hng_pix_expires_at', true);

if (!$charge_id) {
    // Tentar gerar automaticamente se não existir
    if (class_exists('HNG_Pix_Manager')) {
        $init = HNG_Pix_Manager::init_charge($order_id);
        if (!is_wp_error($init)) {
            $charge_id = $init['charge_id'];
            $expires_at = $init['expires_at'];
            $pix_data = [ 'encodedImage' => $init['qr_code'], 'payload' => $init['copy_paste'] ];
        }
    }
}

if (!$charge_id) {
    wp_die('Cobrança PIX não encontrada ou falha ao gerar');
}

if (!isset($pix_data)) {
    // Buscar dados do QR via gateway se já criada
    if (class_exists('HNG_Gateway_Asaas')) {
        $gateway = new HNG_Gateway_Asaas();
        $qrcode = $gateway->get_pix_qrcode($charge_id);
        if (is_wp_error($qrcode)) {
            wp_die(esc_html__('Erro ao carregar dados do PIX: ', 'hng-commerce') . esc_html($qrcode->get_error_message()));
        }
        $pix_data = [ 'encodedImage' => $qrcode['encodedImage'] ?? '', 'payload' => $qrcode['payload'] ?? '' ];
    } else {
        $pix_data = [ 'encodedImage' => '', 'payload' => '' ];
    }
}

get_header();
?>

<div class="hng-payment-page hng-payment-pix" role="main" aria-label="Pagamento por PIX" tabindex="0">
    <div class="hng-container">
        <div class="hng-payment-box" role="region" aria-label="Box de pagamento" tabindex="0">
            <div class="hng-payment-header" role="region" aria-label="Cabeçalho do pagamento" tabindex="0">
                <span class="hng-payment-icon">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                        <path d="M20 40C31.0457 40 40 31.0457 40 20C40 8.9543 31.0457 0 20 0C8.9543 0 0 8.9543 0 20C0 31.0457 8.9543 40 20 40Z" fill="#32BCAD"/>
                        <path d="M28 12H12V28H28V12Z" fill="white"/>
                    </svg>
                </span>
                <h2>Pague com PIX</h2>
                <p class="hng-payment-description">
                    Pedido <strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
                </p>
            </div>
            
            <div class="hng-payment-amount">
                <span class="label">Valor a pagar:</span>
                <span class="value"><?php echo esc_html( hng_price($order->get_total()) ); ?></span>
            </div>
            
            <div class="hng-pix-instructions">
                <h3>Como pagar:</h3>
                <ol>
                    <li>Abra o app do seu banco</li>
                    <li>Escolha pagar com <strong>PIX QR Code</strong> ou <strong>PIX Copia e Cola</strong></li>
                    <li>Escaneie o código ou cole o código abaixo</li>
                    <li>Confirme o pagamento</li>
                </ol>
            </div>
            
            <div class="hng-pix-qrcode">
                <h3>Escaneie o QR Code:</h3>
                <div class="qrcode-container">
                    <?php if (!empty($pix_data['encodedImage'])): ?>
                        <img src="<?php echo esc_attr( 'data:image/png;base64,' . $pix_data['encodedImage'] ); ?>" alt="QR Code PIX" />
                    <?php else: ?>
                        <p class="error">QR Code não disponível</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="hng-pix-copy-paste">
                <h3>Ou copie o código PIX:</h3>
                <div class="copy-paste-container">
                    <input 
                        type="text" 
                        id="pix-code" 
                        value="<?php echo esc_attr($pix_data['payload'] ?? ''); ?>" 
                        readonly 
                    />
                    <button type="button" class="hng-btn hng-btn-copy" id="copy-pix-code">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M15 6.5H9C8.17157 6.5 7.5 7.17157 7.5 8V14C7.5 14.8284 8.17157 15.5 9 15.5H15C15.8284 15.5 16.5 14.8284 16.5 14V8C16.5 7.17157 15.8284 6.5 15 6.5Z"/>
                            <path d="M5 10.5H4.5C3.67157 10.5 3 9.82843 3 9V5C3 4.17157 3.67157 3.5 4.5 3.5H10.5C11.3284 3.5 12 4.17157 12 5V5.5"/>
                        </svg>
                        Copiar Código
                    </button>
                </div>
                <p class="copy-feedback" id="copy-feedback" style="display: none;">
                    Código copiado!
                </p>
            </div>
            
            <div class="hng-payment-status">
                <div class="status-checking">
                    <div class="spinner"></div>
                    <p id="pix-status-text">Aguardando pagamento...</p>
                    <small id="pix-expiration-text"></small>
                </div>
            </div>
            
            <div class="hng-payment-actions">
                    <a href="<?php echo esc_url( home_url('/minha-conta/pedidos/') ); ?>" class="hng-btn hng-btn-secondary">
                    Ver Meus Pedidos
                </a>
                            <button type="button" id="hng-pix-regenerate" class="hng-btn hng-btn-secondary" style="display:none;">Gerar Novo PIX</button>
            </div>
        </div>
    </div>
</div>

<style>
.hng-payment-page {
    padding: 60px 20px;
    background: #f5f5f5;
    min-height: 100vh;
}

.hng-container {
    max-width: 600px;
    margin: 0 auto;
}

.hng-payment-box {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    padding: 40px;
}

.hng-payment-header {
    text-align: center;
    margin-bottom: 30px;
}

.hng-payment-icon {
    display: inline-block;
    margin-bottom: 15px;
}

.hng-payment-header h2 {
    font-size: 28px;
    margin: 0 0 10px 0;
    color: #333;
}

.hng-payment-description {
    color: #666;
    margin: 0;
}

.hng-payment-amount {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 30px;
}

.hng-payment-amount .label {
    display: block;
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.hng-payment-amount .value {
    display: block;
    font-size: 32px;
    font-weight: bold;
    color: #32BCAD;
}

.hng-pix-instructions {
    margin-bottom: 30px;
    padding: 20px;
    background: #e8f5f3;
    border-radius: 8px;
    border-left: 4px solid #32BCAD;
}

.hng-pix-instructions h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #333;
}

.hng-pix-instructions ol {
    margin: 0;
    padding-left: 20px;
}

.hng-pix-instructions li {
    margin-bottom: 8px;
    color: #555;
}

.hng-pix-qrcode {
    text-align: center;
    margin-bottom: 30px;
}

.hng-pix-qrcode h3 {
    font-size: 16px;
    margin-bottom: 20px;
    color: #333;
}

.qrcode-container {
    display: inline-block;
    padding: 20px;
    background: white;
    border: 2px solid #e5e5e5;
    border-radius: 8px;
}

.qrcode-container img {
    display: block;
    max-width: 250px;
    height: auto;
}

.hng-pix-copy-paste {
    margin-bottom: 30px;
}

.hng-pix-copy-paste h3 {
    font-size: 16px;
    margin-bottom: 15px;
    color: #333;
}

.copy-paste-container {
    display: flex;
    gap: 10px;
}

.copy-paste-container input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-family: monospace;
    font-size: 12px;
    color: #333;
}

.hng-btn-copy {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: #32BCAD;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    white-space: nowrap;
    transition: background 0.3s;
}

.hng-btn-copy:hover {
    background: #2aa899;
}

.copy-feedback {
    margin-top: 10px;
    color: #2aa899;
    font-weight: 600;
}

.hng-payment-status {
    margin-bottom: 30px;
    padding: 20px;
    background: #fff8e6;
    border-radius: 8px;
    text-align: center;
}

.status-checking {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #32BCAD;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.status-checking p {
    margin: 0;
    font-weight: 600;
    color: #333;
}

.status-checking small {
    color: #666;
    font-size: 13px;
}

.hng-payment-actions {
    text-align: center;
}

.hng-btn {
    display: inline-block;
    padding: 12px 30px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.hng-btn-secondary {
    background: #f5f5f5;
    color: #333;
}

.hng-btn-secondary:hover {
    background: #e5e5e5;
}
.hng-btn-regenerating { opacity:0.6; pointer-events:none; }

@media (max-width: 600px) {
    .hng-payment-box {
        padding: 30px 20px;
    }
    
    .copy-paste-container {
        flex-direction: column;
    }
    
    .hng-btn-copy {
        justify-content: center;
    }
}
</style>

<script>
(function(){
    const orderId = <?php echo (int) $order_id; ?>;
    const nonce = <?php echo wp_json_encode( wp_create_nonce('HNG Commerce') ); ?>;
    const expiresAt = <?php echo (int) ($expires_at ?: 0); ?>;
    const statusText = document.getElementById('pix-status-text');
    const expireEl = document.getElementById('pix-expiration-text');

    function fmtRemaining(sec){
        if(sec <= 0) return 'Expirado';
        const m = Math.floor(sec/60), s = sec%60; return 'Expira em ' + m + 'm ' + s + 's';
    }
    function tickExpiration(){
        if(!expiresAt) return;
        const remaining = expiresAt - Math.floor(Date.now()/1000);
        expireEl.textContent = fmtRemaining(remaining);
        if(remaining > 0){ setTimeout(tickExpiration, 1000); }
    }
    tickExpiration();

    let phase = 0;
    function poll(){
        const form = new FormData();
        form.append('action','hng_pix_poll');
        form.append('nonce', nonce);
        form.append('order_id', orderId);
        fetch(<?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>,{method:'POST', body:form})
            .then(r=>r.json())
            .then(data=>{
                if(!data.success){ console.warn('PIX poll erro', data); schedule(); return; }
                const st = data.data.status;
                if(st === 'paid'){
                    statusText.textContent = 'Pagamento confirmado!';
                    expireEl.textContent = '';
                    const rb = document.getElementById('hng-pix-regenerate'); if(rb) rb.style.display='none';
                } else if(st === 'expired') {
                    statusText.textContent = 'Cobrança expirada. Gere novo PIX.';
                    const rb = document.getElementById('hng-pix-regenerate'); if(rb) rb.style.display='inline-block';
                } else if(st === 'refunded') {
                    statusText.textContent = 'Pagamento reembolsado.';
                    const rb = document.getElementById('hng-pix-regenerate'); if(rb) rb.style.display='none';
                } else {
                    statusText.textContent = 'Aguardando pagamento...';
                    schedule();
                }
            }).catch(e=>{ console.warn('PIX poll falha', e); schedule(); });
    }
    function schedule(){
        phase++;
        if(phase < 8){ setTimeout(poll,15000); }
        else if(phase < 38){ setTimeout(poll,60000); }
    }
    poll();

    // Copiar código PIX
    const copyBtn = document.getElementById('copy-pix-code');
    const pixInput = document.getElementById('pix-code');
    const feedback = document.getElementById('copy-feedback');
    if(copyBtn && pixInput){
        copyBtn.addEventListener('click', function(){
            pixInput.select();
            try { document.execCommand('copy'); feedback.style.display='block'; setTimeout(()=>feedback.style.display='none',2500); } catch(e){ console.warn('Falha ao copiar PIX', e); }
        });
    }

    // Regenerar PIX
    const regenBtn = document.getElementById('hng-pix-regenerate');
    if(regenBtn){
        regenBtn.addEventListener('click', function(){
            regenBtn.classList.add('hng-btn-regenerating');
            regenBtn.textContent='Gerando...';
            const f = new FormData();
            f.append('action','hng_pix_regenerate');
            f.append('nonce', nonce);
            f.append('order_id', orderId);
            fetch(<?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>,{method:'POST', body:f})
                .then(r=>r.json())
                .then(data=>{
                    regenBtn.classList.remove('hng-btn-regenerating');
                    regenBtn.textContent='Gerar Novo PIX';
                    if(!data.success){ alert('Falha: '+(data.data && data.data.message || 'Erro desconhecido')); return; }
                    // Atualizar QR e código
                    if(data.data.qr_code){
                        const img = document.querySelector('.hng-pix-qrcode img');
                        if(img){ img.src = data.data.qr_code.startsWith('data:')? data.data.qr_code : 'data:image/png;base64,'+data.data.qr_code; }
                    }
                    if(data.data.copy_paste && pixInput){ pixInput.value = data.data.copy_paste; }
                    // Forçar reload para recontar expiração
                    if(data.data.expires_at){ window.location.reload(); }
                }).catch(err=>{
                    console.warn(err);
                    regenBtn.classList.remove('hng-btn-regenerating');
                    regenBtn.textContent='Gerar Novo PIX';
                });
        });
    }
})();
</script>


<?php
get_footer();
