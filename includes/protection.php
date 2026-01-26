<?php
/**
 * Website Protection Module
 * Rate limiting, brute force protection, and security monitoring
 */

session_start();

class WebsiteProtection {
    private $redis_enabled = false;
    private $rate_limit_enabled = true;
    private $max_attempts = 10;
    private $time_window = 3600; // 1 hour
    
    public function __construct() {
        // Check if Redis is available
        $this->redis_enabled = extension_loaded('redis');
    }
    
    /**
     * Check rate limit for IP address
     */
    public function checkRateLimit($action = 'general') {
        $ip = $this->getClientIP();
        $key = "ratelimit_{$action}_{$ip}";
        
        if ($this->redis_enabled) {
            return $this->checkRateLimitRedis($key);
        } else {
            return $this->checkRateLimitSession($key, $ip, $action);
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check rate limit using session
     */
    private function checkRateLimitSession($key, $ip, $action) {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
        }
        
        $data = &$_SESSION[$key];
        $now = time();
        
        // Reset if time window has passed
        if ($now - $data['first_attempt'] > $this->time_window) {
            $data = [
                'attempts' => 0,
                'first_attempt' => $now
            ];
        }
        
        $data['attempts']++;
        
        // Log the attempt
        $this->logSecurityEvent($action, $ip, $data['attempts']);
        
        // Check if limit exceeded
        if ($data['attempts'] > $this->max_attempts) {
            return false; // Rate limit exceeded
        }
        
        return true; // Within rate limit
    }
    
    /**
     * Check rate limit using Redis
     */
    private function checkRateLimitRedis($key) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            
            $attempts = $redis->incr($key);
            
            if ($attempts === 1) {
                $redis->expire($key, $this->time_window);
            }
            
            $redis->close();
            
            return $attempts <= $this->max_attempts;
        } catch (Exception $e) {
            // Fall back to session if Redis fails
            return $this->checkRateLimitSession($key, $this->getClientIP(), 'general');
        }
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF token as hidden input
     */
    public function getCSRFTokenInput() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->generateCSRFToken()) . '" />';
    }
    
    /**
     * Sanitize input
     */
    public function sanitizeInput($input, $type = 'text') {
        if (is_array($input)) {
            return array_map(function($value) use ($type) {
                return $this->sanitizeInput($value, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'text':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     */
    public function validatePasswordStrength($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special char
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        return preg_match($regex, $password) === 1;
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($action, $ip, $attempts) {
        $log_file = dirname(__DIR__) . '/storage/security.log';
        
        // Create log directory if it doesn't exist
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_entry = sprintf(
            "[%s] Action: %s | IP: %s | Attempts: %d | URI: %s\n",
            date('Y-m-d H:i:s'),
            $action,
            $ip,
            $attempts,
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        );
        
        error_log($log_entry, 3, $log_file);
    }
    
    /**
     * Check for suspicious patterns
     */
    public function checkSuspiciousPatterns($input) {
        $dangerous_patterns = [
            '/<script[^>]*>|<\/script>/i',
            '/onclick|onload|onerror|onmouseover|onkeypress|onchange|onsubmit/i',
            '/(union|select|insert|update|delete|drop|create|alter|exec|system|passthru)/i',
            '/base64|hex|eval|assert/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true; // Suspicious pattern detected
            }
        }
        
        return false;
    }
    
    /**
     * Get security status
     */
    public function getSecurityStatus() {
        return [
            'https_enabled' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'headers_set' => isset($_SERVER['HTTP_X_CONTENT_TYPE_OPTIONS']),
            'session_secure' => ini_get('session.cookie_secure') === '1',
            'session_httponly' => ini_get('session.cookie_httponly') === '1',
            'rate_limit_enabled' => $this->rate_limit_enabled,
            'redis_enabled' => $this->redis_enabled
        ];
    }
}

// Create global instance
if (!isset($GLOBALS['website_protection'])) {
    $GLOBALS['website_protection'] = new WebsiteProtection();
}
