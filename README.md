# HNG Commerce

Plugin de e-commerce para WordPress focado no mercado brasileiro, com recursos de checkout, pagamentos (PIX, boleto e cartão), frete, integrações e gestão administrativa.

## Status

- Projeto preparado para GitHub
- Estrutura de plugin WordPress
- CI básica de sintaxe PHP via GitHub Actions

## Requisitos

- WordPress 5.8+
- PHP 7.4+

## Instalação local

1. Copie a pasta `hng-commerce` para `wp-content/plugins/`.
2. Ative o plugin no painel WordPress.
3. Configure as opções em `HNG Commerce` no admin.

## Desenvolvimento

### Validar sintaxe PHP

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

### Composer (opcional)

```bash
composer install
```

## Publicar no GitHub

No diretório do plugin, execute:

```bash
git add .
git commit -m "chore: init hng-commerce github project"
git remote add origin https://github.com/SEU_USUARIO/hng-commerce.git
git push -u origin main
```

## Licença

GPL-2.0-or-later (ver `LICENSE.txt`).
