<?php

$host = 'localhost';             
$db   = 'inventory';             
$user = 'root';                  
$pass = '';                      
$app_env = 'production';          

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Connect to target database
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $ex) {
        // If database doesn't exist yet in development/local setup, attempt initialization
        if ($app_env === 'development' || strpos($ex->getMessage(), 'Unknown database') !== false) {
            $pdo_init = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, $options);
            $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo = new PDO($dsn, $user, $pass, $options);
        } else {
            throw $ex;
        }
    }

    // Auto-check categories and product_designs schema in development mode only
    if ($app_env === 'development') {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'code'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE categories ADD COLUMN code VARCHAR(100) DEFAULT NULL AFTER name");
            }
            $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'labour_charge'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE categories ADD COLUMN labour_charge DECIMAL(10,2) DEFAULT 0.00");
            }
            $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'flat_rate'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE categories ADD COLUMN flat_rate DECIMAL(10,2) DEFAULT 0.00");
            }
            $stmt = $pdo->query("SHOW COLUMNS FROM product_designs LIKE 'is_hidden'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE product_designs ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (Exception $schemaEx) {
            // Fail silently if table doesn't exist yet
        }
    }

    // Auto-create customer_wishlists table for wishlist feature
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_wishlists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            design_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_wishlist_item (customer_id, design_id),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (design_id) REFERENCES product_designs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Silently ignore if table already exists or FK issues in early setup
    }

    // Auto-create blog tables and schema
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            short_description TEXT DEFAULT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'General',
            featured_image VARCHAR(255) DEFAULT NULL,
            content LONGTEXT NOT NULL,
            article_style VARCHAR(50) NOT NULL DEFAULT 'classic',
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            seo_title VARCHAR(255) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL,
            author_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log("Blog table creation error: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    if ($app_env === 'development') {
        die("DB Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    } else {
        die("A database connection error occurred. Please verify database setup in your Hostinger configuration.");
    }
}

// ----------------------------------------------------
// SECURE SESSION & COOKIE CONFIGURATION
// ----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie flags
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_admin'])) {
    $remember_token = $_COOKIE['remember_admin'];
    $decoded = base64_decode($remember_token, true);

    if ($decoded !== false && strpos($decoded, ':') !== false) {
        [$user_id, $expected_hash] = explode(':', $decoded, 2);
        $user_id = (int) $user_id;

        if ($user_id > 0 && !empty($expected_hash)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$user_id]);
                $remember_user = $stmt->fetch();

                if ($remember_user) {
                    $expected = hash_hmac('sha256', $remember_user['id'] . ':' . $remember_user['email'] . ':' . $remember_user['password_hash'], 'silvee-admin-session');
                    if (hash_equals($expected, $expected_hash)) {
                        $_SESSION['user_id'] = (int) $remember_user['id'];
                        $_SESSION['user_name'] = $remember_user['name'];
                    } else {
                        setcookie('remember_admin', '', time() - 3600, '/');
                    }
                }
            } catch (Exception $e) {
                setcookie('remember_admin', '', time() - 3600, '/');
            }
        }
    }
}

// ----------------------------------------------------
// AUTOMATED PORTABILITY ENGINE (SAFE BACKUP & RESTORE)
// ----------------------------------------------------
function backup_database_to_sql($pdo) {
    try {
        $sql = "-- Database Portability Backup\n";
        $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        foreach ($tables as $table) {
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql .= $row[1] . ";\n\n";
            
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                $cols = array_keys($rows[0]);
                $sql .= "INSERT INTO `$table` (" . implode(", ", array_map(function($c) { return "`$c`"; }, $cols)) . ") VALUES \n";
                
                $valuesList = [];
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($cols as $col) {
                        $val = $r[$col];
                        if ($val === null) {
                            $vals[] = "NULL";
                        } else {
                            $vals[] = $pdo->quote($val);
                        }
                    }
                    $valuesList[] = "(" . implode(", ", $vals) . ")";
                }
                $sql .= implode(",\n", $valuesList) . ";\n\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        $backup_paths = [
            __DIR__ . '/uploads/db_backup.sql',
        ];
        
        foreach ($backup_paths as $path) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($path, $sql);
        }
        return true;
    } catch (Exception $e) {
        error_log("Portability Engine - Backup Error: " . $e->getMessage());
        return false;
    }
}

function restore_database_from_sql($pdo) {
    $backup_file = __DIR__ . '/uploads/db_backup.sql';
    if (!file_exists($backup_file)) {
        return false;
    }
    
    try {
        $sql = file_get_contents($backup_file);
        if (!empty($sql)) {
            $pdo->exec($sql);
            return true;
        }
    } catch (Exception $e) {
        error_log("Portability Engine - Restore Error: " . $e->getMessage());
    }
    return false;
}

// Auto restore database in development if empty
if ($app_env === 'development') {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_NUM);
        if (empty($tables)) {
            restore_database_from_sql($pdo);
        }
    } catch (Exception $e) {
        // Fail silently
    }
}

// Handle Remember Me Persistent Login
if (!isset($_SESSION['customer_id']) && isset($_COOKIE['remember_customer'])) {
    $token = $_COOKIE['remember_customer'];
    $decoded = base64_decode($token);
    if (strpos($decoded, ':') !== false) {
        list($cid, $hash) = explode(':', $decoded, 2);
        if ($cid && $hash) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND password_hash = ?");
                $stmt->execute([$cid, $hash]);
                $customer = $stmt->fetch();
                if ($customer) {
                    $_SESSION['customer_id'] = $customer['id'];
                    $_SESSION['customer_username'] = $customer['username'];
                    $_SESSION['customer_name'] = $customer['name'];
                }
            } catch (Exception $e) {
                // Ignore DB error during cookie check
            }
        }
    }
}

/**
 * Dynamic, self-contained SMTP email dispatcher.
 */
function send_smtp_email($to, $subject, $body) {
    $smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtp_port = getenv('SMTP_PORT') ?: 465;
    $smtp_user = getenv('SMTP_USER') ?: 'catlouge.silvee@gmail.com'; 
    $smtp_pass = getenv('SMTP_PASS') ?: '';
    
    if (empty($smtp_pass)) {
        $headers = "From: " . $smtp_user . "\r\n" .
                   "Reply-To: " . $smtp_user . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();
        return @mail($to, $subject, $body, $headers);
    }
    
    $socket = @fsockopen("ssl://" . $smtp_host, $smtp_port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP Connection failed: $errstr ($errno)");
        return false;
    }
    
    $readResponse = function($socket) {
        $data = "";
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $data;
    };
    
    $sendCommand = function($socket, $cmd) use ($readResponse) {
        fputs($socket, $cmd . "\r\n");
        return $readResponse($socket);
    };
    
    $readResponse($socket);
    $sendCommand($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $sendCommand($socket, "AUTH LOGIN");
    $sendCommand($socket, base64_encode($smtp_user));
    $sendCommand($socket, base64_encode($smtp_pass));
    $sendCommand($socket, "MAIL FROM: <" . $smtp_user . ">");
    $sendCommand($socket, "RCPT TO: <" . $to . ">");
    $sendCommand($socket, "DATA");
    
    $headers = "From: \"Silvee 925 Store\" <" . $smtp_user . ">\r\n" .
               "To: <" . $to . ">\r\n" .
               "Subject: " . $subject . "\r\n" .
               "MIME-Version: 1.0\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n" .
               "Content-Transfer-Encoding: 8bit\r\n\r\n" .
               $body . "\r\n.";
               
    $sendCommand($socket, $headers);
    $sendCommand($socket, "QUIT");
    fclose($socket);
    return true;
}
