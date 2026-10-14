<?php
error_reporting(0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

if(ob_get_length()) ob_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'upload_card') {
    // Check if visiting card is uploaded
    if (!isset($_FILES['visiting_card']) || $_FILES['visiting_card']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Visiting card file is required.']);
        exit;
    }

    $tmp_file = $_FILES['visiting_card']['tmp_name'];
    $original_name = $_FILES['visiting_card']['name'];
    $ext = pathinfo($original_name, PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, and PDF are allowed.']);
        exit;
    }

    // Save to temp location
    $temp_dir = __DIR__ . '/uploads/temp';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    $temp_filename = uniqid('card_') . '.' . $ext;
    $temp_path = $temp_dir . '/' . $temp_filename;
    
    if (!move_uploaded_file($tmp_file, $temp_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded visiting card.']);
        exit;
    }

    // Resize image using GD to save bandwidth if it's an image
    if ($ext !== 'pdf' && extension_loaded('gd') && function_exists('imagecreatefromjpeg')) {
        $img_info = @getimagesize($temp_path);
        if ($img_info) {
            list($width, $height, $type) = $img_info;
            $max_dim = 1200; // Good resolution for storage
            if ($width > $max_dim || $height > $max_dim) {
                $ratio = min($max_dim / $width, $max_dim / $height);
                $new_width = round($width * $ratio);
                $new_height = round($height * $ratio);
                $src = null;
                if ($type == IMAGETYPE_JPEG) $src = @imagecreatefromjpeg($temp_path);
                elseif ($type == IMAGETYPE_PNG) $src = @imagecreatefrompng($temp_path);
                
                if ($src) {
                    $dst = imagecreatetruecolor($new_width, $new_height);
                    // Handle transparency for PNG
                    if ($type == IMAGETYPE_PNG) {
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                    }
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagejpeg($dst, $temp_path, 80);
                    imagedestroy($src);
                    imagedestroy($dst);
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Visiting card uploaded successfully.', 'temp_file' => $temp_filename]);
    exit;
}
elseif ($action === 'register') {
    $shop_name       = trim($_POST['shop_name'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $mobile          = trim($_POST['mobile_no'] ?? '');
    $address_street  = trim($_POST['address_street'] ?? '');
    $address_city    = trim($_POST['address_city'] ?? '');
    $address_state   = trim($_POST['address_state'] ?? '');
    $address_pincode = trim($_POST['address_pincode'] ?? '');
    $gst_no          = trim($_POST['gst_no'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirm_pass    = $_POST['confirm_password'] ?? '';
    $verified_card   = trim($_POST['verified_card_file'] ?? '');
    
    // Required fields check
    if (empty($shop_name) || empty($name) || empty($username) || empty($mobile) || 
        empty($address_street) || empty($address_city) || empty($address_state) || empty($address_pincode) || 
        empty($password) || empty($confirm_pass)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }
    
    if (empty($verified_card)) {
        echo json_encode(['success' => false, 'message' => 'Please upload your visiting card first.']);
        exit;
    }

    $temp_path = __DIR__ . '/uploads/temp/' . $verified_card;
    if (!file_exists($temp_path)) {
        echo json_encode(['success' => false, 'message' => 'Uploaded visiting card not found. Please upload again.']);
        exit;
    }
    
    // Password match check
    if ($password !== $confirm_pass) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    
    if (strlen($username) < 3 || strlen($username) > 50 || !preg_match("/^[a-zA-Z0-9_\-\.]+$/", $username)) {
        echo json_encode(['success' => false, 'message' => 'Username must be 3-50 characters and alphanumeric.']);
        exit;
    }
    
    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number must be exactly 10 digits.']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }
    
    // Check if username or mobile already exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE username = ? OR mobile_no = ?");
    $stmt->execute([$username, $mobile]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or Mobile number already exists.']);
        exit;
    }
    
    // Move uploaded card to permanent directory
    $perm_dir = __DIR__ . '/uploads/visiting_cards';
    if (!is_dir($perm_dir)) {
        mkdir($perm_dir, 0777, true);
    }
    $safe_username = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);
    $ext = pathinfo($verified_card, PATHINFO_EXTENSION);
    $ext = strtolower($ext) ?: 'jpg';
    $perm_path = $perm_dir . '/' . $safe_username . '_' . time() . '.' . $ext;
    if (file_exists($temp_path)) {
        rename($temp_path, $perm_path);
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO customers (username, shop_name, name, mobile_no, password_hash, address_street, address_city, address_state, address_pincode, gst_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $shop_name, $name, $mobile, $hash, $address_street, $address_city, $address_state, $address_pincode, $gst_no ?: null]);
        $customer_id = $pdo->lastInsertId();
        
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['customer_username'] = $username;
        $_SESSION['customer_name'] = $name; // Optional: store full name in session
        $_SESSION['is_new_registration'] = true;
        $_SESSION['visiting_card_url'] = 'uploads/visiting_cards/' . basename($perm_path);
        
        // Set Remember Me cookie
        $token = base64_encode($customer_id . ':' . $hash);
        setcookie('remember_customer', $token, time() + (86400 * 30), "/"); // 30 days
        
        // Send email notification to admins
        try {
            $stmt_admins = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' AND is_active = 1");
            $stmt_admins->execute();
            $admin_emails = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($admin_emails)) {
                $admin_emails = [];
            }
            if (!in_array('catlouge.silvee@gmail.com', $admin_emails)) {
                $admin_emails[] = 'catlouge.silvee@gmail.com';
            }
            
            if (!empty($admin_emails)) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                $website_url = $protocol . $host . rtrim($current_dir, '/');
                
                $full_address = $address_street . ', ' . $address_city . ', ' . $address_state . ' ' . $address_pincode;
                
                $to = implode(', ', $admin_emails);
                $subject = "New Customer Registration: " . $name;
                $message = "'{$name}' is registered on our website '{$website_url}' . his store name is '{$shop_name}, {$full_address}'";
                send_smtp_email($to, $subject, $message);
            }
        } catch (Exception $mailEx) {
            // Silence exceptions so registration flow remains fully active in local mock setups
        }
        
        // Construct WhatsApp Redirect URL
        $wa_number = '917030033819'; // default
        try {
            $stmt_wa = $pdo->prepare("SELECT setting_value FROM theme_settings WHERE setting_key = 'whatsapp_number' LIMIT 1");
            $stmt_wa->execute();
            $db_wa = $stmt_wa->fetchColumn();
            if (!empty($db_wa)) {
                $wa_number = $db_wa;
            }
        } catch (Exception $e) {}
        
        $full_address = $address_street . ', ' . $address_city . ', ' . $address_state . ' ' . $address_pincode;
        $wa_text = "Hello, I am {$name} from {$shop_name} located at {$full_address}.\nI have successfully completed my registration process using my shop business card and contact details on your website.";
        $wa_redirect = "https://wa.me/{$wa_number}?text=" . rawurlencode($wa_text);
        
        echo json_encode(['success' => true, 'message' => 'Registration successful!', 'wa_redirect' => $wa_redirect]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
    exit;
} 
elseif ($action === 'login') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Both fields are required.']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE username = ? OR mobile_no = ?");
    $stmt->execute([$identifier, $identifier]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer && password_verify($password, $customer['password_hash'])) {
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_username'] = $customer['username'];
        
        // Set Remember Me cookie
        $token = base64_encode($customer['id'] . ':' . $customer['password_hash']);
        setcookie('remember_customer', $token, time() + (86400 * 30), "/"); // 30 days
        
        echo json_encode(['success' => true, 'message' => 'Login successful!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid login credentials.']);
    }
    exit;
}
elseif ($action === 'clear_new_registration_flag') {
    if (isset($_SESSION['is_new_registration'])) {
        unset($_SESSION['is_new_registration']);
        unset($_SESSION['visiting_card_url']);
    }
    echo json_encode(['success' => true]);
    exit;
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
