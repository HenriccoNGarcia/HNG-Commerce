<?php
/**
 * HNG Password - Hashing Argon2id
 * 
 * Implementa hashing de senhas com Argon2id:
 * - Resistente a ataques GPU
 * - Resistente a ataques side-channel
 * - Configurï¿½vel (tempo, memï¿½ria, paralelismo)
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Password {
    
    /**
     * Custo de memï¿½ria (64 MB)
     */
    const MEMORY_COST = 65536; // 64 * 1024 KiB
    
    /**
     * Custo de tempo (4 iteraï¿½ï¿½es)
     */
    const TIME_COST = 4;
    
    /**
     * Threads paralelos
     */
    const THREADS = 2;
    
    /**
     * Tamanho mï¿½nimo de senha
     */
    const MIN_LENGTH = 8;
    
    /**
     * Tamanho mï¿½ximo de senha
     */
    const MAX_LENGTH = 128;
    
    /**
     * Instï¿½ncia ï¿½nica
     */
    private static $instance = null;
    
    /**
     * Obter instï¿½ncia
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
        // Verificar disponibilidade
        if (!$this->is_argon2id_available()) {
            add_action('admin_notices', [$this, 'admin_notice_argon2id']);
        }
    }
    
    /**
     * Hash de senha
     * 
     * @param string $password Senha em texto plano
     * @return string|false Hash da senha
     */
    public function hash($password) {
        try {
            // Validar senha
            if (!$this->validate_password($password)) {
                return false;
            }
            
            // Usar Argon2id se disponï¿½vel
            if ($this->is_argon2id_available()) {
                $hash = password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => self::MEMORY_COST,
                    'time_cost' => self::TIME_COST,
                    'threads' => self::THREADS,
                ]);
            } else {
                // Fallback para bcrypt
                $hash = password_hash($password, PASSWORD_BCRYPT, [
                    'cost' => 12,
                ]);
            }
            
            if ($hash === false) {
                $this->log_error('Falha ao gerar hash');
                return false;
            }
            
            return $hash;
            
        } catch (Exception $e) {
            $this->log_error('Erro ao criar hash: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar senha
     * 
     * @param string $password Senha em texto plano
     * @param string $hash Hash armazenado
     * @return bool True se vï¿½lida
     */
    public function verify($password, $hash) {
        try {
            if (empty($password) || empty($hash)) {
                return false;
            }
            
            $valid = password_verify($password, $hash);
            
            // Verificar se precisa rehash
            if ($valid && $this->needs_rehash($hash)) {
                do_action('hng_password_needs_rehash', $hash);
            }
            
            return $valid;
            
        } catch (Exception $e) {
            $this->log_error('Erro ao verificar senha: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se hash precisa ser atualizado
     * 
     * @param string $hash Hash atual
     * @return bool True se precisa atualizar
     */
    public function needs_rehash($hash) {
        if ($this->is_argon2id_available()) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
                'memory_cost' => self::MEMORY_COST,
                'time_cost' => self::TIME_COST,
                'threads' => self::THREADS,
            ]);
        } else {
            return password_needs_rehash($hash, PASSWORD_BCRYPT, [
                'cost' => 12,
            ]);
        }
    }
    
    /**
     * Rehash de senha
     * 
     * @param string $password Senha em texto plano
     * @param string $old_hash Hash antigo
     * @return string|false Novo hash ou false
     */
    public function rehash($password, $old_hash) {
        // Verificar senha atual primeiro
        if (!$this->verify($password, $old_hash)) {
            return false;
        }
        
        // Gerar novo hash
        return $this->hash($password);
    }
    
    /**
     * Validar senha
     * 
     * @param string $password Senha
     * @return bool True se vï¿½lida
     */
    private function validate_password($password) {
        if (empty($password)) {
            return false;
        }
        
        $length = strlen($password);
        
        if ($length < self::MIN_LENGTH) {
            return false;
        }
        
        if ($length > self::MAX_LENGTH) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar forï¿½a da senha
     * 
     * @param string $password Senha
     * @return array Score (0-4) e feedback
     */
    public function check_strength($password) {
        $score = 0;
        $feedback = [];
        
        // Comprimento
        $length = strlen($password);
        if ($length >= 8) $score++;
        if ($length >= 12) $score++;
        if ($length >= 16) $score++;
        
        // Complexidade
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;
        
        // Normalizar score (0-4)
        $score = min(4, floor($score / 2));
        
        // Feedback
        if ($length < 8) {
            $feedback[] = __('Use pelo menos 8 caracteres', 'hng-commerce');
        }
        
        if ($length < 12) {
            $feedback[] = __('Senhas mais longas sï¿½o mais seguras', 'hng-commerce');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $feedback[] = __('Adicione letras minï¿½sculas', 'hng-commerce');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $feedback[] = __('Adicione letras maiï¿½sculas', 'hng-commerce');
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $feedback[] = __('Adicione nï¿½meros', 'hng-commerce');
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $feedback[] = __('Adicione caracteres especiais (!@#$%)', 'hng-commerce');
        }
        
        // Verificar senhas comuns
        if ($this->is_common_password($password)) {
            $score = 0;
            $feedback[] = __('Esta senha ï¿½ muito comum. Escolha outra.', 'hng-commerce');
        }
        
        return [
            'score' => $score,
            'strength' => $this->get_strength_label($score),
            'feedback' => $feedback,
        ];
    }
    
    /**
     * Obter label de forï¿½a
     */
    private function get_strength_label($score) {
        $labels = [
            0 => __('Muito fraca', 'hng-commerce'),
            1 => __('Fraca', 'hng-commerce'),
            2 => __('Mï¿½dia', 'hng-commerce'),
            3 => __('Forte', 'hng-commerce'),
            4 => __('Muito forte', 'hng-commerce'),
        ];
        
        return $labels[$score] ?? $labels[0];
    }
    
    /**
     * Verificar se senha ï¿½ comum
     */
    private function is_common_password($password) {
        $common_passwords = [
            '12345678', 'password', '123456789', '12345', '1234567',
            'password1', '123456', '1234567890', '000000', 'qwerty',
            'abc123', '111111', '123123', 'admin', 'letmein',
        ];
        
        $password_lower = strtolower((string) $password);
        
        return in_array($password_lower, $common_passwords);
    }
    
    /**
     * Gerar senha aleatï¿½ria forte
     * 
     * @param int $length Tamanho (padrï¿½o: 16)
     * @return string Senha gerada
     */
    public function generate($length = 16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
        $chars_length = strlen($chars);
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $random_index = random_int(0, $chars_length - 1);
            $password .= $chars[$random_index];
        }
        
        // Garantir complexidade mï¿½nima
        if (!preg_match('/[a-z]/', $password) ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^a-zA-Z0-9]/', $password)) {
            // Regenerar se nï¿½o atender requisitos
            return $this->generate($length);
        }
        
        return $password;
    }
    
    /**
     * Verificar se Argon2id estï¿½ disponï¿½vel
     */
    private function is_argon2id_available() {
        return defined('PASSWORD_ARGON2ID');
    }
    
    /**
     * Aviso admin se Argon2id nï¿½o disponï¿½vel
     */
    public function admin_notice_argon2id() {
        echo '<div class="notice notice-warning">';
        echo '<p>';
        echo '<strong>' . esc_html_e('HNG Commerce:', 'hng-commerce') . '</strong>';
        echo esc_html_e('Argon2id nï¿½o estï¿½ disponï¿½vel. Usando bcrypt como fallback. Para mï¿½xima seguranï¿½a, compile PHP com --with-password-argon2.', 'hng-commerce');
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Obter informaï¿½ï¿½es do algoritmo atual
     */
    public function get_algorithm_info() {
        if ($this->is_argon2id_available()) {
            return [
                'algorithm' => 'Argon2id',
                'memory_cost' => self::MEMORY_COST . ' KiB',
                'time_cost' => self::TIME_COST . ' iterations',
                'threads' => self::THREADS,
                'recommended' => true,
            ];
        } else {
            return [
                'algorithm' => 'bcrypt',
                'cost' => 12,
                'recommended' => false,
                'note' => 'Argon2id indisponï¿½vel',
            ];
        }
    }
    
    /**
     * Log de erros
     */
    private function log_error($message) {
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/password.log', '[HNG Password] ' . $message . PHP_EOL);
        }

        do_action('hng_password_error', $message);
    }
}
