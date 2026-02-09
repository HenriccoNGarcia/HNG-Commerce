<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Signature {
    public static function is_available(): bool {
        return function_exists('sodium_crypto_sign_verify_detached');
    }

    public static function get_public_key(): string {
        $pk = '';
        if (defined('HNG_API_SIGN_PUBLIC_KEY_B64') && HNG_API_SIGN_PUBLIC_KEY_B64) {
            $pk = base64_decode(HNG_API_SIGN_PUBLIC_KEY_B64, true) ?: '';
        }
        if (!$pk) {
            $opt = get_option('hng_api_sign_public_key_b64');
            if (!empty($opt)) {
                $pk = base64_decode($opt, true) ?: '';
            }
        }
        return is_string($pk) ? $pk : '';
    }

    public static function canonical_json(array $data): string {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function verify_signed_block($signed, array $expected_subset = [], int $leeway = 30) {
        if (!is_array($signed)) {
            return new WP_Error('invalid_signed_block', 'Bloco assinado ausente ou inválido');
        }
        $pk = self::get_public_key();
        if (strlen($pk) !== 32 || !self::is_available()) {
            // Sem chave configurada, não bloquear; registrar aviso
            self::log('verify_signed_block', 'Public key ausente ou sodium indisponível; aceitando sem verificação.');
            return $signed['payload'] ?? [];
        }
        $payload = $signed['payload'] ?? null;
        $sig_b64 = $signed['signature'] ?? null;
        if (!is_array($payload) || empty($sig_b64)) {
            return new WP_Error('invalid_signature_format', 'Formato de assinatura inválido');
        }
        $sig = base64_decode($sig_b64, true);
        if ($sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return new WP_Error('invalid_signature_bytes', 'Assinatura inválida');
        }
        $msg = self::canonical_json($payload);
        $ok = sodium_crypto_sign_verify_detached($sig, $msg, $pk);
        if (!$ok) {
            return new WP_Error('signature_mismatch', 'Assinatura não confere');
        }
        // Valida janela de tempo
        $iat = intval($payload['issued_at'] ?? 0);
        $ttl = intval($payload['ttl'] ?? 0);
        $now = time();
        if ($iat > 0 && $ttl > 0) {
            if ($now + $leeway < $iat || $now - $leeway > ($iat + $ttl)) {
                return new WP_Error('signature_expired', 'Assinatura expirada ou fora da janela');
            }
        }
        // Checa campos esperados
        foreach ($expected_subset as $k => $v) {
            if (!array_key_exists($k, $payload)) {
                return new WP_Error('payload_missing', 'Campo obrigatório ausente: ' . $k);
            }
            if ($v !== null && $payload[$k] !== $v) {
                return new WP_Error('payload_mismatch', 'Campo divergente: ' . $k);
            }
        }
        return $payload;
    }

    public static function verify_jwt_eddsa(string $token) {
        $pk = self::get_public_key();
        if (strlen($pk) !== 32 || !self::is_available()) {
            self::log('verify_jwt_eddsa', 'Public key ausente ou sodium indisponível; aceitando sem verificação.');
            return self::decode_jwt_payload_unverified($token);
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return new WP_Error('invalid_jwt', 'Token inválido');
        }
        list($h64, $p64, $s64) = $parts;
        $msg = $h64 . '.' . $p64;
        $sig = self::b64url_decode($s64);
        if ($sig === false) {
            return new WP_Error('invalid_jwt_sig', 'Assinatura JWT inválida');
        }
        if (!sodium_crypto_sign_verify_detached($sig, $msg, $pk)) {
            return new WP_Error('jwt_signature_mismatch', 'Assinatura JWT não confere');
        }
        $payload_json = self::b64url_decode($p64);
        $payload = json_decode($payload_json, true);
        if (!is_array($payload)) {
            return new WP_Error('invalid_jwt_payload', 'Payload inválido');
        }
        $now = time();
        $nbf = intval($payload['nbf'] ?? 0);
        $exp = intval($payload['exp'] ?? 0);
        if (($nbf && $now < $nbf - 30) || ($exp && $now > $exp + 30)) {
            return new WP_Error('jwt_expired', 'Token expirado ou não ativo');
        }
        return $payload;
    }

    public static function decode_jwt_payload_unverified(string $token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) { return null; }
        $payload_json = self::b64url_decode($parts[1]);
        $payload = json_decode($payload_json, true);
        return is_array($payload) ? $payload : null;
    }

    private static function b64url_decode($data) {
        $replaced = strtr($data, '-_', '+/');
        $pad = strlen($replaced) % 4;
        if ($pad) { $replaced .= str_repeat('=', 4 - $pad); }
        return base64_decode($replaced, true);
    }

    private static function log($method, $message, $context = []) {
        $log_path = WP_CONTENT_DIR . '/plugins/hng-commerce/logs/signature.log';
        $dir = dirname($log_path);
        if (!file_exists($dir)) { wp_mkdir_p($dir); }
        $entry = sprintf('[%s] %s: %s', gmdate('Y-m-d H:i:s'), $method, $message);
        if (!empty($context)) {
            $entry .= "\nContext: " . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $entry .= "\n---\n";
        @error_log($entry, 3, $log_path);
    }
}
