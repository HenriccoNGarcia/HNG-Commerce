<?php
/**
 * HNG Crypto - Criptografia AES-256-GCM
 * 
 * Implementa criptografia de nível bancário para dados sensíveis:
 * - AES-256-GCM (autenticado)
 * - Rotação automática de chaves
 * - Armazenamento seguro
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helpers DB e arquivos
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-files.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-files.php';
}

class HNG_Crypto {
    
    /**
     * Método de criptografia
     */
    const CIPHER_METHOD = 'aes-256-gcm';
    
    /**
     * Tamanho da chave em bytes
     */
    const KEY_SIZE = 32; // 256 bits
    
    /**
     * Tamanho do IV (Initialization Vector)
     */
    const IV_SIZE = 12; // 96 bits (recomendado para GCM)
    
    /**
     * Tamanho da tag de autenticação
     */
    const TAG_SIZE = 16; // 128 bits
    
    /**
     * Instância única
     */
    private static $instance = null;
    
    /**
     * Chave mestra
     */
    private $master_key = null;
    
    /**
     * Obter instância
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        $this->load_master_key();
    }
    
    /**
     * Carregar chave mestra
     */
    private function load_master_key() {
        // Buscar chave do banco
        $key = get_option('hng_crypto_master_key');
        
        if (empty($key)) {
            // Gerar nova chave
            $this->master_key = $this->generate_key();
            update_option('hng_crypto_master_key', base64_encode($this->master_key), false);
            
            // Log de geração de chave
            do_action('hng_crypto_key_generated', $this->get_key_hash());
        } else {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Legitimate use for cryptographic key storage/retrieval
            $this->master_key = base64_decode($key);
        }
        
        // Validar integridade
        if (strlen($this->master_key) !== self::KEY_SIZE) {
            throw new Exception('Chave de criptografia corrompida');
        }
    }
    
    /**
     * Gerar chave aleatória
     */
    public function generate_key() {
        if (function_exists('random_bytes')) {
            return random_bytes(self::KEY_SIZE);
        } else {
            return openssl_random_pseudo_bytes(self::KEY_SIZE);
        }
    }
    
    /**
     * Criptografar dados
     * 
     * @param mixed $data Dados para criptografar
     * @return string|false Dados criptografados (base64) ou false em erro
     */
    public function encrypt($data) {
        try {
            // Serializar dados (permitir arrays/objetos)
            $data = is_string($data) ? $data : serialize($data);
            
            // Gerar IV único
            $iv = openssl_random_pseudo_bytes(self::IV_SIZE);
            
            // Tag de autenticação
            $tag = '';
            
            // Criptografar
            $ciphertext = openssl_encrypt(
                $data,
                self::CIPHER_METHOD,
                $this->master_key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                self::TAG_SIZE
            );
            
            if ($ciphertext === false) {
                $this->log_error('Falha na criptografia');
                return false;
            }
            
            // Empacotar: IV + Tag + Ciphertext
            $encrypted = $iv . $tag . $ciphertext;
            
            // Retornar em base64 para armazenamento
            return base64_encode($encrypted);
            
        } catch (Exception $e) {
            $this->log_error('Erro ao criptografar: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Descriptografar dados
     * 
     * @param string $encrypted_data Dados criptografados (base64)
     * @return mixed|false Dados originais ou false em erro
     */
    public function decrypt($encrypted_data) {
        try {
            // Decodificar base64
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Legitimate use for decrypting stored encrypted data
            $encrypted = base64_decode($encrypted_data, true);
            
            if ($encrypted === false) {
                $this->log_error('Dados criptografados inválidos');
                return false;
            }
            
            // Verificar tamanho mínimo
            $min_size = self::IV_SIZE + self::TAG_SIZE;
            if (strlen($encrypted) < $min_size) {
                $this->log_error('Dados criptografados muito curtos');
                return false;
            }
            
            // Desempacotar: IV + Tag + Ciphertext
            $iv = substr($encrypted, 0, self::IV_SIZE);
            $tag = substr($encrypted, self::IV_SIZE, self::TAG_SIZE);
            $ciphertext = substr($encrypted, self::IV_SIZE + self::TAG_SIZE);
            
            // Descriptografar
            $decrypted = openssl_decrypt(
                $ciphertext,
                self::CIPHER_METHOD,
                $this->master_key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($decrypted === false) {
                $this->log_error('Falha na descriptografia (dados corrompidos ou adulterados)');
                return false;
            }
            
            // Tentar deserializar
            $unserialized = @unserialize($decrypted);
            return $unserialized !== false ? $unserialized : $decrypted;
            
        } catch (Exception $e) {
            $this->log_error('Erro ao descriptografar: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criptografar número de cartão
     * 
     * @param string $card_number Número do cartão
     * @return string|false Token criptografado
     */
    public function encrypt_card($card_number) {
        // Remover espaços e caracteres especiais
        $card_number = preg_replace('/\D/', '', $card_number);
        
        // Validar formato básico
        if (strlen($card_number) < 13 || strlen($card_number) > 19) {
            $this->log_error('Número de cartão inválido');
            return false;
        }
        
        // Criptografar
        $encrypted = $this->encrypt($card_number);
        
        if ($encrypted) {
            // Log de tokenização (sem dados sensíveis)
            do_action('hng_card_tokenized', $this->get_card_hash($card_number));
        }
        
        return $encrypted;
    }
    
    /**
     * Descriptografar número de cartão
     * 
     * @param string $token Token criptografado
     * @return string|false Número do cartão
     */
    public function decrypt_card($token) {
        $card_number = $this->decrypt($token);
        
        if ($card_number && preg_match('/^\d{13,19}$/', $card_number)) {
            return $card_number;
        }
        
        return false;
    }
    
    /**
     * Obter últimos 4 dígitos do cartão
     * 
     * @param string $card_number Número completo
     * @return string últimos 4 dígitos
     */
    public function get_last_4_digits($card_number) {
        $card_number = preg_replace('/\D/', '', $card_number);
        return substr($card_number, -4);
    }
    
    /**
     * Mascarar número do cartão
     * 
     * @param string $card_number Número completo
     * @return string Número mascarado (ex: **** **** **** 1234)
     */
    public function mask_card_number($card_number) {
        $card_number = preg_replace('/\D/', '', $card_number);
        $last_4 = substr($card_number, -4);
        return '**** **** **** ' . $last_4;
    }
    
    /**
     * Rotacionar chave mestra
     * 
     * Re-criptografa todos os dados sensíveis com nova chave
     */
    public function rotate_keys() {
        global $wpdb;
        
        try {
            // Gerar nova chave
            $new_key = $this->generate_key();
            $old_key = $this->master_key;
            
            // Buscar todos os dados criptografados
            $encrypted_fields = [
                'hng_customer_payment_tokens' => ['token'],
                'hng_subscriptions' => ['payment_token'],
            ];
            
            $rotated_count = 0;
            
            foreach ($encrypted_fields as $table => $columns) {
                foreach ($columns as $column) {
                    // Validar identificadores via helper
                    $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name( $table ) : ($wpdb->prefix . hng_db_sanitize_identifier( $table ));
                    $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table( $table ) : ('`' . $table_full . '`');
                    $safe_column = hng_db_sanitize_identifier( $column );

                    if (empty($table_full) || empty($safe_column)) {
                        continue;
                    }

                    // Preparar nomes seguros para SELECT (com backticks)
                    $col_sql = hng_db_backtick_column( $safe_column );

                    // Construir query com nomes de tabela/coluna seguros
                    $sql_query = "SELECT id, {$col_sql} FROM {$table_sql} WHERE {$col_sql} IS NOT NULL AND {$col_sql} != %s";
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Nomes de tabela/coluna sanitizados via hng_db_backtick_*
                    $results = $wpdb->get_results( $wpdb->prepare( $sql_query, '' ) );

                    foreach ($results as $row) {
                        // Descriptografar com chave antiga
                        $encrypted_value = isset($row->{$safe_column}) ? $row->{$safe_column} : null;
                        if ($encrypted_value === null) continue;

                        $decrypted = $this->decrypt($encrypted_value);

                        if ($decrypted !== false) {
                            // Temporariamente usar nova chave
                            $this->master_key = $new_key;
                            $re_encrypted = $this->encrypt($decrypted);
                            $this->master_key = $old_key;

                            if ($re_encrypted) {
                                // Atualizar registro (usar nome de tabela sem backticks para $wpdb->update)
                                $wpdb->update(
                                    $table_full,
                                    [$safe_column => $re_encrypted],
                                    ['id' => $row->id],
                                    ['%s'],
                                    ['%d']
                                );

                                $rotated_count++;
                            }
                        }
                    }
                }
            }
            
            // Atualizar chave mestra
            $this->master_key = $new_key;
            update_option('hng_crypto_master_key', base64_encode($new_key), false);
            
            // Armazenar histórico
            $this->store_key_rotation_history();
            
            // Log
            do_action('hng_crypto_keys_rotated', $rotated_count);
            
            return $rotated_count;
            
        } catch (Exception $e) {
            $this->log_error('Erro na rotação de chaves: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Armazenar histórico de rotação
     */
    private function store_key_rotation_history() {
        $history = get_option('hng_crypto_rotation_history', []);
        
        $history[] = [
            'date' => current_time('mysql'),
            'key_hash' => $this->get_key_hash(),
            'user_id' => get_current_user_id(),
        ];
        
        // Manter apenas últimas 10 rotações
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        update_option('hng_crypto_rotation_history', $history, false);
    }
    
    /**
     * Obter hash da chave (para auditoria)
     */
    private function get_key_hash() {
        return hash('sha256', $this->master_key);
    }
    
    /**
     * Obter hash do cartáo (para auditoria)
     */
    private function get_card_hash($card_number) {
        return hash('sha256', $card_number);
    }
    
    /**
     * Log de erros
     */
    private function log_error($message) {
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/crypto.log', '[HNG Crypto] ' . $message . PHP_EOL);
        }

        // Trigger an action so other parts can react to crypto errors (no direct error_log fallback)
        do_action('hng_crypto_error', $message);
    }
    
    /**
     * Verificar se criptografia está disponível
     */
    public static function is_available() {
        if (!function_exists('openssl_encrypt')) {
            return false;
        }
        
        $ciphers = openssl_get_cipher_methods();
        if (!in_array(self::CIPHER_METHOD, $ciphers)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obter informações de diagnóstico
     */
    public function get_diagnostics() {
        return [
            'cipher_method' => self::CIPHER_METHOD,
            'key_size' => self::KEY_SIZE . ' bytes',
            'iv_size' => self::IV_SIZE . ' bytes',
            'tag_size' => self::TAG_SIZE . ' bytes',
            'key_hash' => $this->get_key_hash(),
            'available_ciphers' => openssl_get_cipher_methods(),
        ];
    }
}
