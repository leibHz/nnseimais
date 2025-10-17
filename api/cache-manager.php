<?php
// ARQUIVO: api/cache-manager.php
// Sistema centralizado de gerenciamento de cache

class CacheManager {
    private $cache_dir;
    private $default_ttl = 1800; // 30 minutos
    
    public function __construct($cache_dir = null) {
        $this->cache_dir = $cache_dir ?? __DIR__ . '/../cache/';
        
        // Cria diretório de cache se não existir
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Obtém dados do cache
     */
    public function get($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Verifica se o cache expirou
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            $this->delete($key);
            return null;
        }
        
        return $data['value'] ?? null;
    }
    
    /**
     * Salva dados no cache
     */
    public function set($key, $value, $ttl = null) {
        $file = $this->getCacheFile($key);
        $ttl = $ttl ?? $this->default_ttl;
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];
        
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Remove item do cache
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Limpa todo o cache
     */
    public function clear() {
        $files = glob($this->cache_dir . '*.json');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Verifica se o cache existe e é válido
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Obtém ou define cache (callback pattern)
     */
    public function remember($key, $callback, $ttl = null) {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Gera nome do arquivo de cache
     */
    private function getCacheFile($key) {
        $safe_key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cache_dir . $safe_key . '.json';
    }
    
    /**
     * Obtém estatísticas do cache
     */
    public function getStats() {
        $files = glob($this->cache_dir . '*.json');
        $total = count($files);
        $valid = 0;
        $expired = 0;
        $total_size = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            $data = json_decode(file_get_contents($file), true);
            
            if (isset($data['expires_at']) && time() <= $data['expires_at']) {
                $valid++;
            } else {
                $expired++;
            }
        }
        
        return [
            'total_files' => $total,
            'valid' => $valid,
            'expired' => $expired,
            'total_size' => $total_size,
            'total_size_mb' => round($total_size / 1024 / 1024, 2)
        ];
    }
}
?>