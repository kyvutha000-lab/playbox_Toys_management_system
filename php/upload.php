<?php
/**
 * php/upload.php - Handles toy image uploads (multipart/form-data)
 * Returns { success, url } where url is a relative path usable in <img src>.
 *
 * Deliberately avoids mime_content_type()/finfo, since the "fileinfo" PHP
 * extension isn't guaranteed to be enabled on every server. getimagesize()
 * is a core PHP function (no extension required) and is enough to both
 * validate that the upload is really an image and to read its MIME type.
 */
require_once __DIR__ . '/db.php';
require_role(['Admin','Manager']);

// Never let an unexpected error surface as a raw HTML/500 page - always JSON.
set_exception_handler(function ($e) {
    json_error('Upload error: ' . $e->getMessage(), 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    json_error('No image file received');
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $phpUploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds the server\'s upload_max_filesize setting',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds the form\'s max file size',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write the file to disk',
        UPLOAD_ERR_EXTENSION => 'A server extension blocked the upload',
    ];
    json_error($phpUploadErrors[$file['error']] ?? ('Upload failed (error code ' . $file['error'] . ')'));
}

if ($file['size'] > 5 * 1024 * 1024) {
    json_error('Image must be smaller than 5MB');
}

// Validate it's really an image (also works without the fileinfo extension).
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    json_error('The uploaded file is not a valid image');
}

$allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime = $imageInfo['mime'];
if (!isset($allowedMimes[$mime])) {
    json_error('Only JPG, PNG, WEBP, or GIF images are allowed');
}

$ext = $allowedMimes[$mime];
$filename = 'toy_' . uniqid() . '_' . time() . '.' . $ext;
$destDir = __DIR__ . '/../uploads/toys/';

if (!is_dir($destDir)) {
    if (!@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        json_error('Could not create the uploads folder. Check that "uploads/" is writable by the web server.', 500);
    }
}

if (!is_writable($destDir)) {
    json_error('The uploads/toys folder is not writable. Run: chmod -R 775 uploads', 500);
}

$destPath = $destDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    json_error('Failed to save the uploaded file to disk. Check folder permissions on uploads/toys.', 500);
}

// Relative URL the frontend can use directly in <img src="...">
json_response(['success' => true, 'url' => '../uploads/toys/' . $filename]);
