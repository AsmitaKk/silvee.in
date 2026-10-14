<?php
// The legacy blog editor has been retired. Keep old bookmarks working while
// ensuring there is only one blog creation interface and one canonical URL.
header('Location: blog_management.php?new=1', true, 302);
exit;
