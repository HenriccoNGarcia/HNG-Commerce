# HNG Commerce - Documentação Completa

**Versão:** 1.2.16  
**Autor:** HNG Desenvolvimentos  
**Licença:** GPL v2 or later  
**Requer PHP:** 7.4+  
**Requer WordPress:** 5.8+  
**Testado até:** WordPress 6.9+  
**Última atualização:** Fevereiro de 2026

---

## Índice

1. [Visão Geral](#visão-geral)
2. [Instalação](#instalação)
3. [Estrutura do Plugin](#estrutura-do-plugin)
4. [Arquitetura](#arquitetura)
5. [Classes Principais](#classes-principais)
6. [Tipos de Produto](#tipos-de-produto)
7. [Gateways de Pagamento](#gateways-de-pagamento)
8. [Integrações de Frete](#integrações-de-frete)
9. [Hooks e Filtros](#hooks-e-filtros)
10. [Shortcodes](#shortcodes)
11. [REST API](#rest-api)
12. [Templates](#templates)
13. [Configurações](#configurações)
14. [Funções Auxiliares](#funções-auxiliares)
15. [Segurança](#segurança)
16. [Banco de Dados](#banco-de-dados)
17. [Integração Elementor](#integração-elementor)
18. [Sistema de E-mails](#sistema-de-e-mails)
19. [Avaliações e Reviews](#avaliações-e-reviews)
20. [Lista de Desejos (Wishlist)](#lista-de-desejos-wishlist)
21. [Compatibilidade e Atualizações](#compatibilidade-e-atualizações)
22. [Guia do Desenvolvedor](#guia-do-desenvolvedor)
23. [Troubleshooting](#troubleshooting)

---

## Visão Geral

O **HNG Commerce** é uma solução completa de e-commerce para WordPress, focada no mercado brasileiro. É uma alternativa ao WooCommerce com integrações nativas de pagamento e frete brasileiros.

### Principais Características

- **Catálogo ilimitado** de produtos (sem limite mensal)
- **Gateways brasileiros**: Asaas, Mercado Pago, PagSeguro, Pagar.me, Nubank, Inter, Cielo, PicPay, e mais
- **PIX Parcelado**: parcele pagamentos PIX em múltiplas parcelas
- **Assinaturas recorrentes** com cobrança automática
- **Agendamentos**: produtos baseados em data/hora com gestão de profissionais
- **Orçamentos**: produtos com negociação de preço e chat integrado
- **Frete integrado**: Correios, Melhor Envio, JadLog, Loggi, Total Express com gerador de etiquetas
- **Avaliações e Reviews**: sistema completo de avaliações de produtos
- **Lista de Desejos**: clientes podem salvar produtos favoritos
- **Dashboard financeiro** completo com relatórios e analytics
- **Compatibilidade Elementor** com widgets dedicados
- **Compatibilidade WordPress 6.8+** com sistema automático de compatibilidade
- **100% em português** (pt_BR)

### Benefícios

| Recurso | Descrição |
|---------|-----------|
| Gratuito | Plugin open-source sob GPLv2 |
| Sem limite de pedidos | Processe quantos pedidos quiser |
| Mercado brasileiro | Foco total em integrações BR |
| Dashboard avançado | Relatórios financeiros e conversão |
| Multigateway | Suporte a múltiplos gateways |

---

## Instalação

### Instalação Automática

1. No painel WordPress, vá em **Plugins > Adicionar Novo**
2. Clique em **Enviar Plugin** e selecione o arquivo `.zip`
3. Ative o plugin
4. Vá em **HNG Commerce > Configurações** para configurar

### Requisitos

- **PHP:** 7.4 ou superior
- **WordPress:** 5.8 ou superior
- **OpenSSL:** extensão com suporte a AES-256-GCM
- **MySQL:** 5.7 ou MariaDB 10.2+

### Pós-Instalação

1. Execute o **Assistente de Configuração** (Setup Wizard)
2. Configure os gateways de pagamento
3. Configure os métodos de frete
4. Crie as páginas necessárias (Loja, Carrinho, Checkout, Minha Conta)

---

## Estrutura do Plugin

```
hng-commerce/
├── hng-commerce.php          # Arquivo principal do plugin
├── uninstall.php             # Script de desinstalação
├── readme.txt                # Readme para WordPress.org
├── LICENSE.txt               # Licença GPL
├── composer.json             # Dependências Composer
│
├── admin/                    # Arquivos administrativos (reconstruído v1.2.14+)
│   ├── class-hng-admin.php   # Admin bootstrap após merge
│   ├── ajax-asaas-sync.php   # Sincronização AJAX Asaas (NOVO)
│   ├── ajax-pagseguro-sync.php # Sincronização AJAX PagSeguro (NOVO)
│   ├── ajax-customers-management.php # Gestão de clientes AJAX (NOVO)
│   ├── settings/             # Gerenciador de configurações (NOVO)
│   │   └── class-admin-settings.php
│   ├── meta-boxes/           # Gerenciador de meta-boxes (NOVO)
│   │   └── class-meta-boxes-manager.php
│   ├── pages/                # Páginas administrativas (expandido)
│   │   ├── class-analytics-hub-page.php
│   │   ├── class-appointments-page.php
│   │   ├── class-customers-page.php
│   │   ├── class-email-customizer-page.php
│   │   ├── class-feedback-page.php
│   │   ├── class-financial-dashboard-page.php
│   │   ├── class-gateway-management-page.php
│   │   ├── class-hng-asaas-data-page.php
│   │   ├── class-professionals-page.php
│   │   ├── class-shipping-page.php
│   │   ├── class-orders-page.php
│   │   ├── class-reports-page.php
│   │   ├── class-subscriptions-page.php
│   │   └── class-tools-page.php
│   └── assets/               # CSS/JS do admin
│
├── assets/                   # Assets frontend/backend
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   │   ├── admin.js
│   │   ├── cart.js
│   │   └── notifications.js
│   ├── images/
│   └── vendor/
│
├── database/                 # Migrações de banco
│   └── migrations/
│       ├── add-data-source-column.php
│       ├── create-coupon-usage-table.php
│       └── create-security-log-table.php
│
├── gateways/                 # Gateways de pagamento
│   ├── class-gateway-base.php
│   ├── asaas/
│   │   └── class-gateway-asaas.php
│   ├── mercadopago/
│   │   └── class-gateway-mercadopago.php
│   ├── pagseguro/
│   ├── pagarme/
│   ├── nubank/
│   ├── inter/
│   ├── cielo/
│   ├── picpay/
│   ├── bancodobrasil/
│   ├── bradesco/
│   ├── itau/
│   ├── santander/
│   └── c6bank/
│
├── includes/                 # Classes principais (55+ classes)
│   ├── functions.php
│   ├── class-hng-autoloader.php
│   ├── class-hng-cart.php
│   ├── class-hng-checkout.php
│   ├── class-hng-order.php
│   ├── class-hng-product.php
│   ├── class-hng-customer.php
│   ├── class-hng-coupon.php
│   ├── class-hng-email.php
│   ├── class-hng-ajax.php
│   ├── class-hng-shortcodes.php
│   ├── class-hng-frontend.php
│   ├── class-hng-session.php
│   ├── class-hng-subscription.php
│   ├── class-hng-appointment.php
│   ├── class-hng-pix-installment.php
│   ├── class-hng-webhook-handler.php
│   ├── class-hng-reviews.php (NOVO - Avaliações)
│   ├── class-hng-wishlist.php (NOVO - Lista de Desejos)
│   ├── class-hng-payment-orchestrator.php (NOVO - Orquestração de pagamentos)
│   ├── class-hng-checkout-intent-handler.php (NOVO)
│   ├── class-hng-gateway-capabilities.php (NOVO - Resolver capabilities)
│   ├── class-hng-digital-product.php (NOVO - Produto Digital)
│   ├── class-hng-fee-calculator.php (NOVO - Cálculo de taxas)
│   ├── class-hng-commerce-updater.php (NOVO - Sistema de atualização)
│   ├── class-hng-user-profile.php (NOVO)
│   ├── class-hng-download-routes.php (NOVO)
│   ├── class-hng-customer-registration.php (NOVO)
│   ├── class-hng-pix-manager.php (NOVO - Gerenciador PIX)
│   ├── class-hng-security-headers.php (NOVO - Headers de segurança)
│   ├── class-hng-domain-heartbeat.php (NOVO)
│   ├── class-hng-commerce-compatibility.php (NOVO - Compatibilidade)
│   ├── class-hng-commerce-compatibility-v2.php (NOVO)
│   ├── class-hng-wp68-compatibility.php (NOVO - WP 6.8+)
│   ├── class-hng-performance.php (NOVO - Performance)
│   ├── class-hng-api-client.php (NOVO)
│   ├── class-hng-rate-limiter.php
│   ├── class-hng-ledger.php
│   ├── class-hng-crypto.php
│   ├── class-hng-password.php
│   │
│   ├── admin/                # Classes administrativas (reconstruído)
│   │   └── (veja pasta /admin acima para detalhes)
│   │
│   ├── ajax/                 # AJAX handlers (NOVO)
│   │
│   ├── analytics/            # Análise e conversão
│   │   └── class-conversion-tracker.php
│   │
│   ├── auth/                 # Autenticação
│   │   ├── class-google-oauth.php
│   │   └── class-hng-google-auth.php
│   │
│   ├── chat/                 # Sistema de chat
│   │   └── class-quote-chat.php
│   │
│   ├── controllers/          # Controllers (NOVO)
│   │
│   ├── database/             # Instalador de banco
│   │   └── class-database-installer.php
│   │
│   ├── elementor/            # Integração Elementor
│   │   ├── loader.php
│   │   └── widgets/
│   │
│   ├── emails/               # Sistema de e-mails
│   │   └── class-hng-email-manager.php
│   │
│   ├── financial/            # Módulo financeiro
│   │   ├── class-cost-tracker.php
│   │   ├── class-profit-calculator.php
│   │   ├── class-financial-dashboard.php
│   │   ├── class-financial-analytics.php
│   │   └── class-fee-manager.php
│   │
│   ├── helpers/              # Funções auxiliares
│   │   ├── hng-db.php
│   │   └── hng-gateway-data.php
│   │
│   ├── import-export/        # Importação/Exportação
│   │   ├── class-csv-importer-exporter.php
│   │   └── class-woocommerce-importer.php
│   │
│   ├── integrations/         # Integrações avançadas
│   │   └── class-hng-asaas-advanced-integration.php
│   │
│   ├── products/             # Tipos de produto
│   │   └── class-product-type-quote.php
│   │
│   ├── reports/              # Gerador de relatórios
│   │   └── class-reports-generator.php
│   │
│   ├── security/             # Segurança
│   │   └── class-rate-limiter.php
│   │
│   └── shipping/             # Métodos de frete
│       ├── class-shipping-method.php
│       ├── class-shipping-manager.php
│       ├── class-shipping-correios.php
│       ├── class-shipping-melhorenvio.php
│       ├── class-shipping-jadlog.php
│       ├── class-shipping-loggi.php
│       ├── class-shipping-total-express.php
│       ├── class-shipping-labels.php
│       └── shipping-diagnostic.php (NOVO)
│
├── languages/                # Traduções
│
├── logs/                     # Arquivos de log
│
├── templates/                # Templates do frontend
│   ├── archive-product.php
│   ├── single-product.php
│   ├── content-product.php
│   ├── cart.php
│   ├── checkout.php
│   ├── my-account.php
│   ├── order-received.php
│   ├── emails/
│   └── partials/
│
├── modules/                  # Módulos adicionais (NOVO - vazio na v1.2.16)
│
├── mu-plugins/               # Must-use plugins (NOVO)
│
├── integrations/             # Integrações (reorganizado)
│
├── shipping/                 # Frete (reorganizado)
│
├── request-quote.php         # Handler de requisição de orçamento (NOVO)
├── setup-client-registration-page.php # Setup de registro (NOVO)
│
└── tests/                    # Testes
```
```

---

## Arquitetura

### Padrão Singleton

O plugin utiliza o padrão **Singleton** para suas classes principais, garantindo uma única instância de cada componente.

```php
// Classe principal
HNG_Commerce::instance();

// Acessando o carrinho
HNG_Cart::instance();

// Acessando checkout
HNG_Checkout::instance();
```

### Constantes Definidas

```php
// Versão do plugin
HNG_COMMERCE_VERSION       // '1.2.12'

// Caminhos
HNG_COMMERCE_FILE          // Caminho completo do arquivo principal
HNG_COMMERCE_PATH          // Diretório do plugin (com trailing slash)
HNG_COMMERCE_URL           // URL do plugin (com trailing slash)
HNG_COMMERCE_BASENAME      // 'hng-commerce/hng-commerce.php'
HNG_COMMERCE_SLUG          // 'hng-commerce'

// Requisitos mínimos
HNG_COMMERCE_MIN_PHP       // '7.4'
HNG_COMMERCE_MIN_WP        // '5.8'
```

### Fluxo de Inicialização

1. **Verificação de requisitos** (PHP, WordPress, OpenSSL)
2. **Carregamento do autoloader**
3. **Inclusão de arquivos core**
4. **Registro de hooks**
5. **Inicialização no hook `init`**
6. **Registro de post types e taxonomias**
7. **Instanciação de classes (Admin/Frontend)**

---

## Classes Principais

### HNG_Commerce (Classe Principal)

```php
<?php
/**
 * Obter instância do plugin
 */
$hng = HNG_Commerce();

// Acessar carrinho
$cart = $hng->cart();

// Acessar sessão
$session = $hng->session();
```

### HNG_Product

Representa um produto no sistema.

```php
<?php
// Criar instância do produto
$product = new HNG_Product($product_id);
// ou
$product = hng_get_product($product_id);

// Métodos disponíveis
$product->get_id();                  // ID do produto
$product->get_name();                // Nome
$product->get_slug();                // Slug
$product->get_permalink();           // URL do produto
$product->get_description();         // Descrição completa
$product->get_short_description();   // Descrição curta
$product->get_sku();                 // SKU
$product->get_price();               // Preço atual
$product->get_regular_price();       // Preço regular
$product->get_sale_price();          // Preço promocional
$product->is_on_sale();              // Está em promoção?
$product->get_price_html();          // Preço formatado HTML
$product->get_stock_quantity();      // Quantidade em estoque
$product->get_stock_status();        // 'instock', 'outofstock', 'onbackorder'
$product->is_in_stock();             // Tem estoque?
$product->manages_stock();           // Gerencia estoque?
$product->is_purchasable();          // Pode ser comprado?
$product->is_sold_individually();    // Vendido individualmente?
$product->get_product_type();        // Tipo do produto
$product->get_image_id();            // ID da imagem destacada
$product->get_image_url($size);      // URL da imagem
$product->get_gallery_image_ids();   // IDs da galeria
$product->get_categories();          // Categorias
$product->get_tags();                // Tags
$product->reduce_stock($qty);        // Reduzir estoque
$product->increase_stock($qty);      // Aumentar estoque
$product->increment_sales($qty);     // Incrementar vendas
```

### HNG_Cart

Gerencia o carrinho de compras.

```php
<?php
// Obter instância
$cart = hng_cart();
// ou
$cart = HNG_Cart::instance();

// Métodos disponíveis
$cart->add_to_cart($product_id, $qty, $variation_id, $variation);
$cart->remove_cart_item($cart_id);
$cart->set_quantity($cart_id, $quantity);
$cart->empty_cart();
$cart->get_cart();                   // Todos os itens
$cart->get_cart_count();             // Número de itens
$cart->is_empty();                   // Carrinho vazio?
$cart->get_subtotal();               // Subtotal
$cart->get_total();                  // Total (com frete e desconto)
$cart->get_discount_total();         // Total de descontos
$cart->get_shipping_total();         // Total de frete
$cart->apply_coupon($code);          // Aplicar cupom
$cart->remove_coupon($code);         // Remover cupom
$cart->get_applied_coupons();        // Cupons aplicados
$cart->set_selected_shipping($data); // Definir frete selecionado
$cart->get_selected_shipping();      // Obter frete selecionado
```

### HNG_Order

Gerencia pedidos.

```php
<?php
// Criar pedido do carrinho
$order = HNG_Order::create_from_cart($data);

// Carregar pedido existente
$order = new HNG_Order($order_id);

// Métodos disponíveis
$order->get_id();                    // ID do pedido
$order->get_order_number();          // Número do pedido
$order->get_status();                // Status atual
$order->update_status($status, $note);
$order->get_total();                 // Total do pedido
$order->get_subtotal();              // Subtotal
$order->get_shipping_total();        // Frete
$order->get_discount_total();        // Desconto
$order->get_items();                 // Itens do pedido
$order->get_customer_id();           // ID do cliente
$order->get_billing_data();          // Dados de cobrança
$order->get_shipping_data();         // Dados de entrega
$order->get_payment_method();        // Método de pagamento
$order->add_note($note);             // Adicionar nota
$order->get_notes();                 // Obter notas
```

### HNG_Checkout

Processa o checkout.

```php
<?php
$checkout = HNG_Checkout::instance();

// Processar checkout (chamado automaticamente via hook)
$checkout->process_checkout();

// Métodos internos
$checkout->validate_checkout_fields($data);
$checkout->prepare_order_data($post);
$checkout->process_payment($order, $data);
$checkout->get_order_received_url($order);
```

### HNG_Subscription

Gerencia assinaturas recorrentes.

```php
<?php
$subscription = new HNG_Subscription($subscription_id);

$subscription->get_status();
$subscription->cancel($immediately);
$subscription->pause();
$subscription->resume();
$subscription->process_renewal();
$subscription->update_next_payment_date($date);
```

### HNG_Appointment

Gerencia agendamentos.

```php
<?php
$appointment = new HNG_Appointment($appointment_id);

$appointment->get_date();
$appointment->get_time();
$appointment->get_professional_id();
$appointment->update_status($status);
$appointment->reschedule($new_date, $new_time);
```

---

## Tipos de Produto

O HNG Commerce suporta 5 tipos de produtos:

### 1. Produto Físico (physical)

Itens que requerem envio. Cálculo de frete integrado.

```php
// Meta dados
'_hng_product_type' => 'physical'
'_weight'           => '0.5'      // kg
'_length'           => '20'       // cm
'_width'            => '15'       // cm
'_height'           => '10'       // cm
```

### 2. Produto Digital (digital)

Downloads após pagamento.

```php
'_hng_product_type'     => 'digital'
'_downloadable_files'   => [...]  // Arquivos para download
'_download_limit'       => 3      // Limite de downloads
'_download_expiry'      => 30     // Dias para expirar
```

### 3. Assinatura (subscription)

Cobranças recorrentes automáticas.

```php
'_hng_product_type'         => 'subscription'
'_subscription_price'       => '99.90'
'_subscription_period'      => 'month'  // day, week, month, year
'_subscription_interval'    => 1
'_subscription_length'      => 12       // Meses (0 = infinito)
'_subscription_trial_days'  => 7
```

### 4. Agendamento (appointment)

Serviços com data/hora específicos.

```php
'_hng_product_type'         => 'appointment'
'_appointment_duration'     => 60       // Minutos
'_appointment_capacity'     => 1        // Vagas por horário
'_appointment_professionals' => [1, 2]  // IDs dos profissionais
```

### 5. Orçamento (quote)

Produtos que requerem negociação.

```php
'_hng_product_type'    => 'quote'
'_quote_fields'        => [...]   // Campos personalizados
'_requires_approval'   => 'yes'
```

### Normalizando Tipos

```php
<?php
// Usando a classe de tipos
$type = HNG_Product_Types::normalize('simple'); // Retorna 'physical'
$type = HNG_Product_Types::normalize('variable'); // Retorna 'physical'

// Tipos permitidos
$allowed = ['physical', 'digital', 'subscription', 'appointment', 'quote'];
```

---

## Gateways de Pagamento

### Gateway Base

Todos os gateways estendem `HNG_Payment_Gateway`:

```php
<?php
abstract class HNG_Payment_Gateway {
    public $id = '';              // ID único
    public $title = '';           // Nome para exibição
    public $description = '';     // Descrição
    public $enabled = false;      // Habilitado?
    public $icon = '';            // Ícone
    public $supported_methods = [];  // ['pix', 'boleto', 'credit_card']
    
    // Métodos que podem ser sobrescritos
    public function is_enabled();
    public function is_configured();
    public function process_payment($order_id, $payment_data);
}
```

### Gateways Disponíveis

| Gateway | ID | Métodos Suportados |
|---------|----|--------------------|
| Asaas | `asaas` | PIX, Boleto, Cartão de Crédito |
| Mercado Pago | `mercadopago` | PIX, Boleto, Cartão de Crédito |
| PagSeguro | `pagseguro` | PIX, Boleto, Cartão de Crédito |
| Pagar.me | `pagarme` | Cartão de Crédito |
| Nubank | `nubank` | PIX |
| Inter | `inter` | PIX, Boleto |
| Cielo | `cielo` | Cartão de Crédito |
| PicPay | `picpay` | PIX |
| Banco do Brasil | `bancodobrasil` | Boleto |
| Bradesco | `bradesco` | Boleto |
| Itaú | `itau` | Boleto |
| Santander | `santander` | Boleto |
| C6 Bank | `c6bank` | PIX |

### Configurando Gateway Asaas

```php
<?php
// Opções armazenadas
update_option('hng_asaas_api_key', 'sua_api_key');
update_option('hng_asaas_environment', 'production'); // ou 'sandbox'
update_option('hng_gateway_asaas_enabled', 'yes');
update_option('hng_asaas_wallet_id', 'wallet_id_opcional');
```

### Processando Pagamento

```php
<?php
// O checkout chama automaticamente o gateway configurado
// Exemplo de processamento manual:

$gateway = new HNG_Gateway_Asaas();

$result = $gateway->process_payment($order_id, [
    'method' => 'pix',  // pix, boleto, credit_card
    'amount' => 99.90,
    'customer' => [
        'name' => 'João Silva',
        'email' => 'joao@email.com',
        'cpf' => '12345678900',
    ]
]);

if (is_wp_error($result)) {
    echo $result->get_error_message();
}
```

### Webhooks

O plugin registra automaticamente endpoints para webhooks:

```
POST /wp-json/hng/v1/webhook/{gateway}
```

Exemplo para Asaas:
```
https://seusite.com/wp-json/hng/v1/webhook/asaas
```

---

## Integrações de Frete

### Shipping Manager

Gerencia todos os métodos de frete.

```php
<?php
$manager = HNG_Shipping_Manager::instance();

// Obter métodos habilitados
$methods = $manager->get_enabled_methods();

// Calcular frete
$package = [
    'destination' => [
        'postcode' => '01310100',
        'country' => 'BR',
    ],
    'items' => [
        [
            'product_id' => 123,
            'quantity' => 2,
            'weight' => 0.5,
            'length' => 20,
            'width' => 15,
            'height' => 10,
        ]
    ]
];

$rates = $manager->calculate_shipping($package);
// Retorna array ordenado por custo (mais barato primeiro)
```

### Transportadoras Suportadas

| Transportadora | Classe | Serviços |
|----------------|--------|----------|
| Correios | `HNG_Shipping_Correios` | PAC, SEDEX, SEDEX 10, SEDEX 12, SEDEX Hoje |
| Melhor Envio | `HNG_Shipping_Melhor_Envio` | Múltiplas transportadoras |
| JadLog | `HNG_Shipping_Jadlog` | Package, .Com, Corporate |
| Loggi | `HNG_Shipping_Loggi` | Expressa, Econômica |
| Total Express | `HNG_Shipping_Total_Express` | Standard, Express |

### Geração de Etiquetas

```php
<?php
$labels = HNG_Shipping_Labels::instance();

$result = $labels->generate_label($order_id, 'correios', [
    'service' => 'sedex',
    'weight' => 0.5,
]);

if (!is_wp_error($result)) {
    $pdf_url = $result['label_url'];
    $tracking = $result['tracking_code'];
}
```

---

## Hooks e Filtros

### Hooks de Ação (do_action)

#### Inicialização

```php
// Plugin totalmente inicializado
do_action('hng_commerce_init');
```

#### Carrinho

```php
// Antes de adicionar ao carrinho
do_action('hng_cart_insufficient_stock', $product_id, $stock_qty, $total_qty);

// Após adicionar ao carrinho
do_action('hng_add_to_cart', $cart_id, $product_id, $qty, $variation_id, $variation, $cart_item_data);

// Após remover item
do_action('hng_cart_item_removed', $cart_id, $product_id, $cart_item);

// Após atualizar quantidade
do_action('hng_cart_item_quantity_updated', $cart_id, $quantity, $old_quantity);

// Após esvaziar carrinho
do_action('hng_cart_emptied', $old_cart);

// Após aplicar cupom
do_action('hng_applied_coupon', $code);

// Após remover cupom
do_action('hng_removed_coupon', $code);
```

#### Pedidos

```php
// Após criar pedido
do_action('hng_order_created', $order_id, $order_data, $cart, $data);

// Se criação falhar
do_action('hng_order_creation_failed', $order_data, $cart, $data);

// Após criar item do pedido
do_action('hng_order_item_created', $order_id, $item_data, $cart_item);

// Após criar post do pedido
do_action('hng_order_post_created', $order_id, $post_id, $order_data);

// Após adicionar nota
do_action('hng_order_note_added', $order_id, $note);

// Após alterar status
do_action('hng_order_status_changed', $order_id, $old_status, $new_status);
```

#### Checkout

```php
// Antes do formulário
do_action('hng_before_checkout_form');

// Antes da revisão do pedido
do_action('hng_before_order_review');

// Após formulário
do_action('hng_after_checkout_form');

// Checkout completo
do_action('hng_checkout_complete', $order, $post);
```

#### Pagamento

```php
// Pagamento confirmado
do_action('hng_payment_confirmed', $order_id, $payment_data);

// Pagamento vencido
do_action('hng_payment_overdue', $order_id, $payment_data);

// Pagamento falhou
do_action('hng_payment_failed', $order_id, $payment_data);

// Pagamento reembolsado
do_action('hng_payment_refunded', $order_id, $payment_data);

// Webhook recebido
do_action('hng_webhook_received', $gateway, $body, $headers);
```

#### Assinaturas

```php
// Status alterado
do_action('hng_subscription_status_changed', $sub_id, $old_status, $new_status);

// Assinatura cancelada
do_action('hng_subscription_cancelled', $sub_id, $immediately);

// Assinatura pausada
do_action('hng_subscription_paused', $sub_id);

// Assinatura retomada
do_action('hng_subscription_resumed', $sub_id);

// Pagamento de renovação
do_action('hng_subscription_renewal_payment', $sub_id, $order_data);

// Renovação manual
do_action('hng_subscription_manual_renewal', $sub_id, $order_id, $payment_method);
```

#### Agendamentos

```php
// Status alterado
do_action('hng_appointment_status_changed', $appt_id, $old_status, $new_status);
```

#### Produtos

```php
// Produto carregado
do_action('hng_product_loaded', $product_id, $data);

// Estoque reduzido
do_action('hng_product_stock_reduced', $product_id, $qty, $new_stock);

// Estoque aumentado
do_action('hng_product_stock_increased', $product_id, $qty, $new_stock);

// Vendas incrementadas
do_action('hng_product_sales_incremented', $product_id, $qty, $new_total);
```

#### Frete

```php
// Antes do calculador
do_action('hng_before_shipping_calculator');

// Etiqueta gerada
do_action('hng_shipping_label_generated', $order_id, $result, $carrier);

// Código de rastreio salvo
do_action('hng_tracking_code_saved', $order_id, $tracking_code);
```

#### Clientes

```php
// Cliente registrado
do_action('hng_customer_registered', $user_id, $provider, $data);

// Cliente criado (Google)
do_action('hng_customer_created', $user_id, 'google');

// Perfil completado
do_action('hng_customer_profile_completed', $user_id, $client_type);
```

#### Segurança

```php
// Atividade suspeita
do_action('hng_suspicious_activity', $type, $data);

// Evento de log de segurança
do_action('hng_security_log_event', $event_data);

// Cache limpo
do_action('hng_cache_cleared');
```

### Filtros (apply_filters)

#### Carrinho

```php
// Dados do item antes de adicionar
$cart_item_data = apply_filters('hng_cart_item_data_before_add', $cart_item_data, $product_id, $qty, $variation_id, $variation);

// Conteúdo do carrinho
$cart = apply_filters('hng_cart_contents', $cart_contents, $cart_instance);
```

#### Pedidos

```php
// Totais calculados
$totals = apply_filters('hng_order_calculated_totals', $totals, $cart, $data);

// Dados antes de inserir
$order_data = apply_filters('hng_order_data_before_insert', $order_data, $cart, $data);

// Dados do item antes de inserir
$item_data = apply_filters('hng_order_item_data_before_insert', $item_data, $cart_item, $order_id);
```

#### Produtos

```php
// Dados do produto ao carregar
$data = apply_filters('hng_product_data_loaded', $data, $product_id);

// Preço do produto
$price = apply_filters('hng_product_price', $price, $product_id, $data);

// Novo estoque ao reduzir
$new_stock = apply_filters('hng_product_new_stock_on_reduce', $new_stock, $current, $qty, $product_id);

// Tipos de produto
$types = apply_filters('hng_product_types', []);
```

#### Taxas e Fees

```php
// Faixas de taxas
$tiers = apply_filters('hng_fee_tiers', $tiers);

// Taxas por gateway
$gateway_fees = apply_filters('hng_gateway_fees', $gateway_fees);
```

#### Frete

```php
// Métodos de frete
$methods = apply_filters('hng_shipping_methods', []);

// Usar cache?
$use_cache = apply_filters('hng_shipping_use_cache', true);

// Geração de etiqueta
$result = apply_filters('hng_shipping_generate_label', $result, $order, $carrier, $package_data);
```

#### Minha Conta

```php
// Itens do menu
$menu_items = apply_filters('hng_account_menu_items', $menu_items);
```

#### Gateway

```php
// Providers ativos
$providers = apply_filters('hng_gateway_active_providers', $providers);

// Capabilities
$caps = apply_filters('hng_gateway_capabilities_resolve', $caps, $provider_id);
```

#### Segurança

```php
// Dias de retenção de logs
$days = apply_filters('hng_security_log_retention_days', 90);
```

#### Utilitários

```php
// Sanitizar input
$input = apply_filters('hng_sanitize_input', $input, $type);

// Fontes de dados ativas
$sources = apply_filters('hng_active_data_sources', $sources);
```

---

## Shortcodes

### Lista de Shortcodes

```php
[hng_products]      // Lista de produtos
[hng_product]       // Produto único
[hng_cart]          // Carrinho
[hng_checkout]      // Checkout
[hng_my_account]    // Minha Conta
[hng_order_received] // Pedido recebido
```

### [hng_products]

Exibe uma grade de produtos.

**Atributos:**

| Atributo | Padrão | Descrição |
|----------|--------|-----------|
| `limit` | 12 | Número de produtos |
| `columns` | 4 | Colunas na grade |
| `orderby` | date | Ordenação (date, title, price, popularity) |
| `order` | DESC | ASC ou DESC |
| `category` | "" | Slug da categoria (separado por vírgula) |
| `featured` | no | Apenas destaques? |
| `on_sale` | no | Apenas em promoção? |

**Exemplos:**

```
[hng_products limit="8" columns="4"]
[hng_products category="eletronicos,informatica" limit="6"]
[hng_products featured="yes" limit="4" columns="2"]
[hng_products on_sale="yes" orderby="price" order="ASC"]
```

### [hng_product]

Exibe um único produto.

**Atributos:**

| Atributo | Descrição |
|----------|-----------|
| `id` | ID do produto (obrigatório) |

**Exemplo:**

```
[hng_product id="123"]
```

### [hng_cart]

Exibe o carrinho de compras.

```
[hng_cart]
```

### [hng_checkout]

Exibe o formulário de checkout.

```
[hng_checkout]
```

### [hng_my_account]

Exibe a área do cliente.

```
[hng_my_account]
```

### [hng_order_received]

Exibe a página de confirmação do pedido.

```
[hng_order_received]
```

---

## REST API

### Endpoints de Pagamento

O plugin registra endpoints REST para operações de pagamento:

```
GET /wp-json/hng/v1/payment/{action}
```

**Ações disponíveis:**

| Ação | Descrição | Parâmetros |
|------|-----------|------------|
| `status` | Verificar status do pagamento | `payment_id`, `gateway` |
| `create` | Criar pagamento | `order_id`, `payment_method`, `gateway` |
| `process` | Processar cartão de crédito | `order_id`, `card_data`, `gateway` |

**Exemplo - Verificar Status:**

```bash
GET /wp-json/hng/v1/payment/status?payment_id=pay_abc123&gateway=asaas
```

**Resposta:**

```json
{
  "success": true,
  "payment_id": "pay_abc123",
  "status": "CONFIRMED",
  "gateway": "asaas"
}
```

### Webhooks

**Endpoint para webhooks:**

```
POST /wp-json/hng/v1/webhook/{gateway}
```

**Gateways suportados:**
- `/webhook/asaas`
- `/webhook/mercadopago`
- `/webhook/pagseguro`
- `/webhook/pagarme`

---

## Templates

### Estrutura de Templates

Os templates ficam em `templates/` e podem ser sobrescritos no tema.

### Sobrescrevendo Templates

Copie o template para:
```
seu-tema/hng-commerce/nome-do-template.php
```

### Templates Disponíveis

| Template | Descrição |
|----------|-----------|
| `archive-product.php` | Arquivo de produtos |
| `single-product.php` | Página de produto |
| `content-product.php` | Card de produto na lista |
| `cart.php` | Página do carrinho |
| `checkout.php` | Página de checkout |
| `my-account.php` | Área do cliente |
| `order-received.php` | Confirmação do pedido |
| `payment-pix.php` | Formulário de pagamento PIX |
| `payment-boleto.php` | Formulário de boleto |
| `payment-credit-card.php` | Formulário de cartão |
| `shipping-calculator.php` | Calculador de frete |
| `tracking-page.php` | Página de rastreamento |
| `my-account-orders.php` | Lista de pedidos do cliente |
| `my-account-downloads.php` | Downloads do cliente |
| `my-account-subscriptions.php` | Assinaturas do cliente |
| `my-account-appointments.php` | Agendamentos do cliente |
| `my-account-pix-installments.php` | PIX Parcelado |

### Funções de Template

```php
<?php
// Carregar template
hng_get_template('cart.php');

// Carregar template com variáveis
hng_get_template('single-product.php', ['product' => $product]);

// Carregar template part
hng_get_template_part('content', 'product');
```

---

## Configurações

### Páginas de Configuração

O plugin adiciona um menu **HNG Commerce** no admin com:

- **Início** - Dashboard/Wizard
- **Financeiro** - Dashboard financeiro
- **Pedidos** - Gerenciamento de pedidos
- **Categorias** - Categorias de produtos
- **Relatórios** - Relatórios e analytics
- **Assinaturas** - Gerenciar assinaturas
- **Clientes** - Base de clientes
- **Agendamentos** - Gerenciar agendamentos
- **Profissionais** - Cadastro de profissionais
- **Orçamentos** - Gerenciar orçamentos
- **Frete** - Configurações de frete
- **Gateways** - Configurar gateways
- **E-mails** - Personalizar e-mails
- **Ferramentas** - Importação/Exportação
- **Configurações** - Configurações gerais

### Opções do Plugin

```php
<?php
// Configurações gerais
get_option('hng_commerce_settings', []);

// Moeda
get_option('hng_currency', 'BRL');
get_option('hng_currency_position', 'left_space');
get_option('hng_thousand_separator', '.');
get_option('hng_decimal_separator', ',');
get_option('hng_number_decimals', 2);

// Páginas
get_option('hng_page_loja');
get_option('hng_page_carrinho');
get_option('hng_page_checkout');
get_option('hng_page_minha-conta');

// Checkout
get_option('hng_enable_guest_checkout', 'yes');
get_option('hng_require_login_to_purchase', 'no');

// Gateway Asaas
get_option('hng_asaas_api_key');
get_option('hng_asaas_environment', 'sandbox');
get_option('hng_gateway_asaas_enabled', 'no');
get_option('hng_asaas_wallet_id');

// Métodos de pagamento
get_option('hng_enabled_payment_methods', ['pix', 'boleto', 'credit_card']);

// Debug
get_option('hng_enable_debug', false);

// Wizard
get_option('hng_setup_wizard_completed', false);
get_option('hng_setup_wizard_skipped', false);
```

---

## Funções Auxiliares

### Funções Globais

```php
<?php
// Instância do carrinho
$cart = hng_cart();

// Obter produto
$product = hng_get_product($id);

// Formatar preço
echo hng_price(99.90); // R$ 99,90

// Verificar se pode comprar
if (hng_customer_can_purchase()) { }

// URLs
$shop_url = hng_get_shop_url();
$cart_url = hng_get_cart_url();
$checkout_url = hng_get_checkout_url();
$account_url = hng_get_account_url();

// Verificar páginas
is_hng_page();      // Qualquer página do plugin
is_hng_shop();      // Página da loja
is_hng_product();   // Página de produto
is_hng_cart();      // Página do carrinho
is_hng_checkout();  // Página de checkout
is_hng_account();   // Página da conta

// Notices
hng_add_notice('Sucesso!', 'success');
hng_add_notice('Erro!', 'error');
$notices = hng_get_notices();
hng_print_notices();

// Validação
hng_validate_postcode('01310-100'); // true
hng_validate_cpf('123.456.789-00'); // true/false
hng_validate_cnpj('12.345.678/0001-00'); // true/false

// Formatação
$phone = hng_sanitize_phone('(11) 99999-9999'); // 11999999999
$formatted = hng_format_phone('11999999999'); // (11) 99999-9999

// Estados brasileiros
$states = hng_get_brazilian_states();

// Métodos de pagamento
$methods = hng_get_enabled_payment_methods();
$title = hng_get_payment_method_title('pix'); // PIX
$gateway_methods = hng_get_active_gateway_methods();

// Debug
hng_log('Mensagem de debug', 'info');
$is_dev = hng_is_dev_mode();
$is_debug = hng_is_debug_enabled();
```

---

## Segurança

### Rate Limiting

O plugin implementa rate limiting para prevenir abusos:

```php
<?php
// Classe: HNG_Rate_Limiter
$limiter = new HNG_Rate_Limiter();

// Verificar limite
if ($limiter->is_rate_limited($key)) {
    // Bloqueado
}

// Incrementar contador
$limiter->hit($key);
```

### Criptografia

Dados sensíveis são criptografados com AES-256-GCM:

```php
<?php
$crypto = HNG_Crypto::instance();

// Criptografar
$encrypted = $crypto->encrypt('dados sensíveis');

// Descriptografar
$decrypted = $crypto->decrypt($encrypted);
```

### Validação de Nonce

```php
<?php
// Verificar nonce
if (!hng_verify_nonce($_POST['nonce'], 'hng_checkout')) {
    wp_die('Erro de segurança');
}
```

### Log de Segurança

```php
<?php
// Registrar evento
$security = HNG_Security::instance();
$security->log_event('login_failed', [
    'user' => 'email@example.com',
    'ip' => $_SERVER['REMOTE_ADDR']
]);
```

### Boas Práticas Implementadas

1. **Sanitização de input** em todos os formulários
2. **Escape de output** em todas as saídas
3. **Prepared statements** para queries SQL
4. **Nonce verification** em ações sensíveis
5. **Capability checks** antes de operações admin
6. **Rate limiting** em endpoints sensíveis
7. **Criptografia** de credenciais de gateway
8. **Logs de auditoria** para operações críticas

---

## Banco de Dados

### Tabelas Criadas

O plugin cria as seguintes tabelas customizadas:

| Tabela | Descrição |
|--------|-----------|
| `{prefix}hng_orders` | Pedidos |
| `{prefix}hng_order_items` | Itens dos pedidos |
| `{prefix}hng_order_notes` | Notas dos pedidos |
| `{prefix}hng_customers` | Clientes CRM |
| `{prefix}hng_subscriptions` | Assinaturas |
| `{prefix}hng_appointments` | Agendamentos |
| `{prefix}hng_coupon_usage` | Uso de cupons |
| `{prefix}hng_security_log` | Log de segurança |
| `{prefix}hng_transactions` | Transações/Ledger |
| `{prefix}hng_pix_installments` | PIX Parcelado |
| `{prefix}hng_quote_messages` | Mensagens de orçamento |

### Helper de Banco

```php
<?php
// Obter nome completo da tabela
$table = hng_db_full_table_name('hng_orders');

// Backtick para queries
$table_sql = hng_db_backtick_table('hng_orders');
```

### Migrações

Migrações são executadas automaticamente na ativação/atualização:

```php
// database/migrations/create-coupon-usage-table.php
// database/migrations/create-security-log-table.php
// database/migrations/add-data-source-column.php
```

---

## Integração Elementor

### Carregamento

O plugin detecta automaticamente o Elementor e carrega os widgets:

```php
// includes/elementor/loader.php
add_action('elementor/init', 'hng_commerce_bootstrap_elementor', 20);
```

### Widgets Disponíveis

Os widgets ficam em `includes/elementor/widgets/`:

- Widget de Produtos
- Widget de Carrinho Mini
- Widget de Checkout
- Widget de Minha Conta
- Widget de Produto Único
- Widget de Categorias

### Registrando Widget Customizado

```php
<?php
use Elementor\Widget_Base;

class HNG_Elementor_Products_Widget extends Widget_Base {
    
    public function get_name() {
        return 'hng_products';
    }
    
    public function get_title() {
        return __('HNG Produtos', 'hng-commerce');
    }
    
    public function get_icon() {
        return 'eicon-products';
    }
    
    public function get_categories() {
        return ['hng-commerce'];
    }
    
    protected function register_controls() {
        // Controles do widget
    }
    
    protected function render() {
        // Output do widget
    }
}
```

---

## Sistema de E-mails

### Email Manager

```php
<?php
$email = HNG_Email::instance();

// Enviar e-mail de pedido
$email->send_order_email($order_id, 'new_order');

// Tipos disponíveis:
// - new_order
// - order_confirmed
// - order_shipped
// - order_completed
// - order_cancelled
// - payment_received
// - subscription_renewal
// - appointment_reminder
```

### Templates de E-mail

Templates em `templates/emails/`:

- `new-order.php`
- `order-confirmed.php`
- `order-shipped.php`
- `customer-new-account.php`
- `reset-password.php`
- `subscription-renewal.php`
- `appointment-reminder.php`

### Customização

Os e-mails podem ser personalizados em **HNG Commerce > E-mails**:

- Logo
- Cores
- Texto do rodapé
- Templates customizados

---

## Guia do Desenvolvedor

### Criando um Gateway Customizado

```php
<?php
class My_Custom_Gateway extends HNG_Payment_Gateway {
    
    public $id = 'my_gateway';
    public $title = 'Meu Gateway';
    public $supported_methods = ['pix', 'boleto'];
    
    public function __construct() {
        parent::__construct();
        $this->load_settings();
    }
    
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    public function process_payment($order_id, $payment_data) {
        $order = new HNG_Order($order_id);
        $method = $payment_data['method'];
        
        switch ($method) {
            case 'pix':
                return $this->create_pix_payment($order);
            case 'boleto':
                return $this->create_boleto_payment($order);
        }
        
        return new WP_Error('invalid_method', 'Método inválido');
    }
    
    private function create_pix_payment($order) {
        // Lógica de criação do PIX
        return [
            'success' => true,
            'qr_code' => 'base64...',
            'copy_paste' => 'pix://...',
        ];
    }
}

// Instanciar no init
add_action('init', function() {
    new My_Custom_Gateway();
});
```

### Criando um Método de Frete

```php
<?php
class My_Shipping_Method extends HNG_Shipping_Method {
    
    public $id = 'my_shipping';
    public $title = 'Minha Transportadora';
    
    public function is_enabled() {
        return get_option('hng_my_shipping_enabled', 'no') === 'yes';
    }
    
    public function calculate_shipping($package) {
        $destination = $package['destination']['postcode'];
        $items = $package['items'];
        
        // Calcular frete
        $rates = [];
        
        $rates[] = [
            'id' => 'my_shipping_standard',
            'method_id' => $this->id,
            'label' => 'Entrega Padrão',
            'cost' => 25.90,
            'delivery_time' => '5 a 10 dias úteis',
        ];
        
        return $rates;
    }
}

// Registrar método
add_filter('hng_shipping_methods', function($methods) {
    $methods['my_shipping'] = 'My_Shipping_Method';
    return $methods;
});
```

### Adicionando Tipo de Produto

```php
<?php
add_filter('hng_product_types', function($types) {
    $types['rental'] = [
        'label' => 'Aluguel',
        'description' => 'Produto para locação',
        'supports' => ['price', 'inventory', 'attributes'],
    ];
    return $types;
});
```

### Customizando Menu da Conta

```php
<?php
add_filter('hng_account_menu_items', function($items) {
    // Adicionar item
    $items['rewards'] = [
        'label' => 'Recompensas',
        'icon' => 'dashicons-star-filled',
    ];
    
    // Remover item
    unset($items['downloads']);
    
    // Reordenar
    return array_merge(
        ['dashboard' => $items['dashboard']],
        ['rewards' => $items['rewards']],
        $items
    );
});

// Renderizar conteúdo
add_action('hng_account_rewards', function() {
    echo '<h2>Suas Recompensas</h2>';
    // Conteúdo...
});
```

---

## Avaliações e Reviews

### HNG_Reviews

Sistema completo de avaliações de produtos com funcionalidades avançadas.

```php
<?php
// Obter instância
$reviews = HNG_Reviews::instance();

// Obter avaliações de um produto
$product_reviews = $reviews->get_product_reviews($product_id, [
    'status' => 'approved',
    'limit' => 10,
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Registrar avaliação
$review_id = $reviews->add_review($product_id, [
    'user_id' => get_current_user_id(),
    'rating' => 5,
    'title' => 'Excelente produto!',
    'comment' => 'Superou minhas expectativas',
    'verified_purchase' => true,
]);

// Aprovar/rejeitar avaliação
$reviews->approve_review($review_id);
$reviews->reject_review($review_id);

// Obter estatísticas
$stats = $reviews->get_product_stats($product_id);
// Retorna: rating_average, total_reviews, rating_distribution
```

### Hooks de Reviews

```php
// Antes de adicionar avaliação
do_action('hng_review_before_add', $product_id, $review_data);

// Após adicionar avaliação
do_action('hng_review_added', $review_id, $product_id);

// Filtro de avaliações
$reviews = apply_filters('hng_product_reviews', $reviews, $product_id);
```

---

## Lista de Desejos (Wishlist)

### HNG_Wishlist

Gerenciador de lista de desejos com sincronização entre dispositivos.

```php
<?php
// Obter instância
$wishlist = HNG_Wishlist::instance();

// Adicionar à lista
$wishlist->add_to_wishlist($product_id, get_current_user_id());

// Verificar se está na lista
$is_wishlisted = $wishlist->is_in_wishlist($product_id, get_current_user_id());

// Remover da lista
$wishlist->remove_from_wishlist($product_id, get_current_user_id());

// Obter lista de usuário
$user_wishlist = $wishlist->get_user_wishlist(get_current_user_id());

// Contar itens
$count = $wishlist->get_wishlist_count(get_current_user_id());

// Compartilhar lista
$share_token = $wishlist->generate_share_token(get_current_user_id());
$share_url = $wishlist->get_wishlist_share_url($share_token);
```

### Shortcodes Wishlist

```
[hng_wishlist] – Exibir lista de desejos do usuário
[hng_wishlist_button product_id="123"] – Botão de adicionar à lista
[hng_wishlist_count] – Mostrar contagem
```

### Hooks de Wishlist

```php
do_action('hng_wishlist_added', $product_id, $user_id);
do_action('hng_wishlist_removed', $product_id, $user_id);
$is_allowed = apply_filters('hng_user_can_wishlist', true, $user_id);
```

---

## Compatibilidade e Atualizações

### Sistema de Compatibilidade

O plugin implementa um sistema inteligente de compatibilidade para evitar quebras de versão:

#### HNG_Commerce_Compatibility

Compatibilidade com versões antigas (1.0.x - 1.1.x)

```php
<?php
// Detecta e adapta dados legados
$compatibility = new HNG_Commerce_Compatibility();
$compatibility->migrate_old_settings();
$compatibility->migrate_old_metadata();
```

#### HNG_WP68_Compatibility

Compatibilidade com WordPress 6.8+ (nova na v1.2.14+)

```php
<?php
// Carregado automaticamente
// Resolve problemas conhecidos:
// - Deprecações de funções
// - Mudanças em hooks
// - Novos requisitos de segurança
```

#### Sistema de Atualização Automática

```php
<?php
// Instância
$updater = HNG_Commerce_Updater::instance();

// Verificar atualizações
$has_updates = $updater->check_updates();

// Executar atualização
if ($has_updates) {
    $result = $updater->run_update();
    // Inclui migrações de banco de dados
}
```

### Constantes de Compatibilidade

```php
// Definidas automaticamente
define('HNG_COMMERCE_VERSION', '1.2.16');
define('HNG_COMMERCE_MIN_PHP', '7.4');
define('HNG_COMMERCE_MIN_WP', '5.8');

// Com proteção contra redefinição
if (!defined('HNG_COMMERCE_MIN_PHP')) {
    define('HNG_COMMERCE_MIN_PHP', '7.4');
}
```

### Checklist de Compatibilidade

**Ao atualizar para 1.2.16, verifique:**

- ✅ PHP 7.4+ instalado
- ✅ WordPress 5.8+ atualizado
- ✅ Gateways de pagamento reconfigurados
- ✅ Templates customizados (se houver)
- ✅ Plugins que estendem HNG Commerce atualizados
- ✅ Database backup realizado antes da atualização

### Migrações de Dados

Executadas automaticamente na ativação/atualização:

```
database/migrations/
├── add-data-source-column.php
├── create-coupon-usage-table.php
├── create-security-log-table.php
└── (e outras...)
```

---

## Novidades na v1.2.16

### Novas Classes (15+)
- `HNG_Reviews` - Sistema completo de avaliações
- `HNG_Wishlist` - Lista de desejos
- `HNG_Payment_Orchestrator` - Orquestração de pagamentos
- `HNG_Digital_Product` - Tipo específico para produtos digitais
- `HNG_Fee_Calculator` - Cálculo automático de taxas
- `HNG_PIX_Manager` - Gerenciador avançado de PIX
- `HNG_Security_Headers` - Headers de segurança HTTP
- `HNG_Domain_Heartbeat` - Verificação de saúde do domínio
- `HNG_Commerce_Updater` - Sistema de atualização
- E mais...

### Melhorias
- Admin reconstruído após merge (melhor organização)
- Compatibilidade WordPress 6.8+ nativa
- Performance otimizada
- Security headers automáticos
- Sistema de atualização robusto

### Deprecações
- Nenhuma (compatibilidade total com 1.2.12)

---

## Troubleshooting

### Problemas Comuns

#### 1. Página em branco após ativação

**Causa:** Conflito de versão PHP ou dependência ausente.

**Solução:**
```php
// Verificar logs de erro
WP_DEBUG = true;
WP_DEBUG_LOG = true;
```

#### 2. Gateway não processa pagamento

**Causa:** Credenciais inválidas ou ambiente incorreto.

**Solução:**
1. Verificar API key
2. Confirmar ambiente (sandbox/production)
3. Verificar logs em `logs/gateways.log`

#### 3. Frete não calcula

**Causa:** CEP inválido ou serviço offline.

**Solução:**
1. Verificar CEP de origem nas configurações
2. Testar CEP manualmente
3. Verificar se método está habilitado

#### 4. E-mails não enviados

**Causa:** Configuração SMTP incorreta.

**Solução:**
1. Usar plugin de SMTP (GoSMTP, WP Mail SMTP)
2. Verificar logs de e-mail

#### 5. Erro "Call to undefined function"

**Causa:** Arquivo ausente ou conflito de carregamento.

**Solução:**
1. Reinstalar plugin
2. Verificar ordem de carregamento
3. Limpar cache

### Modo Debug

Ativar debug para diagnóstico:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Ou via opção do plugin
update_option('hng_enable_debug', true);
```

### Logs

Logs são salvos em `wp-content/plugins/hng-commerce/logs/`:

- `hng.log` - Log geral
- `gateways.log` - Logs de gateway
- `elementor.log` - Logs do Elementor
- `webhook.log` - Logs de webhooks

### Limpeza de Cache

```php
// Via código
HNG_Commerce()->force_cache_clear();

// Via URL (admin logado)
/wp-admin/admin.php?page=hng-commerce&hng_clear_cache=1
```

### Suporte

- **Documentação:** https://hngdesenvolvimentos.com.br/docs
- **GitHub Issues:** https://github.com/hng/hng-commerce/issues
- **Email:** suporte@hngdesenvolvimentos.com.br

---

## Changelog

### 1.2.16 (Atual - Fevereiro 2026)
- ✨ **NOVO:** Sistema completo de Avaliações e Reviews (HNG_Reviews)
- ✨ **NOVO:** Lista de Desejos (HNG_Wishlist)
- ✨ **NOVO:** Orquestrador de Pagamentos (HNG_Payment_Orchestrator)
- ✨ **NOVO:** Compatibilidade nativa com WordPress 6.8+
- ✨ **NOVO:** Gerenciador PIX avançado (HNG_PIX_Manager)
- ✨ **NOVO:** Sistema de cálculo dinâmico de taxas (HNG_Fee_Calculator)
- ✨ **NOVO:** Headers de Segurança automáticos
- 🔧 **REFACTOR:** Admin reconstruído após merge
- 🔧 **REFACTOR:** Organização modular melhorada (15+ novas classes)
- 🛡️ **SEGURANÇA:** System heartbeat para verificação de saúde do domínio
- 🚀 **PERFORMANCE:** Otimizações gerais
- ⚙️ **SISTEMA:** Atualização automática e migrações robustas
- 📦 **NOVO:** Controllers e AJAX handlers organizados
- 🔄 **NOVO:** Compatibilidade com versões antigas (backward compatibility)

### 1.2.14
- Reconstrução pós-merge do módulo admin
- Sincronização AJAX para Asaas e PagSeguro

### 1.2.12
- Correções de compatibilidade com WordPress 6.8+
- Melhorias no sistema de webhooks
- Novos gateways adicionados

### 1.1.1
- Preparação para WordPress.org
- Nova página de categorias
- Correções de segurança

### 1.1.0
- Atualizações de segurança
- Validação de taxas server-side
- Suporte a wallet_id para split

### 1.0.0
- Lançamento inicial
- Sistema de produtos, carrinho, checkout
- Integração Asaas

---

## Licença

HNG Commerce é software livre distribuído sob a licença GPLv2 ou posterior.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

**HNG Desenvolvimentos** © 2025  
https://hngdesenvolvimentos.com.br
