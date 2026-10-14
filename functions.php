<?php
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function e($str) {
    if ($str === null) return '';
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function get_category_path($pdo, $id) {
    static $cat_cache = null;
    if (!$id) return "-";
    
    if ($cat_cache === null) {
        try {
            $stmt = $pdo->query("SELECT id, name, parent_id FROM categories");
            $cat_cache = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cat_cache[$row['id']] = $row;
            }
        } catch (Exception $e) {
            $cat_cache = [];
        }
    }
    
    $path = [];
    $curr = $id;
    $visited = [];
    while ($curr && isset($cat_cache[$curr]) && !isset($visited[$curr])) {
        $visited[$curr] = true;
        $path[] = $cat_cache[$curr]['name'];
        $curr = $cat_cache[$curr]['parent_id'];
    }
    
    return !empty($path) ? implode(" > ", array_reverse($path)) : "-";
}

function sync_customer_cart($pdo) {
    if (!isset($_SESSION['customer_id'])) return;
    $cid = $_SESSION['customer_id'];
    try {
        $pdo->prepare("DELETE FROM customer_carts WHERE customer_id = ?")->execute([$cid]);
        if (!empty($_SESSION['cart'])) {
            $insert_stmt = $pdo->prepare("INSERT INTO customer_carts (customer_id, product_id, design_id, quantity) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
            $ids = array_keys($_SESSION['cart']);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $lookup = $pdo->prepare("SELECT id, product_id FROM product_designs WHERE id IN ($placeholders)");
            $lookup->execute($ids);
            $prod_map = [];
            foreach ($lookup->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prod_map[$row['id']] = $row['product_id'];
            }
            foreach($_SESSION['cart'] as $item) {
                $did = $item['id'];
                $qty = $item['qty'];
                if (isset($prod_map[$did])) {
                    $insert_stmt->execute([$cid, $prod_map[$did], $did, $qty]);
                }
            }
        }
    } catch (Exception $e) { }
}

function blog_slugify($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', '', $text);
    $text = preg_replace('/[^a-z0-9\s-]+/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function blog_normalize_content($content) {
    // Some older image/content builders stored the two visible characters
    // "\\n" instead of a real line break. Convert those legacy markers so
    // they never appear in an article or in the blog editor.
    $content = str_replace(['\\r\\n', '\\n', '\\r'], ["\n", "\n", "\n"], (string)$content);
    $content = preg_replace('/<\?xml[^>]*>/i', '', $content);
    $content = preg_replace('/<!--\?xml[^>]*-->/i', '', $content);
    return $content;
}

function blog_sanitize_html($html) {
    $html = blog_normalize_content($html);
    if (trim((string)$html) === '') {
        return '';
    }

    // Keep the structural and presentational HTML produced by rich-text
    // clipboard sources (web pages, Word, Google Docs, etc.). Dangerous tags
    // and event attributes are still removed below.
    $allowed_tags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote',
        'a', 'img', 'picture', 'source', 'figure', 'figcaption',
        'div', 'section', 'article', 'header', 'footer', 'main', 'aside',
        'span', 'font', 'mark', 'small', 'big', 'sub', 'sup',
        'code', 'pre', 'kbd', 'samp', 'var', 'abbr', 'cite', 'q', 'address',
        'table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'hr'
    ];

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    foreach ($dom->getElementsByTagName('*') as $node) {
        $name = strtolower($node->nodeName);
        if (!in_array($name, $allowed_tags, true)) {
            $replacement = $dom->createTextNode($node->textContent);
            $node->parentNode->replaceChild($replacement, $node);
            continue;
        }

        foreach (array_keys(iterator_to_array($node->attributes)) as $attrName) {
            $attrNameLower = strtolower($attrName);
            if (strpos($attrNameLower, 'on') === 0) {
                $node->removeAttribute($attrName);
                continue;
            }

            $value = $node->getAttribute($attrName);
            if ($attrNameLower === 'href' || $attrNameLower === 'src') {
                $clean = trim($value);
                if ($clean === '' || preg_match('/^(javascript:|data:)/i', $clean)) {
                    $node->removeAttribute($attrName);
                } else {
                    $node->setAttribute($attrName, htmlspecialchars($clean, ENT_QUOTES, 'UTF-8'));
                }
                continue;
            }

            if ($attrNameLower === 'style') {
                $style = preg_replace('/expression\s*:/i', '', $value);
                $style = preg_replace('/javascript\s*:/i', '', $style);
                $style = preg_replace('/behavior\s*:/i', '', $style);
                if (trim($style) === '') {
                    $node->removeAttribute($attrName);
                } else {
                    $node->setAttribute($attrName, $style);
                }
                continue;
            }

            if (!in_array($attrNameLower, [
                'href', 'target', 'rel', 'src', 'srcset', 'sizes', 'alt', 'title',
                'class', 'style', 'width', 'height', 'align', 'valign', 'face', 'size',
                'colspan', 'rowspan', 'cellpadding', 'cellspacing', 'border', 'scope',
                'start', 'type', 'reversed', 'dir', 'lang'
            ], true)) {
                $node->removeAttribute($attrName);
            }
        }

        if ($node->nodeName === 'a' && $node->hasAttribute('target') && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    $content = $dom->saveHTML();
    $content = preg_replace('/^<!DOCTYPE.+?>/i', '', $content);
    $content = preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $content);
    return trim($content);
}

function blog_file_upload($file, $path, $max_size = 2097152) {
    if (!isset($file) || !is_array($file) || empty($file['name'])) {
        return ['ok' => false, 'message' => 'No file selected.'];
    }

    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!empty($file['error'])) {
        return ['ok' => false, 'message' => 'Upload failed. Please try another image.'];
    }

    if ($file['size'] <= 0) {
        return ['ok' => false, 'message' => 'The selected file is empty.'];
    }

    if ($file['size'] > $max_size) {
        return ['ok' => false, 'message' => 'Image is too large. Maximum upload size is 2MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            return ['ok' => false, 'message' => 'Unsupported format. Use JPG, PNG, WEBP, or GIF.'];
        }
    }

    @mkdir($path, 0775, true);
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($file['name']));
    $destination = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'message' => 'The file could not be saved. Please try again.'];
    }

    return ['ok' => true, 'path' => str_replace('\\', '/', $destination)];
}

function blog_add_inline_images_to_content($content, $uploaded_files) {
    if (empty($uploaded_files)) {
        return $content;
    }

    $images = '';
    foreach ($uploaded_files as $file_path) {
        $relative = str_replace('\\', '/', $file_path);
        if (strpos($relative, 'uploads/') === false) {
            continue;
        }
        $images .= "\n<figure class=\"blog-inline-image\">\n<img src=\"" . htmlspecialchars($relative, ENT_QUOTES, 'UTF-8') . "\" alt=\"Blog image\" style=\"max-width:100%;height:auto;display:block;margin:1rem auto;border-radius:12px;\" />\n<figcaption>Image</figcaption>\n</figure>";
    }

    if ($images !== '') {
        $content = trim($content) . $images;
    }

    return $content;
}

function blog_output_content_safe($content) {
    if (empty($content)) {
        return '<p>Content coming soon.</p>';
    }

    // Public articles use the featured image as their single visual. Remove
    // legacy inline image blocks so a second image is not shown in the body.
    $content = preg_replace('~<figure\b[^>]*\bclass="[^"]*\bblog-inline-image\b[^"]*"[^>]*>.*?</figure>~is', '', (string)$content);

    // First sanitize the content
    $sanitized = blog_sanitize_html($content);
    
    // Extract and protect images from double-sanitization
    $images = [];
    $protected = preg_replace_callback('/<figure\s+class="blog-inline-image"[^>]*>.*?<\/figure>/is', function($matches) use (&$images) {
        $id = '___BLOG_IMAGE_' . count($images) . '___';
        $images[$id] = $matches[0];
        return $id;
    }, $sanitized);
    
    // Restore images
    foreach ($images as $id => $img_html) {
        $protected = str_replace($id, $img_html, $protected);
    }
    
    return $protected;
}
