<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/role_management.php';
require_admin($pdo);

$page_title = 'Blog Management';
require_once __DIR__ . '/templates/header.php';

// Fetch existing blog posts
$posts = $pdo->query("SELECT * FROM blog_posts ORDER BY updated_at DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$show_form = isset($_GET['new']) || $edit_id > 0;

$post = [
    'id' => '',
    'title' => '',
    'slug' => '',
    'short_description' => '',
    'category' => 'General',
    'featured_image' => '',
    'image_alt' => '',
    'content' => '',
    'article_style' => 'classic',
    'status' => 'draft',
    'seo_title' => '',
    'meta_description' => '',
];

if ($edit_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ? LIMIT 1');
    $stmt->execute([$edit_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $post = $existing;
        $post['content'] = blog_normalize_content($post['content'] ?? '');
        $show_form = true;
    }
}

$notice = $_SESSION['blog_notice'] ?? '';
if (isset($_SESSION['blog_notice'])) unset($_SESSION['blog_notice']);
$error = $_SESSION['blog_error'] ?? '';
if (isset($_SESSION['blog_error'])) unset($_SESSION['blog_error']);
?>

<div class="container-fluid" style="max-width: 1400px; margin: 0 auto; padding: 2rem 1rem;">
    <?php if ($notice): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($notice) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="mb-1">Blog Management</h3>
            <p class="text-muted mb-0">Create, edit, and publish blog posts for your store.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="blog_management.php?new=1" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-plus-circle me-1"></i>New Blog
            </a>
            <a href="blog.php" target="_blank" class="btn btn-outline-dark rounded-pill px-4">
                <i class="bi bi-box-arrow-up-right me-1"></i>Preview Public Blog
            </a>
        </div>
    </div>

    <!-- Blog Posts Form (Shown when creating/editing) -->
    <?php if ($show_form): ?>
        <div class="card shadow-sm border-0 mb-5" style="border-radius: 16px;">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h4 class="mb-0 text-dark font-serif"><?= $post['id'] ? 'Edit Blog Post' : 'Create New Blog Post' ?></h4>
                <a href="blog_management.php" class="btn btn-sm btn-outline-secondary rounded-pill">Cancel / Close Form</a>
            </div>
            <div class="card-body p-4">
                <form id="blogForm" method="post" action="blog_save.php" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= e($post['id']) ?>">
                    <input type="hidden" name="redirect_to" value="blog_management.php">
                    <input type="hidden" name="category" value="General">
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="blogTitleInput" value="<?= e($post['title']) ?>" required placeholder="Enter blog post title" oninput="autoGenerateSlug(this.value)">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">URL Slug</label>
                        <input type="text" class="form-control" name="slug" id="blogSlugInput" value="<?= e($post['slug']) ?>" placeholder="url-friendly-slug">
                        <div class="form-text">Leave blank to automatically generate from title.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Short Description</label>
                        <textarea class="form-control" name="short_description" rows="3" placeholder="Brief summary of the article for blog card listing"><?= e($post['short_description']) ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Featured Image</label>
                            <input type="file" class="form-control" name="featured_image" id="featuredImageInput" accept="image/*" onchange="previewFeaturedImage(this)">
                            
                            <!-- Featured Image Live Preview Area -->
                            <div id="featuredImagePreviewContainer" class="mt-3">
                                <?php
                                $form_img = ltrim(trim(str_replace(['\\', str_replace('\\', '/', __DIR__) . '/'], ['/', ''], $post['featured_image'] ?? '')), '/');
                                $has_form_thumb = !empty($form_img) && @file_exists(__DIR__ . '/' . $form_img);
                                ?>
                                <?php if ($has_form_thumb): ?>
                                    <div class="p-3 bg-light rounded d-flex align-items-center gap-3 border">
                                        <img id="featuredImagePreviewImg" src="<?= e($form_img) ?>?v=<?= filemtime(__DIR__ . '/' . $form_img) ?>" alt="Featured Image" style="max-height:110px; border-radius:8px; object-fit:contain; background:#062a22; padding:4px;">
                                        <div>
                                            <span class="d-block small text-dark fw-semibold"><?= basename($form_img) ?></span>
                                            <span class="badge bg-success mt-1">Current Image</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Image Alt Text</label>
                            <input type="text" class="form-control" name="image_alt" value="<?= e($post['image_alt'] ?? $post['title']) ?>" placeholder="Alt text for accessibility & image SEO">
                            <div class="form-text">Used for accessibility and image search optimization.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Article Layout Style</label>
                            <select class="form-select" name="article_style">
                                <option value="classic" <?= ($post['article_style'] ?? 'classic') === 'classic' ? 'selected' : '' ?>>Classic</option>
                                <option value="editorial" <?= ($post['article_style'] ?? 'classic') === 'editorial' ? 'selected' : '' ?>>Editorial</option>
                                <option value="luxury" <?= ($post['article_style'] ?? 'classic') === 'luxury' ? 'selected' : '' ?>>Luxury</option>
                                <option value="modern" <?= ($post['article_style'] ?? 'classic') === 'modern' ? 'selected' : '' ?>>Modern</option>
                            </select>
                        </div>
                    </div>

                    <!-- Article Content WYSIWYG Editor -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Article Content <span class="text-danger">*</span></label>
                        
                        <!-- Rich Text Toolbar -->
                        <div class="d-flex flex-wrap gap-1 p-2 bg-light border border-bottom-0 rounded-top">
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('bold')" title="Bold"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('italic')" title="Italic"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('underline')" title="Underline"><i class="bi bi-type-underline"></i></button>
                            <div class="vr mx-1"></div>
                            <select class="form-select form-select-sm" style="width: 105px;" onmousedown="saveEditorSelection()" onchange="changeEditorHeading(this)" title="Heading level" aria-label="Heading level">
                                <option value="">Heading</option>
                                <option value="h1">H1</option>
                                <option value="h2">H2</option>
                                <option value="h3">H3</option>
                                <option value="h4">H4</option>
                                <option value="h5">H5</option>
                                <option value="h6">H6</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('formatBlock', '<p>')" title="Paragraph">P</button>
                            <select class="form-select form-select-sm" style="width: 150px;" onchange="changeEditorFont(this)" title="Font style" aria-label="Font style">
                                <option value="">Font Style</option>
                                <option value="Arial">Arial</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Courier New">Courier New</option>
                                <option value="Montserrat">Montserrat</option>
                            </select>
                            <select id="fontSizeSelect" class="form-select form-select-sm" style="width: 90px;" onmousedown="saveEditorSelection()" onchange="changeEditorFontSize(this)" title="Font size" aria-label="Font size">
                                <option value="">Size</option>
                                <?php foreach ([8, 9, 10, 11, 12, 14, 16, 18, 20, 22, 24, 26, 28, 32, 36, 40, 48, 56, 72] as $font_size): ?>
                                    <option value="<?= $font_size ?>"><?= $font_size ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="color" value="#1f2937" class="form-control form-control-color form-control-sm" onmousedown="saveEditorSelection()" onchange="changeEditorColor(this)" title="Text color" aria-label="Text color">
                            <select class="form-select form-select-sm" style="width: 105px;" onmousedown="saveEditorSelection()" onchange="changeEditorLineHeight(this)" title="Line spacing" aria-label="Line spacing">
                                <option value="">Spacing</option>
                                <option value="1">Single</option>
                                <option value="1.5">1.5</option>
                                <option value="1.7">1.7</option>
                                <option value="2">Double</option>
                            </select>
                            <div class="vr mx-1"></div>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('justifyLeft')" title="Align left"><i class="bi bi-text-left"></i></button>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('justifyCenter')" title="Align center"><i class="bi bi-text-center"></i></button>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('justifyRight')" title="Align right"><i class="bi bi-text-right"></i></button>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('insertUnorderedList')" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('insertOrderedList')" title="Numbered List"><i class="bi bi-list-ol"></i></button>
                            <button type="button" class="btn btn-sm btn-white border" onclick="execCmd('formatBlock', '<blockquote>')" title="Quote"><i class="bi bi-quote"></i></button>
                            <div class="vr mx-1"></div>
                            <!-- Link Toolbar Buttons (Requirement 6) -->
                            <button type="button" class="btn btn-sm btn-white border text-primary" onclick="insertEditorLink()" title="Add Internal / External Link"><i class="bi bi-link-45deg"></i> Add Link</button>
                            <button type="button" class="btn btn-sm btn-white border text-secondary" onclick="unlinkEditor()" title="Remove Link"><i class="bi bi-scissors"></i> Unlink</button>
                        </div>

                        <!-- Editable Content Box -->
                        <div id="contentEditor" contenteditable="true" class="form-control rounded-bottom p-3" style="min-height: 340px; max-height: 600px; overflow-y: auto; background: #fff; border-top-left-radius: 0; border-top-right-radius: 0; line-height: 1.7; font-size: 1rem;"><?= $post['content'] ?></div>
                        <input type="hidden" name="content" id="contentInput">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SEO Title</label>
                            <input type="text" class="form-control" name="seo_title" value="<?= e($post['seo_title']) ?>" placeholder="Meta title for search engines">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Meta Description</label>
                            <input type="text" class="form-control" name="meta_description" value="<?= e($post['meta_description']) ?>" placeholder="Meta description snippet for Google search">
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap align-items-center pt-3 border-top">
                        <button type="submit" name="action" value="save_draft" class="btn btn-secondary rounded-pill px-4">Save Draft</button>
                        <button type="submit" name="action" value="publish" class="btn btn-primary rounded-pill px-4">Publish Article</button>
                        <?php if ($post['id']): ?>
                            <a href="blog_post.php?preview=1&id=<?= (int)$post['id'] ?>" target="_blank" class="btn btn-outline-dark rounded-pill px-3">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Preview Article
                            </a>
                            <button type="submit" name="action" value="delete" class="btn btn-outline-danger rounded-pill px-3 ms-auto" onclick="return confirm('Delete this blog post?')" title="Delete" aria-label="Delete blog post"><i class="bi bi-trash me-1" aria-hidden="true"></i></button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Existing Blog Posts Table (Displayed directly on open - Requirement 7) -->
    <div class="card shadow-sm border-0" style="border-radius: 16px;">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <h4 class="mb-0 text-dark font-serif">All Blog Posts</h4>
            <span class="badge bg-dark-subtle text-dark rounded-pill px-3 py-2"><?= count($posts) ?> Total Articles</span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($posts)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;" class="ps-4">Image</th>
                                <th>Title & Slug</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th class="text-end pe-4" style="min-width: 300px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $p): ?>
                                <?php
                                $img_path = ltrim(trim(str_replace(['\\', str_replace('\\', '/', __DIR__) . '/'], ['/', ''], $p['featured_image'] ?? '')), '/');
                                $has_thumb = false;
                                if (!empty($img_path) && @file_exists(__DIR__ . '/' . $img_path)) {
                                    $has_thumb = true;
                                }
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if ($has_thumb): ?>
                                            <img src="<?= e($img_path) ?>?v=<?= filemtime(__DIR__ . '/' . $img_path) ?>" alt="Thumb" style="width: 54px; height: 38px; object-fit: contain; background: #062a22; border-radius: 6px;">
                                        <?php else: ?>
                                            <div style="width: 54px; height: 38px; background: #e5e7eb; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 0.7rem; font-weight: 700;">NO IMG</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark mb-1"><?= e($p['title']) ?></div>
                                        <div class="small text-muted font-monospace">/blog_post.php?slug=<?= e($p['slug']) ?></div>
                                    </td>
                                    <td>
                                        <?php if (($p['status'] ?? 'draft') === 'published'): ?>
                                            <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill fw-semibold">✓ Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-1 rounded-pill fw-semibold">● Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M j, Y', strtotime($p['updated_at'] ?: $p['created_at'])) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-1">
                                            <a href="blog_management.php?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="bi bi-pencil-square me-1"></i>Edit
                                            </a>
                                            <form method="post" action="blog_save.php" class="d-inline" onsubmit="return confirm('Delete this blog post? This action cannot be undone.');">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <input type="hidden" name="redirect_to" value="blog_management.php">
                                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width: 38px; height: 38px;" aria-label="Delete blog post">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            <a href="blog_post.php?preview=1&id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Preview">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <p class="mb-3">No blog posts created yet.</p>
                    <a href="blog_management.php?new=1" class="btn btn-primary rounded-pill px-4">Create Your First Blog Post</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let savedEditorRange = null;

// Insert the clipboard's HTML instead of converting a rich paste to plain
// text. Inline fonts, sizes, spacing, headings, colours and emphasis are then
// submitted exactly as they appear in the editor.
document.getElementById('contentEditor')?.addEventListener('paste', function (event) {
    const html = event.clipboardData?.getData('text/html');
    if (!html) return;
    event.preventDefault();
    document.execCommand('insertHTML', false, html);
    saveEditorSelection();
});

function applyStyleToSelection(property, value) {
    restoreEditorSelection();
    const editor = document.getElementById('contentEditor');
    const selection = window.getSelection();
    if (!editor || !selection || !selection.rangeCount || selection.isCollapsed) return;

    const range = selection.getRangeAt(0);
    const wrapper = document.createElement('span');
    wrapper.style.setProperty(property, value);
    try {
        range.surroundContents(wrapper);
    } catch (error) {
        const fragment = range.extractContents();
        wrapper.appendChild(fragment);
        range.insertNode(wrapper);
    }

    selection.removeAllRanges();
    const updatedRange = document.createRange();
    updatedRange.selectNodeContents(wrapper);
    selection.addRange(updatedRange);
    saveEditorSelection();
}

function saveEditorSelection() {
    const editor = document.getElementById('contentEditor');
    const selection = window.getSelection();
    if (editor && selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
        savedEditorRange = selection.getRangeAt(0).cloneRange();
    }
}

function restoreEditorSelection() {
    if (!savedEditorRange) return;
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedEditorRange);
}

// Execute Rich Text formatting commands
function execCmd(command, value = null) {
    restoreEditorSelection();
    document.execCommand(command, false, value);
    document.getElementById('contentEditor')?.focus();
    saveEditorSelection();
}

function changeEditorFont(select) {
    if (select.value) {
        execCmd('fontName', select.value);
    }
    select.selectedIndex = 0;
}

function changeEditorHeading(select) {
    if (select.value) {
        execCmd('formatBlock', '<' + select.value + '>');
    }
    select.selectedIndex = 0;
}

function changeEditorFontSize(select) {
    const size = parseInt(select.value, 10);
    select.selectedIndex = 0;
    if (!Number.isFinite(size)) return;

    applyEditorFontSize(size);
}

function changeEditorColor(input) {
    restoreEditorSelection();
    document.execCommand('foreColor', false, input.value);
    document.getElementById('contentEditor')?.focus();
    saveEditorSelection();
}

function changeEditorLineHeight(select) {
    const value = select.value;
    select.selectedIndex = 0;
    if (value) applyStyleToSelection('line-height', value);
}

function applyEditorFontSize(size) {

    restoreEditorSelection();
    const editor = document.getElementById('contentEditor');
    if (!editor) return;

    // Let the browser format selections that span multiple elements, then
    // replace its legacy 1-7 size with the exact selected point size.
    editor.querySelectorAll('font[size="7"]').forEach(function (font) {
        font.dataset.existingFontSize = 'true';
    });
    document.execCommand('fontSize', false, '7');
    editor.querySelectorAll('font[size="7"]:not([data-existing-font-size])').forEach(function (font) {
        font.removeAttribute('size');
        font.style.fontSize = size + 'pt';
    });
    editor.querySelectorAll('[data-existing-font-size]').forEach(function (font) {
        font.removeAttribute('data-existing-font-size');
    });
    editor.focus();
    saveEditorSelection();
}

// Add Link to selected text (Requirement 6)
function insertEditorLink() {
    const url = prompt("Enter URL (e.g. https://example.com or shop.php):");
    if (url) {
        document.execCommand('createLink', false, url);
    }
}

// Remove link from selected text
function unlinkEditor() {
    document.execCommand('unlink', false, null);
}

// Live Featured Image Preview (Requirement 3)
function previewFeaturedImage(input) {
    const container = document.getElementById('featuredImagePreviewContainer');
    if (!container) return;

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            container.innerHTML = `
                <div class="p-3 bg-light rounded d-flex align-items-center gap-3 border">
                    <img src="${e.target.result}" alt="Preview" style="max-height:110px; border-radius:8px; object-fit:contain; background:#062a22; padding:4px;">
                    <div>
                        <span class="d-block small text-dark fw-semibold">${input.files[0].name}</span>
                        <span class="badge bg-primary mt-1">Ready to Upload</span>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto generate URL slug from title
function autoGenerateSlug(title) {
    const slugInput = document.getElementById('blogSlugInput');
    if (slugInput && (slugInput.dataset.manual !== 'true')) {
        slugInput.value = title.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .trim()
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
}

document.getElementById('blogSlugInput')?.addEventListener('input', function() {
    this.dataset.manual = 'true';
});

const contentEditor = document.getElementById('contentEditor');
contentEditor?.addEventListener('mouseup', saveEditorSelection);
contentEditor?.addEventListener('keyup', saveEditorSelection);
contentEditor?.addEventListener('focus', saveEditorSelection);

// Sync editor content on form submit
document.getElementById('blogForm')?.addEventListener('submit', function() {
    const editor = document.getElementById('contentEditor');
    const input = document.getElementById('contentInput');
    if (editor && input) {
        input.value = editor.innerHTML;
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
