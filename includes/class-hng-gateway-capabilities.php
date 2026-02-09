<?php
/**
 * Gateway Capabilities Provider
 * Padroniza e fornece capacidades dos gateways/bancos para uso interno.
 */
if (!defined('ABSPATH')) { exit; }

interface HNG_Gateway_Capabilities_Interface {
    /**
     * Retorna array associativo de capacidades padronizadas do provedor.
     * Estrutura recomendada:
     * [
     *   'provider' => 'asaas',
     *   'version' => '1.0',
     *   'capabilities' => [
     *       'pix' => ['supported' => true, 'dynamic_qr' => true, 'expiration_control' => true],
     *       'boleto' => ['supported' => true, 'registration' => true, 'automatic_baixa' => true],
     *       'cartao' => ['supported' => true, '3ds' => true, 'antifraude' => 'basico'],
     *       'split' => ['native' => true, 'mode' => 'wallet'],
     *       'webhook' => ['hmac' => true, 'idempotency' => true, 'retry' => false],
     *       'refund' => ['partial' => true, 'pix' => true, 'cartao' => true],
     *       'settlement' => ['pix' => 'D+1', 'boleto' => 'D+1..D+3', 'cartao' => 'D+28']
     *   ]
     * ]
     */
    public static function get_capabilities();
}

class HNG_Gateway_Capabilities_Provider {
    /** TTL do cache em segundos */
    const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Obter capacidades de um provider (cache transient)
     */
    public static function get($provider_id) {
        $cache_key = 'hng_gateway_caps_' . sanitize_key($provider_id);
        $cached = get_transient($cache_key);
        if ($cached !== false) { return $cached; }

        $data = self::resolve_provider($provider_id);
        if (!empty($data)) {
            set_transient($cache_key, $data, self::CACHE_TTL);
        }
        return $data;
    }

    /**
     * Forï¿½a recarregamento (invalida cache)
     */
    public static function refresh($provider_id) {
        delete_transient('hng_gateway_caps_' . sanitize_key($provider_id));
        return self::get($provider_id);
    }

    /**
     * Lista de providers ativos (futuro: ler de configuraï¿½ï¿½es admin)
     */
    public static function list_active_providers() {
        $providers = ['asaas']; // Expandir dinamicamente depois
        // Filtro para permitir extensï¿½es
        return apply_filters('hng_gateway_active_providers', $providers);
    }

    /**
     * Resolve provider e retorna capacidades.
     */
    private static function resolve_provider($provider_id) {
        switch ($provider_id) {
            case 'asaas':
                if (class_exists('HNG_Gateway_Asaas') && method_exists('HNG_Gateway_Asaas', 'get_capabilities')) {
                    return HNG_Gateway_Asaas::get_capabilities();
                }
                return self::fallback_caps('asaas');
            default:
                /** Permitir que outros plugins registrem capabilities */
                return apply_filters('hng_gateway_capabilities_resolve', self::fallback_caps($provider_id), $provider_id);
        }
    }

    /** Fallback genï¿½rico caso gateway nï¿½o defina detalhes */
    private static function fallback_caps($provider_id) {
        return [
            'provider' => $provider_id,
            'version' => '1.0',
            'capabilities' => [
                'pix' => ['supported' => false],
                'boleto' => ['supported' => false],
                'cartao' => ['supported' => false],
                'split' => ['native' => false],
                'webhook' => ['hmac' => false, 'idempotency' => false, 'retry' => false],
                'refund' => ['partial' => false],
                'settlement' => []
            ]
        ];
    }
}

/** Helper global */
function hng_get_gateway_capabilities($provider_id) {
    return HNG_Gateway_Capabilities_Provider::get($provider_id);
}

/** Helper para verificar suporte rï¿½pido */
function hng_gateway_supports($provider_id, $feature, $subkey = null) {
    $caps = hng_get_gateway_capabilities($provider_id);
    if (!$caps || empty($caps['capabilities'][$feature])) { return false; }
    if ($subkey === null) { return !empty($caps['capabilities'][$feature]['supported']); }
    return isset($caps['capabilities'][$feature][$subkey]) ? $caps['capabilities'][$feature][$subkey] : false;
}
