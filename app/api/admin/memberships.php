<?php
declare(strict_types=1);
// Route alias: POST /api/admin/{0}/* -> app/api/admin/moderation.php
// The front controller resolves /api/admin/<area>/<action> by walking the path
// back to the longest matching file, so this thin alias is what makes the URL
// in the admin views actually reachable.
require __DIR__ . '/moderation.php';