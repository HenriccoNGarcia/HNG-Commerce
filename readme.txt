=== HNG Commerce ===

Contributors: henricco, hngdesenvolvimentos

Tags: ecommerce, payments, pix, boleto, brazilian store, quotes, appointments, subscriptions, refund

Requires at least: 5.8

Tested up to: 6.9

Requires PHP: 7.4

Stable tag: 1.2.17

License: GPLv2 or later

License URI: https://www.gnu.org/licenses/gpl-2.0.html



Complete e-commerce solution for WordPress focused on the Brazilian market with PIX, Boleto, Credit Card, PIX installments, subscriptions, appointments, quotes, refund system and advanced financial dashboard.



== Description ==



HNG Commerce is a complete and modern e-commerce solution for WordPress, designed specifically for the Brazilian market. It offers full catalog, cart, checkout and order management functionalities, integrating with the most popular payment gateways and shipping providers in Brazil.



The plugin provides an advanced financial dashboard with sales reports, conversion analytics and overall site performance metrics. It also includes email customization and advanced integration with gateways that support webhooks and split payments.



**Important:** HNG Commerce has a mandatory transaction fee, processed through connection to our central API. Each sale passes through the API where fees are calculated and charged via payment split.



**Main benefits:**



- **8 Payment Gateways**: Native integrations with Asaas, Mercado Pago, PagSeguro, Pagar.me (Stone), Cielo, GetNet, Rede, and more

- **Multi-Gateway Support**: Automatic gateway detection and flexible payment routing

- **Shipping Integrations**: Correios (SEDEX, PAC) and Melhor Envio with multiple carriers

- **PIX Installments**: Split PIX payments into multiple installments without credit

- **Comprehensive Refund System**: Customer-initiated refunds with admin approval, automatic multi-gateway processing, refund history and status tracking

- **Fully Localized**: Complete Portuguese (pt_BR) interface with focus on Brazilian e-commerce compliance

- **Free & Unlimited**: No plugin license fees, no order limits (operational fees apply per transaction)

- **Modern Admin Interface**: Clean, intuitive dashboard with responsive design

- **Elementor Compatible**: Widgets for products, cart, checkout, quotes and refund requests

- **Advanced Customization**: Email templates, product custom fields, custom order statuses

- Complete financial dashboard and sales reports



== Features ==



**Catalog and Products:**



- Unlimited products (no monthly limit)

- Categories, tags and attributes

- Product variations (size, color, etc.)

- Custom fields for additional product information



**Cart and Checkout:**



- Responsive cart with real-time updates

- Streamlined checkout flow

- Discount coupons and pricing rules

- Shipping calculation at checkout



**Payments:**



- PIX, Boleto and Credit Card via multiple gateways

- **PIX Installments** - split PIX payments into multiple installments with interest

- Recurring payments for subscriptions

- Secure payment processing with SSL/TLS



**Orders and Customers:**



- Complete order management

- Customer database with purchase history

- Order notes and status tracking

- Email notifications for order updates



**Shipping:**



- Correios integration (SEDEX, PAC, etc.)

- Melhor Envio integration (multiple carriers)

- Shipping label generation

- Order tracking



**Financial Dashboard and Reports:**



- Sales dashboard with charts and metrics

- Financial analytics (revenue, fees, net income)

- Conversion reports (cart abandonment, checkout completion)

- Export reports to CSV

- GMV (Gross Merchandise Volume) tracking

- Gateway performance comparison



**Email Customization:**



- Customizable email templates

- Logo and branding options

- Email preview before sending

- Automatic order notifications



**Widgets and Shortcodes:**



- Elementor widgets for products, cart, checkout

- Shortcodes for quick theme integration

- Responsive design for all devices



**Refund Management System:**



- **Customer-Initiated Refunds**: Customers can request refunds directly from their account with order selection, amount, reason, description and evidence upload

- **Admin Review & Approval**: New admin meta box on order edit pages to approve, reject or review refund requests with rejection reason tracking

- **Multi-Gateway Processing**: Automatic refund processing through the correct payment gateway (Asaas, Mercado Pago, PagSeguro, Pagar.me, Cielo, GetNet, Rede, Stone)

- **Refund Request Tracking**: Complete refund history with status (pending, approved, completed, rejected) and timestamps

- **Customer Notifications**: Automatic email notifications for request status updates

- **Dashboard Insights**: Refund metrics in financial dashboard (total refunded, pending requests, approval rate)

- **Elementor Widget**: Customer-facing refund request form with past requests display



== Supported Product Types ==



- **Physical Products**: items shipped via carrier or post office, with shipping calculation (Correios, Melhor Envio, local integrations).

- **Digital Products**: downloads delivered after payment (files, e-books, courses) with access control and download limits.

- **Subscriptions / Recurring Products**: recurring sales with automatic billing (integration with Asaas, Mercado Pago). The plugin manages the subscription lifecycle, renewals and cancellations.

- **Services and Appointments**: time/date-based products (e.g.: consultations, on-site services) with calendar, time slot selection, capacity limits and booking confirmations.

- **Quotes / Budgets**: products that require price negotiation. Customers request a quote, the admin reviews and approves with final pricing, then the customer can proceed to payment.



== Custom Fields in Products ==



HNG Commerce allows you to add custom fields to products to capture additional information both by the administrator and by the customer at the time of purchase. Examples and capabilities:



- **Field types**: text, text area, number, select (dropdown), checkbox, date/time, file upload.

- **Visibility**: fields for administrator internal use (additional product information) and fields for display on the product and customer completion at checkout.

- **Per-field settings**: label, description, required/optional, validation (regex or type), placeholder and default values.

- **Use in variations**: fields can be applied to product variations when relevant (e.g.: size, color with specific additional field).

- **Practical examples**: engraving/monogram on products, information for ticket issuance, date/time for scheduled services, image upload for custom printing.

- **Exposure**: values filled in by the customer appear in the order summary, confirmation emails and on the order edit screen in admin.



Administrators can configure these fields in `HNG Commerce > Products > Custom Fields` and choose which products or categories they should appear in.



== Installation ==



= Automatic Installation =



1. In the WordPress dashboard, go to 'Plugins > Add New'

2. Click 'Upload Plugin' and select the generated .zip file

3. Activate the plugin

4. Go to 'HNG Commerce > Settings' to complete the configuration



== External Services ==



HNG Commerce integrates with various external services to provide payment processing, shipping calculations, and other functionalities. Below is a list of external services used by this plugin:



**Payment Gateways:**



- **Asaas** - Used for PIX, Boleto and Credit Card payments

  - When used: When customer selects Asaas as payment method during checkout

  - Data transmitted: Order details (amount, customer name, email, CPF/CNPJ), payment information

  - Terms of Service: https://www.asaas.com/termos-de-uso

  - Privacy Policy: https://www.asaas.com/politica-de-privacidade



- **Mercado Pago** - Used for PIX, Boleto and Credit Card payments

  - When used: When customer selects Mercado Pago as payment method during checkout

  - Data transmitted: Order details (amount, customer information), payment data

  - Terms of Service: https://www.mercadopago.com.br/ajuda/termos-e-condicoes_194

  - Privacy Policy: https://www.mercadopago.com.br/privacidade



- **PagBank/PagSeguro** - Used for PIX, Boleto and Credit Card payments

  - When used: When customer selects PagBank/PagSeguro as payment method during checkout

  - Data transmitted: Order details, customer information, payment data

  - Contracts: https://pagbank.com.br/para-voce/contratos

  - Privacy Policy: https://pagbank.com.br/politica-de-privacidade



- **Pagar.me (Stone)** - Used for Credit Card, PIX and Boleto payments

  - When used: When customer selects Pagar.me as payment method during checkout

  - Data transmitted: Order details, customer information, payment data

  - Terms of Service: https://pagar.me/termos-de-uso

  - Privacy Policy: https://pagar.me/politica-de-privacidade

- **Stripe** - Used for Credit Card payments (via Pagar.me integration)

  - When used: When customer selects Pagar.me gateway with Stripe card processing

  - Data transmitted: Credit card information (directly to Stripe, not stored on site)

  - Terms of Service: https://stripe.com/legal

  - Privacy Policy: https://stripe.com/privacy

- **Cielo** - Used for PIX, Credit Card and Debit Card payments

  - When used: When customer selects Cielo as payment method during checkout

  - Data transmitted: Order details, customer information, payment data

  - Terms of Service: https://www.cielo.com.br/

  - Privacy Policy: https://www.cielo.com.br/



**Shipping Services:**



- **Correios** (SIGEP/Pricing API) - Used for shipping calculation and label generation

  - When used: When calculating shipping costs during checkout or generating shipping labels

  - Data transmitted: Package dimensions, weight, origin/destination postal codes

  - Privacy Policy: https://www.correios.com.br/falecomoscorreios/politica-de-privacidade-e-notas-legais/



- **Melhor Envio** - Used for shipping calculation and label generation with multiple carriers

  - When used: When calculating shipping costs during checkout or generating shipping labels

  - Data transmitted: Package information, shipping addresses, order details

  - Terms of Service: https://melhorenvio.com.br/termos-de-uso

  - Privacy Policy: https://melhorenvio.com.br/politica-de-privacidade



**Other Services:**



- **Google Charts API** - Used for rendering analytics charts in the dashboard

  - When used: When viewing reports and analytics in the admin dashboard

  - Data transmitted: Aggregated sales data (no personal customer information)

  - Privacy Policy: https://policies.google.com/privacy



- **Google Maps API** - Used for address autocomplete and location services

  - When used: When entering addresses during checkout or in customer/order management

  - Data transmitted: Address information being entered, geolocation data

  - Privacy Policy: https://policies.google.com/privacy

- **HNG API Manager** - Used for fee calculation, split rules and transaction registration

  - When used: When calculating fees and registering transactions

  - Data transmitted: Order amount, merchant ID, gateway, payment method, order ID

  - Terms of Service: https://hngplugins.com/termos

  - Privacy Policy: https://hngplugins.com/privacidade

- **ViaCEP** - Postal code lookup for address autofill

  - When used: When customer types a CEP during checkout

  - Data transmitted: CEP (postal code)

  - Terms of Service: https://viacep.com.br

  - Privacy Policy: https://viacep.com.br

- **BrasilAPI** - Correios shipping data fallback

  - When used: When calculating shipping via Correios fallback

  - Data transmitted: Origin/destination CEP and package data

  - Terms of Service: https://brasilapi.com.br

  - Privacy Policy: https://brasilapi.com.br

- **CEPCerto** - Alternative CEP lookup

  - When used: When using alternative shipping calculator flow

  - Data transmitted: Origin/destination CEP

  - Terms of Service: https://www.cepcerto.com

  - Privacy Policy: https://www.cepcerto.com

  - When used: When generating labels and tracking shipments

  - Data transmitted: Recipient address, package data

  - Terms of Service: https://www.jadlog.com.br

  - Privacy Policy: https://www.jadlog.com.br



**HNG Commerce Central API** (optional):



- **api.hngdesenvolvimentos.com.br** - Used for fee calculation, license verification and integration operations

  - When used: During payment processing for fee calculation (split payment)

  - Data transmitted: merchant_id, plugin_version, order information (amount, product type, gateway)

  - Note: NO sensitive customer data (like credit card numbers) is sent to the central server

  - To disable: Remove API credentials in HNG Commerce > Settings



**Important Notes:**

- The plugin does NOT send sensitive customer information (credit card numbers) to external servers except to the payment processors themselves

- Payment data is transmitted securely using SSL/TLS encryption

- If you don't want the store to communicate with the central server, you can skip configuring the merchant/API or remove credentials in Settings

- All external service communications respect WordPress standards and best practices



If your site operates under privacy regulations (e.g., GDPR, LGPD), make sure to document the use of these third-party services in your own Privacy Policy.



== Privacy ==



HNG Commerce may send data to external services controlled by the developer (e.g., `api.hngdesenvolvimentos.com.br`) for fee calculation, license verification and other integration operations. Data sent is limited to what is necessary for these operations, such as:



- `merchant_id` (merchant identifier)

- `plugin_version` (plugin version)

- Order information when needed for fee calculation (amount, product type, gateway)



The plugin does NOT send sensitive customer information (such as credit card numbers) to the central server — this information remains only with the integrated payment providers. If the merchant does not want the store to communicate with the central server, they can choose not to configure the merchant/API integration or remove credentials in `HNG Commerce > Settings`.



Logs: the plugin may write a log file in `wp-content/hng-transactions.log` when the `hng_transaction_log` option is enabled. To preserve privacy, disable logging in settings or manually remove the file.



Debug mode / Logs opt-in: HNG Commerce provides an opt-in diagnostic option called **Debug Mode** accessible in `HNG Commerce > Settings`. When enabled (`option: hng_enable_debug`) the plugin can activate additional logs and diagnostic information useful for support. Important note:



- Logs and diagnostics are ONLY written when **Debug Mode** is enabled OR when `WP_DEBUG` is set to `true` in `wp-config.php`.

- By default, `Debug Mode` is disabled. Do not enable in production environments without necessity, and never expose sensitive data in logs.

- If the merchant does not want communications with external servers or log generation, do not configure API credentials and keep Debug Mode disabled.



Logging and debug options respect user privacy and should be managed by the site administrator.



If your site operates in a jurisdiction with privacy rules (e.g., LGPD, GDPR), make sure to document the use of third parties in your own Privacy Policy, informing the providers involved and the purpose of data transmission.



== Uninstall / Remove Data ==



When uninstalling the plugin (remove via Plugins → Delete), the `uninstall.php` included in the package allows complete removal of plugin data when the constant `HNG_COMMERCE_REMOVE_DATA` is defined and true.



To remove all data when uninstalling, temporarily add to `wp-config.php`:



```php

define('HNG_COMMERCE_REMOVE_DATA', true);

```



Then remove the plugin from the dashboard. The procedure will delete options related to the plugin and, if it exists, will attempt to remove custom tables created by the plugin. Use with caution — this action is irreversible.



If you prefer to preserve data after uninstallation, leave the constant undefined (default behavior) and the plugin will not delete the database.





== Frequently Asked Questions ==



= Does the plugin have a paid version or order limits? =



The plugin code is distributed for free under GPLv2 and there is no paid version of the source code. However, payment processing through the integrated API/services may apply a **mandatory fee per transaction** (split) that is passed on to HNG or the service provider. In other words: the plugin does not limit orders, but there is a mandatory operational fee within the payment flow that should be considered when operating the store.



= Do I need any technical knowledge to use it? =



The plugin was designed to be used by merchants without advanced knowledge. Some operations (API integration, server deployment) may require access to cPanel or technical support.



= How do I configure payment gateways? =



Go to 'HNG Commerce > Settings > Payments' and enter the keys/credentials for the gateways (Asaas, Mercado Pago, etc.). Use `config.local.php` on the server only for sensitive values if using the private API.



== Screenshots ==



1. Dashboard - Sales overview

2. Product management

3. Simplified checkout

4. Gateway configuration

5. Sales reports

6. Elementor integration



== Changelog ==



= 1.2.16 - 2026-02-09 =

* **NEW**: Comprehensive Refund Management System with multi-gateway support

* **NEW**: Customer refund request widget and form (Elementor compatible)

* **NEW**: Admin refund approval/rejection interface with evidence tracking

* **NEW**: Automatic refund processing for all 8 supported payment gateways

* **NEW**: Customer email notifications for refund status updates

* **ENHANCEMENT**: Improved WordPress Coding Standards compliance (i18n, escaping, security)

* **FIX**: Corrected placeholder ordering in translatable strings

* **FIX**: Enhanced output escaping in admin panels and email templates

* **FIX**: Updated timezone-aware date functions (gmdate vs date)

* **FIX**: Proper use of WordPress filesystem API for file operations

* **FIX**: Direct file access protection in utility scripts

* **SECURITY**: Improved input validation and output escaping across all modules



= 1.2.15 - 2026-02-08 =

* Minor bug fixes and stability improvements

* Enhanced error handling in payment processing



= 1.2.0 - 2026-01-15 =

* Quotes/Budget system with approval workflow

* Subscriptions and recurring payments

* Appointments booking system

* Advanced financial dashboard with metrics



= 1.1.1 - 2025-12-01 =

* Preparation for WordPress.org directory: i18n loaded and standardized readme

* New product category management page (CRUD)

* Admin menu reorganization with clear labels

* Security fixes and improvements (input sanitization, nonces, escaping)



= 1.1.0 - 2025-11-26 =

* Security updates and preparation for WordPress.org submission

* Improvement: server-side fee validation and webhook key rotation

* Adjustment: use of wallet_id returned by API for split payments



= 1.0.0 - 2025-11-22 =

* Initial release



== Upgrade Notice ==



= 1.2.16 =

Major update: Complete refund management system with multi-gateway support, improved security and WordPress Coding Standards compliance. Highly recommended update.



= 1.2.0 =

Major feature update: Quotes/Budgets, Subscriptions, Appointments and Financial Dashboard. Strongly recommended.



= 1.1.1 =

Security and i18n improvements; new category page; admin adjustments. Recommended to update.



= 1.1.0 =

Security update and payment flow improvements. Recommended to update.

