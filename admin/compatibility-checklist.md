# Checklist de Validação de Compatibilidade do Admin HNG Commerce

Este checklist garante que o painel admin do HNG Commerce está robusto, isolado e compatível com os principais temas e plugins do mercado WordPress.

## 1. Isolamento de CSS/JS
- [x] Todos os estilos do admin estão dentro de `.hng-admin-panel` e prefixados com `.hng-`.
- [x] Nenhum estilo do admin vaza para o frontend ou outros plugins.
- [x] Scripts JS do admin estão encapsulados (IIFE) e usam namespace global `hngAdmin`.

## 2. Detecção de Conflitos
- [x] O sistema detecta plugins e temas populares (WooCommerce, Elementor, Yoast, Astra, etc).
- [x] O modo de compatibilidade é ativado automaticamente em caso de conflito.
- [x] O status do modo compatibilidade é exposto para JS e pode ser usado em hotfixes.

## 3. Carregamento Condicional de Assets
- [x] `compat.css` é carregado apenas quando necessário.
- [x] O hook `hng_admin_enqueue_compat` permite hotfixes rápidos sem editar o core.

## 4. Testes com Temas Populares
- [ ] Astra
- [ ] Hello Elementor
- [ ] OceanWP
- [ ] Storefront
- [ ] Blocksy
- [ ] Kadence

## 5. Testes com Plugins Populares
- [ ] WooCommerce
- [ ] Elementor
- [ ] Yoast SEO
- [ ] WP Rocket
- [ ] Autoptimize
- [ ] Contact Form 7

## 6. Testes de Funcionalidade
- [ ] Todos os botões, tabelas, inputs e modais funcionam e estão visíveis.
- [ ] Não há sobreposição de tooltips, modais ou menus.
- [ ] O dark mode do admin funciona mesmo com temas/plugins que alteram o body.

## 7. Testes de Performance
- [ ] O admin carrega rápido mesmo com muitos plugins ativos.
- [ ] Não há erros JS ou CSS no console.

## 8. Testes de Extensibilidade
- [ ] Hooks e filtros do admin funcionam normalmente.
- [ ] O hook `hng_admin_enqueue_compat` permite aplicar hotfixes sem editar o core.

---

**Dica:** Marque cada item conforme for testando em diferentes ambientes. Adicione observações e hotfixes necessários ao `compat.css` ou via hook.
