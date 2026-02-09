<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Página de Rastreamento -->
<?php
/**
 * Template: Rastreamento de Pedido
 * 
 * @package HNG_Commerce
 */

// Obter código de rastreamento da URL
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for tracking code lookup, no data modification
$tracking_code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
$tracking_data = null;
$error_message = '';

if (!empty($tracking_code)) {
    require_once HNG_COMMERCE_PATH . 'includes/class-hng-correios-shipping.php';
    $correios = HNG_Correios_Shipping::instance();
    
    $result = $correios->track_package($tracking_code);
    
    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
    } else {
        $tracking_data = $result;
    }
}

get_header();
?>

<div class="hng-tracking-page">
    <div class="hng-container">
        <div class="hng-tracking-header">
            <h1>Rastreamento de Pedido</h1>
            <p>Acompanhe sua encomenda em tempo real</p>
        </div>
        
        <!-- Formulário de Busca -->
        <div class="hng-tracking-search">
            <form method="get" action="">
                <div class="hng-search-group">
                    <input 
                        type="text" 
                        name="code" 
                        placeholder="Digite o código de rastreamento (Ex: AA123456789BR)"
                        value="<?php echo esc_attr($tracking_code); ?>"
                        maxlength="13"
                        required
                    >
                    <button type="submit" class="hng-btn hng-btn-primary">
                        Rastrear
                    </button>
                </div>
            </form>
            <p class="hng-help-text">
                O código de rastreamento foi enviado por email quando seu pedido foi despachado.
            </p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <!-- Erro -->
            <div class="hng-alert hng-alert-error">
                <strong>Erro:</strong> <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($tracking_data)): ?>
            <!-- Resultado do Rastreamento -->
            <div class="hng-tracking-result">
                <!-- Status Principal -->
                <div class="hng-tracking-status">
                    <div class="hng-status-icon <?php echo esc_attr( $tracking_data['delivered'] ? 'delivered' : 'in-transit' ); ?>">
                        <?php echo esc_html( $tracking_data['delivered'] ? '✔' : '→' ); ?>
                    </div>
                    <div class="hng-status-info">
                        <h2><?php echo esc_html( $tracking_data['delivered'] ? 'Entregue!' : 'Em Trânsito' ); ?></h2>
                        <p class="hng-tracking-code">
                            Código: <strong><?php echo esc_html($tracking_data['code']); ?></strong>
                        </p>
                        <p class="hng-last-status">
                            <?php echo esc_html($tracking_data['last_status']); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Timeline de Eventos -->
                <div class="hng-tracking-timeline">
                    <h3>Histórico de Movimentação</h3>
                    
                    <?php if (!empty($tracking_data['events'])): ?>
                        <div class="hng-timeline">
                            <?php foreach ($tracking_data['events'] as $index => $event): ?>
                                <div class="hng-timeline-item <?php echo esc_attr( $index === 0 ? 'active' : '' ); ?>">
                                    <div class="hng-timeline-marker"></div>
                                    <div class="hng-timeline-content">
                                        <div class="hng-timeline-date">
                                            <?php 
                                            $date = new DateTime($event['date']);
                                            echo esc_html( $date->format('d/m/Y H:i') );
                                            ?>
                                        </div>
                                        <div class="hng-timeline-location">
                                            <?php echo esc_html($event['location']); ?>
                                        </div>
                                        <div class="hng-timeline-status">
                                            <strong><?php echo esc_html($event['status']); ?></strong>
                                        </div>
                                        <?php if (!empty($event['details'])): ?>
                                            <div class="hng-timeline-details">
                                                <?php echo esc_html($event['details']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php esc_html_e('Nenhum evento de rastreamento disponível.', 'hng-commerce'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Dicas -->
                <div class="hng-tracking-tips">
                    <h4>Dicas Importantes</h4>
                    <ul>
                        <li>Os eventos são atualizados automaticamente pelos Correios</li>
                        <li>Pode haver um atraso de até 24 horas na atualização</li>
                        <li>Se o status indicar "Ausente", uma nova tentativa será feita</li>
                        <li>Você pode retirar o objeto em uma agência dos Correios após 7 dias</li>
                    </ul>
                </div>
                
                <!-- Aï¿½ï¿½es -->
                <div class="hng-tracking-actions">
                    <a href="<?php echo esc_url( hng_get_page_url('minha-conta') ); ?>" class="hng-btn hng-btn-secondary">
                        Voltar para Minha Conta
                    </a>
                    <a href="https://www.correios.com.br/contato" target="_blank" class="hng-btn hng-btn-outline">
                        Falar com os Correios
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Perguntas Frequentes -->
        <div class="hng-tracking-faq">
            <h3>Perguntas Frequentes</h3>
            
            <details class="hng-faq-item">
                <summary>Quanto tempo leva para o código funcionar?</summary>
                <p>O código de rastreamento é ativado nas primeiras 24 horas após a postagem do objeto.</p>
            </details>
            
            <details class="hng-faq-item">
                <summary>O que fazer se o código não funcionar?</summary>
                <p>Aguarde 24-48 horas após o envio. Se persistir, entre em contato com nossa loja.</p>
            </details>
            
            <details class="hng-faq-item">
                <summary>Posso mudar o endereço de entrega?</summary>
                <p>Sim, mas apenas através do app dos Correios ou com contato direto na agência.</p>
            </details>
            
            <details class="hng-faq-item">
                <summary>E se eu não estiver em casa na hora da entrega?</summary>
                <p>O carteiro deixará um aviso para retirada na agência ou agendamento de nova tentativa.</p>
            </details>
        </div>
    </div>
</div>

<style>
.hng-tracking-page {
    padding: 40px 0;
    background: #f5f7fa;
    min-height: 70vh;
}

.hng-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
}

.hng-tracking-header {
    text-align: center;
    margin-bottom: 40px;
}

.hng-tracking-header h1 {
    font-size: 32px;
    margin-bottom: 10px;
    color: #333;
}

.hng-tracking-header p {
    color: #666;
    font-size: 16px;
}

.hng-tracking-search {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.hng-search-group {
    display: flex;
    gap: 10px;
}

.hng-search-group input {
    flex: 1;
    padding: 14px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 15px;
    text-transform: uppercase;
    transition: all 0.3s;
}

.hng-search-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.hng-btn {
    padding: 14px 30px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.hng-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.hng-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.hng-btn-secondary {
    background: #95a5a6;
    color: white;
}

.hng-btn-outline {
    background: transparent;
    border: 2px solid #667eea;
    color: #667eea;
}

.hng-help-text {
    margin-top: 12px;
    font-size: 13px;
    color: #666;
    text-align: center;
}

.hng-alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.hng-alert-error {
    background: #ffebee;
    border-left: 4px solid #e74c3c;
    color: #c62828;
}

.hng-tracking-result {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.hng-tracking-status {
    display: flex;
    align-items: center;
    gap: 20px;
    padding-bottom: 30px;
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 30px;
}

.hng-status-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
}

.hng-status-icon.delivered {
    background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%);
    color: white;
}

.hng-status-icon.in-transit {
    background: linear-gradient(135deg, #2196f3 0%, #64b5f6 100%);
    color: white;
}

.hng-status-info h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
    color: #333;
}

.hng-tracking-code {
    font-size: 14px;
    color: #666;
    margin: 5px 0;
}

.hng-last-status {
    font-size: 15px;
    color: #667eea;
    margin: 10px 0 0 0;
}

.hng-tracking-timeline h3 {
    margin: 0 0 25px 0;
    font-size: 20px;
    color: #333;
}

.hng-timeline {
    position: relative;
    padding-left: 40px;
}

.hng-timeline::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}

.hng-timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.hng-timeline-marker {
    position: absolute;
    left: -33px;
    top: 4px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #e0e0e0;
    border: 3px solid white;
    box-shadow: 0 0 0 2px #e0e0e0;
}

.hng-timeline-item.active .hng-timeline-marker {
    background: #667eea;
    box-shadow: 0 0 0 2px #667eea, 0 0 12px rgba(102, 126, 234, 0.5);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.hng-timeline-content {
    background: #f9f9f9;
    padding: 15px 20px;
    border-radius: 8px;
}

.hng-timeline-date {
    font-size: 12px;
    color: #999;
    margin-bottom: 5px;
}

.hng-timeline-location {
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
}

.hng-timeline-status {
    font-size: 15px;
    color: #333;
    margin-bottom: 5px;
}

.hng-timeline-details {
    font-size: 13px;
    color: #666;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e0e0e0;
}

.hng-tracking-tips {
    background: #e3f2fd;
    padding: 20px;
    border-radius: 8px;
    margin-top: 30px;
}

.hng-tracking-tips h4 {
    margin: 0 0 15px 0;
    color: #1976d2;
}

.hng-tracking-tips ul {
    margin: 0;
    padding-left: 20px;
    line-height: 1.8;
}

.hng-tracking-tips li {
    color: #0d47a1;
}

.hng-tracking-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.hng-tracking-faq {
    margin-top: 40px;
    background: white;
    padding: 30px;
    border-radius: 12px;
}

.hng-tracking-faq h3 {
    margin: 0 0 20px 0;
    font-size: 20px;
    color: #333;
}

.hng-faq-item {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 10px;
    overflow: hidden;
}

.hng-faq-item summary {
    padding: 15px 20px;
    cursor: pointer;
    font-weight: 600;
    color: #333;
    background: #f9f9f9;
    transition: background 0.3s;
}

.hng-faq-item summary:hover {
    background: #f0f0f0;
}

.hng-faq-item p {
    padding: 15px 20px;
    margin: 0;
    color: #666;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .hng-search-group {
        flex-direction: column;
    }
    
    .hng-tracking-status {
        flex-direction: column;
        text-align: center;
    }
    
    .hng-tracking-actions {
        flex-direction: column;
    }
    
    .hng-btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<?php get_footer(); ?>
