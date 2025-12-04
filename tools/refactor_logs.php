
<?php

// Increase memory limit for large log files
ini_set('memory_limit', '512M');

class LogRedactor {


    private $logDir;
    private $processedCount = 0;
    private $redactedCount = 0;
    private $errorCount = 0;
    private $patterns;

    public function __construct($logDirectory) {
        $this->logDir = realpath($logDirectory);
        
        if (!$this->logDir || !is_dir($this->logDir)) {
            throw new InvalidArgumentException("Invalid directory: $logDirectory");
        }
        
        if (!is_readable($this->logDir)) {
            throw new RuntimeException("Directory not readable: $this->logDir");
        }
        
        $this->setupPatterns();
    }


    private function setupPatterns() { 

        $this-> patterns = [ 

             // Email addresses
            '/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/' => '[REDACTED]',
            
            // JWT tokens (full redaction)
            '/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/' => '[REDACTED]',
            
            // Verification tokens in verificationData context (partial masking)
            '/(verificationData[^}]*["\']token["\']\s*[:=]\s*["\'])([a-fA-F0-9]{6})([a-fA-F0-9]+)(["\'])/i' => 'MASK_VERIFICATION_TOKEN',
            
            // Password hashes (full hash)
            '/\$argon2id?\$v=\d+\$m=\d+,t=\d+,p=\d+\$[A-Za-z0-9+\/=]+\$[A-Za-z0-9+\/=]+/' => '[REDACTED_HASH]',
            
            '/\[REDACTED\],t=\d+,p=\d+\$[A-Za-z0-9+\/=]+\$[A-Za-z0-9+\/=]+/' => '[REDACTED_HASH]',
            
            '/\$2[aby]\$\d{2}\$[A-Za-z0-9.\/]{53}/' => '[REDACTED_HASH]',
            
            '/(password["\']?\s*[:=]\s*["\']?)([^"\'}\s,]+)(["\'}},]?)/i' => '$1[REDACTED_HASH]$3',
            
            // API keys
            '/(api[_-]?key["\']?\s*[:=]\s*["\']?)([a-zA-Z0-9_-]{20,})(["\'}},]?)/i' => '$1[REDACTED]$3',
            
            // Bearer tokens
            '/Bearer\s+[a-zA-Z0-9._-]{20,}/' => 'Bearer [REDACTED]',
            
            // Access/refresh tokens
            '/(["\']?(?:access|refresh)_?token["\']?\s*[:=]\s*["\']?)([a-zA-Z0-9._-]{20,})(["\'}},]?)/i' => '$1[REDACTED]$3',
        ];
    }


    public function run() { 
        $files = $this->findLogFiles();
        
        if (empty($files)) {
            $this->output("No log files found in: {$this->logDir}");
            return;
        }
        
        $this->output("Found " . count($files) . " log file(s)");
        $this->output(str_repeat('-', 50));
        
        foreach ($files as $file) {
            $this->processFile($file);
        }
        
        $this->output(str_repeat('-', 50));
        $this->output("Complete: {$this->processedCount} processed, {$this->errorCount} errors");
        $this->output("Total redactions: {$this->redactedCount}");
    }

    private function findLogFiles() {
        $files = [];
        $handle = opendir($this->logDir);
        
        if (!$handle) {
            throw new RuntimeException("Cannot open directory: {$this->logDir}");
        }
        
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            
            $path = $this->logDir . DIRECTORY_SEPARATOR . $entry;
            
            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'log') {
                $files[] = $path;
            }
        }
        
        closedir($handle);
        return $files;
    }
    
    private function processFile($filepath) {
        $basename = basename($filepath);
        
        // Check if file is too large (> 500MB)
        $size = filesize($filepath);
        if ($size > 524288000) {
            $this->logError("File too large, skipping: $basename");
            return;
        }
        
        $fp = fopen($filepath, 'r+');
        if (!$fp) {
            $this->logError("Cannot open file: $basename");
            return;
        }
        
        if (!flock($fp, LOCK_EX)) {
            $this->logError("Cannot lock file: $basename");
            fclose($fp);
            return;
        }
        
        try {

            rewind($fp);
            $content = stream_get_contents($fp);
            
            if ($content === false) {
                throw new RuntimeException("Failed to read file");
            }
            
            $sanitized = $this->sanitize($content);
            
            if ($sanitized === $content) {
                $this->output("No changes needed: $basename");
                return;
            }
            
            // Create backup
            $backupPath = $filepath . '.bak';
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
            
            if (!copy($filepath, $backupPath)) {
                throw new RuntimeException("Backup creation failed");
            }
            
            if (filesize($backupPath) !== strlen($content)) {
                unlink($backupPath);
                throw new RuntimeException("Backup verification failed");
            }
            
            // Write atomically using temp file
            $tempFile = $filepath . '.tmp.' . getmypid();
            
            if (file_put_contents($tempFile, $sanitized, LOCK_EX) === false) {
                throw new RuntimeException("Failed to write temp file");
            }
            
            if (!rename($tempFile, $filepath)) {
                unlink($tempFile);
                throw new RuntimeException("Failed to replace original file");
            }
            
            chmod($filepath, 0644);
            
            $this->processedCount++;
            $this->output("Sanitized: $basename");
            
        } catch (Exception $e) {
            $this->logError("Error processing $basename: " . $e->getMessage());
            
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    
    private function sanitize($content) {
        $original = $content;
        
        foreach ($this->patterns as $pattern => $replacement) {
            $content = preg_replace_callback(
                $pattern,
                function($matches) use ($replacement) {
                    $this->redactedCount++;
                    
                    if ($replacement === 'MASK_VERIFICATION_TOKEN') {
                        $maskLength = strlen($matches[3]);
                        return $matches[1] . $matches[2] . str_repeat('*', $maskLength) . $matches[4];
                    }
                    
                    // Handle $1, $2, $3 replacements
                    if (strpos($replacement, '$') !== false) {
                        $result = $replacement;
                        for ($i = 0; $i < count($matches); $i++) {
                            $result = str_replace('$' . $i, $matches[$i], $result);
                        }
                        return $result;
                    }
                    
                    return $replacement;
                },
                $content
            );
        }
        
        return $content;
    }
    
    private function output($message) {
        echo $message . PHP_EOL;
    }
    
    private function logError($message) {
        fwrite(STDERR, "ERROR: $message" . PHP_EOL);
        $this->errorCount++;
    }
}



// Main execution
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from command line" . PHP_EOL);
    exit(1);
}

$logDir = $argv[1];


if ($logDir[0] !== '/') {
    $logDir = getcwd() . DIRECTORY_SEPARATOR . $logDir;
}

try {
    $sanitizer = new LogRedactor($logDir);
    $sanitizer->run();
    exit(0);
    
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . PHP_EOL);
    exit(1);
}