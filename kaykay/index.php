<?php
// --- CONFIGURATION ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M'); 

 $uploadDir = __DIR__ . '/uploads/'; 
 $webDir = 'uploads/';              

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

 $message = "";

// --- BACKEND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. UPLOAD
    if (isset($_POST['action']) && $_POST['action'] === 'upload' && isset($_FILES['image'])) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($file['type'], $allowedTypes) && $file['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $message = "Image uploaded successfully!";
            } else {
                $message = "Failed to upload file.";
            }
        } else {
            $message = "Invalid file type.";
        }
    }

    // 2. DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['filename'])) {
        $fileToDelete = $uploadDir . basename($_POST['filename']);
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
            $message = "Image deleted.";
        }
    }

    // 3. PROCESS (Edit)
    if (isset($_POST['action']) && $_POST['action'] === 'process' && isset($_POST['filename'])) {
        $filename = basename($_POST['filename']);
        $filePath = $uploadDir . $filename;
        
        if (file_exists($filePath)) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                $message = "Error: Invalid image.";
            } else {
                $mimeType = $imageInfo['mime'];
                $image = null;

                switch ($mimeType) {
                    case 'image/jpeg': $image = @imagecreatefromjpeg($filePath); break;
                    case 'image/png':  $image = @imagecreatefrompng($filePath); break;
                    case 'image/gif':  $image = @imagecreatefromgif($filePath); break;
                    default: $message = "Format not supported."; break;
                }

                if ($image) {
                    // Resize
                    if (!empty($_POST['resize_width']) && is_numeric($_POST['resize_width'])) {
                        $newWidth = intval($_POST['resize_width']);
                        $oldWidth = imagesx($image);
                        $oldHeight = imagesy($image);
                        if ($newWidth > 0 && $oldWidth > 0) {
                            $newHeight = round(($oldHeight / $oldWidth) * $newWidth);
                            $newImage = imagecreatetruecolor($newWidth, $newHeight);
                            if ($mimeType == 'image/png') {
                                imagealphablending($newImage, false);
                                imagesavealpha($newImage, true);
                                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
                            }
                            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $oldWidth, $oldHeight);
                            imagedestroy($image);
                            $image = $newImage;
                        }
                    }

                    // Filters
                    if (isset($_POST['effect_grayscale'])) imagefilter($image, IMG_FILTER_GRAYSCALE);
                    if (isset($_POST['effect_invert'])) imagefilter($image, IMG_FILTER_NEGATE);
                    if (isset($_POST['effect_sepia'])) {
                        imagefilter($image, IMG_FILTER_GRAYSCALE);
                        imagefilter($image, IMG_FILTER_COLORIZE, 100, 50, 0);
                    }

                    // Watermark
                    if (!empty($_POST['watermark_text'])) {
                        $text = $_POST['watermark_text'];
                        $white = imagecolorallocate($image, 255, 255, 255);
                        $black = imagecolorallocate($image, 0, 0, 0);
                        $fontSize = 5; $x = 10; $y = imagesy($image) - 20;
                        if ($y < $fontSize) $y = $fontSize + 5;
                        imagestring($image, $fontSize, $x + 1, $y + 1, $text, $black);
                        imagestring($image, $fontSize, $x, $y, $text, $white);
                    }

                    // Save
                    $saved = false;
                    switch ($mimeType) {
                        case 'image/jpeg': $saved = imagejpeg($image, $filePath, 90); break;
                        case 'image/png':  $saved = imagepng($image, $filePath); break;
                        case 'image/gif':  $saved = imagegif($image, $filePath); break;
                    }
                    imagedestroy($image);
                    
                    if ($saved) $message = "Image updated successfully!";
                    else $message = "Failed to save.";
                }
            }
        }
    }
}

 $images = glob($uploadDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
if ($images) {
    usort($images, function($a, $b) { return filemtime($b) - filemtime($a); });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PixelEdit 3D - Premium Edition</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root {
    --bg-dark: #0a0a1f;
    --bg-gradient: linear-gradient(145deg, #0a0a1f, #1a1a3a, #0d0d2b);
    --glass-bg: rgba(20, 20, 50, 0.3);
    --glass-border: rgba(255, 255, 255, 0.15);
    --primary-neon: #00ff9d;
    --secondary-neon: #00b8ff;
    --accent-purple: #9d4edd;
    --danger-neon: #ff3860;
    --text-main: #ffffff;
    --text-muted: #b8b8d0;
    --radius: 24px;
    --shadow-3d: 0 30px 50px rgba(0, 0, 0, 0.6);
}

* { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
}

body { 
    font-family: 'Montserrat', sans-serif;
    background: var(--bg-gradient);
    background-attachment: fixed;
    color: var(--text-main);
    min-height: 100vh;
    overflow-x: hidden;
    position: relative;
}

/* Animated background particles */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(0, 255, 157, 0.1) 0%, transparent 20%),
        radial-gradient(circle at 80% 70%, rgba(0, 184, 255, 0.1) 0%, transparent 25%),
        radial-gradient(circle at 40% 80%, rgba(157, 78, 221, 0.1) 0%, transparent 30%);
    animation: particleFloat 20s ease-in-out infinite;
    pointer-events: none;
    z-index: -1;
}

@keyframes particleFloat {
    0%, 100% { transform: scale(1) rotate(0deg); opacity: 0.5; }
    50% { transform: scale(1.2) rotate(5deg); opacity: 0.8; }
}

header { 
    padding: 2rem 0; 
    text-align: center;
    transform: perspective(1000px) translateZ(20px);
    animation: headerFloat 3s ease-in-out infinite;
}

@keyframes headerFloat {
    0%, 100% { transform: perspective(1000px) translateZ(20px) translateY(0); }
    50% { transform: perspective(1000px) translateZ(30px) translateY(-10px); }
}

.container { 
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 0 30px; 
}

h1 { 
    font-size: 4rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 4px;
    background: linear-gradient(135deg, var(--primary-neon), var(--secondary-neon), var(--accent-purple));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 
        0 0 30px rgba(0, 255, 157, 0.5),
        0 0 60px rgba(0, 184, 255, 0.3),
        0 20px 40px rgba(0, 0, 0, 0.5);
    transform: perspective(1000px) rotateX(5deg);
    display: inline-block;
    position: relative;
}

h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 10%;
    width: 80%;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--primary-neon), var(--secondary-neon), var(--accent-purple), transparent);
    border-radius: 100%;
    filter: blur(3px);
    animation: glowLine 3s linear infinite;
}

@keyframes glowLine {
    0%, 100% { opacity: 0.5; transform: scaleX(1); }
    50% { opacity: 1; transform: scaleX(1.2); }
}

/* 3D Upload Zone */
.upload-zone { 
    background: var(--glass-bg);
    backdrop-filter: blur(15px);
    border: 2px dashed var(--glass-border);
    border-radius: var(--radius);
    padding: 4rem 2rem;
    text-align: center;
    margin-bottom: 4rem;
    box-shadow: 
        0 30px 50px rgba(0, 0, 0, 0.5),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset,
        0 0 30px rgba(0, 255, 157, 0.2);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transform: perspective(1000px) rotateX(2deg) translateZ(10px);
    position: relative;
    overflow: hidden;
}

.upload-zone::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotateBg 15s linear infinite;
    pointer-events: none;
}

@keyframes rotateBg {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.upload-zone::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: var(--radius);
    padding: 3px;
    background: linear-gradient(135deg, var(--primary-neon), var(--secondary-neon), var(--accent-purple));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: 0;
    transition: opacity 0.3s;
}

.upload-zone:hover::after {
    opacity: 1;
}

.upload-zone:hover { 
    transform: perspective(1000px) rotateX(0deg) translateZ(30px) translateY(-10px);
    box-shadow: 
        0 40px 70px rgba(0, 0, 0, 0.7),
        0 0 0 2px rgba(255, 255, 255, 0.2) inset,
        0 0 50px rgba(0, 255, 157, 0.4);
}

.upload-icon { 
    font-size: 5rem;
    color: var(--primary-neon);
    margin-bottom: 20px;
    filter: drop-shadow(0 0 20px rgba(0, 255, 157, 0.5));
    animation: iconPulse 2s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 0 20px rgba(0, 255, 157, 0.5)); }
    50% { transform: scale(1.1); filter: drop-shadow(0 0 40px rgba(0, 255, 157, 0.8)); }
}

/* Gallery with 3D perspective */
.gallery { 
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 50px;
    perspective: 2000px;
}

/* 3D Card - Enhanced */
.card { 
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    overflow: hidden;
    transform-style: preserve-3d;
    transform: perspective(2000px) rotateY(0deg) rotateX(2deg) translateZ(20px);
    box-shadow: 
        0 30px 50px rgba(0, 0, 0, 0.6),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset,
        0 0 30px rgba(0, 184, 255, 0.2);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
}

.card::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, var(--primary-neon), var(--secondary-neon), var(--accent-purple));
    border-radius: calc(var(--radius) + 2px);
    opacity: 0;
    transition: opacity 0.3s;
    z-index: -1;
}

.card:hover::before {
    opacity: 0.5;
    animation: borderRotate 3s linear infinite;
}

@keyframes borderRotate {
    0%, 100% { filter: blur(5px); }
    50% { filter: blur(10px); }
}

.card-image-wrapper { 
    width: 100%;
    height: 300px;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid var(--glass-border);
    cursor: pointer;
    transform: translateZ(0);
}

.card-image-wrapper::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, transparent 70%, rgba(0,0,0,0.5) 100%);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s;
}

.card:hover .card-image-wrapper::after {
    opacity: 1;
}

.card-img { 
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.card:hover .card-img { 
    transform: scale(1.15) rotate(2deg);
}

.card-body { 
    padding: 30px;
    position: relative;
    z-index: 1;
}

.card-title { 
    color: var(--primary-neon);
    font-size: 1rem;
    letter-spacing: 1px;
    margin-bottom: 20px;
    border-bottom: 2px solid rgba(0, 255, 157, 0.2);
    padding-bottom: 12px;
    font-weight: 600;
    text-shadow: 0 0 10px rgba(0, 255, 157, 0.3);
}

.card-actions { 
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    transform: translateZ(30px);
}

.btn-sm { 
    padding: 12px 20px;
    font-size: 0.9rem;
    flex: 1;
    border-radius: 12px;
    border: none;
    font-weight: 700;
    color: white;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 1px;
    transform: translateZ(10px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.btn-sm::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-sm:hover::before {
    left: 100%;
}

.btn-sm:hover {
    transform: translateZ(20px) translateY(-3px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
}

.btn-edit { 
    background: linear-gradient(135deg, #f09819, #edde5d);
    position: relative;
    z-index: 1;
}

.btn-delete { 
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
}

/* Editor Panel - 3D Floating */
.editor-panel { 
    display: none;
    background: rgba(30, 30, 60, 0.8);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--glass-border);
    animation: slideIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    margin-top: 20px;
    box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.5),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset,
        0 0 30px rgba(157, 78, 221, 0.3);
    transform: perspective(1000px) translateZ(10px);
}

@keyframes slideIn {
    from { 
        opacity: 0;
        transform: perspective(1000px) translateZ(-50px) translateY(-20px);
    }
    to { 
        opacity: 1;
        transform: perspective(1000px) translateZ(10px) translateY(0);
    }
}

.editor-panel.active { 
    display: block; 
}

.form-group { 
    margin-bottom: 20px; 
}

.form-label { 
    display: block;
    color: var(--primary-neon);
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 8px;
    letter-spacing: 1px;
    text-transform: uppercase;
    text-shadow: 0 0 10px rgba(0, 255, 157, 0.3);
}

.form-control { 
    background: rgba(20, 20, 40, 0.8);
    border: 1px solid var(--glass-border);
    color: white;
    padding: 12px 18px;
    border-radius: 12px;
    width: 100%;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-neon);
    box-shadow: 0 0 20px rgba(0, 255, 157, 0.3);
    transform: scale(1.02);
}

.checkbox-group { 
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.checkbox-label { 
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px 20px;
    background: rgba(20, 20, 40, 0.8);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.checkbox-label:hover { 
    background: rgba(40, 40, 70, 0.9);
    transform: translateY(-2px);
    border-color: var(--primary-neon);
    box-shadow: 0 10px 25px rgba(0, 255, 157, 0.2);
}

.checkbox-label input { 
    accent-color: var(--primary-neon);
    width: 18px;
    height: 18px;
}

.btn-save { 
    width: 100%;
    background: linear-gradient(135deg, #11998e, #38ef7d);
    margin-top: 10px;
    border: none;
    padding: 14px;
    color: white;
    font-weight: bold;
    border-radius: 12px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.btn-save::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-save:hover::before {
    left: 100%;
}

.btn-save:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 35px rgba(0, 255, 157, 0.3);
}

/* Modal - 3D Enhanced */
.modal { 
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(10, 10, 30, 0.95);
    backdrop-filter: blur(30px);
    justify-content: center;
    align-items: center;
    flex-direction: column;
    perspective: 2000px;
}

.modal-content { 
    max-width: 90%;
    max-height: 80vh;
    border-radius: 20px;
    box-shadow: 
        0 0 100px rgba(0, 255, 157, 0.3),
        0 30px 70px rgba(0, 0, 0, 0.8),
        0 0 0 2px rgba(255, 255, 255, 0.1) inset;
    animation: zoomIn3D 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform-origin: center;
}

@keyframes zoomIn3D {
    from { 
        transform: perspective(2000px) rotateY(90deg) scale(0.5);
        opacity: 0;
    }
    to { 
        transform: perspective(2000px) rotateY(0deg) scale(1);
        opacity: 1;
    }
}

.close-btn { 
    position: absolute;
    top: 30px;
    right: 40px;
    color: #fff;
    font-size: 60px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    text-shadow: 0 0 30px var(--danger-neon);
    transform: scale(1);
}

.close-btn:hover { 
    color: var(--danger-neon);
    transform: scale(1.2) rotate(180deg);
    text-shadow: 0 0 50px var(--danger-neon);
}

/* Custom Scrollbar - 3D Style */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: rgba(20, 20, 40, 0.5);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary-neon), var(--secondary-neon));
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 255, 157, 0.5);
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--secondary-neon), var(--accent-purple));
}

/* Responsive Design */
@media (max-width: 768px) {
    h1 { font-size: 2.5rem; }
    .gallery { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<header>
<div class="container">
<h1><i class="fas fa-cube"></i> SHANEYYS <span style="color:#fff;">GALLERY</span></h1>
<p style="color:var(--text-muted); letter-spacing:4px; text-transform:uppercase; font-size:0.9rem; margin-top:10px; text-shadow:0 0 20px rgba(0,255,157,0.3);">PROFESSIONAL 3D STUDIO</p>
</div>
</header>

<main class="container">
<input type="file" id="fileInput" accept="image/*" multiple style="display:none">
<label class="upload-zone" id="dropZone">
    <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
    <div class="upload-text">Drag & Drop Image Here</div>
    <div class="upload-subtext">or click anywhere to upload instantly</div>
</label>

<div class="gallery" id="gallery"></div>
</main>

<div id="imageModal" class="modal">
    <span class="close-btn">&times;</span>
    <img class="modal-content" id="modalImg">
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const gallery = document.getElementById('gallery');

// Store image data for modal
let currentModalImage = null;
let currentModalFilters = '';

['dragenter','dragover','dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev=>{ev.preventDefault(); ev.stopPropagation();}));
['dragenter','dragover'].forEach(e=>dropZone.addEventListener(e, ()=>dropZone.classList.add('drag-active')));
['dragleave','drop'].forEach(e=>dropZone.addEventListener(e, ()=>dropZone.classList.remove('drag-active')));

dropZone.addEventListener('click',()=>fileInput.click());
fileInput.addEventListener('change', e=>handleFiles(e.target.files));

function handleFiles(files){
    for(let file of files){
        const reader = new FileReader();
        reader.onload = function(ev){
            const src = ev.target.result;
            const safeId = 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const card = document.createElement('div');
            card.className = 'card';
            card.id = 'card-'+safeId;
            card.innerHTML = `
            <div class="card-image-wrapper" onclick="openModal('${safeId}')">
                <img src="${src}" class="card-img" id="img-${safeId}" data-original-src="${src}" alt="Image">
            </div>
            <div class="card-body">
                <div class="card-title"><i class="fas fa-image" style="margin-right:8px;"></i>${file.name}</div>
                <div class="card-actions">
                    <button class="btn-sm btn-edit" onclick="toggleEditor('${safeId}')"><i class="fas fa-edit" style="margin-right:5px;"></i>EDIT</button>
                    <button class="btn-sm btn-delete" onclick="deleteCard('${safeId}')"><i class="fas fa-trash" style="margin-right:5px;"></i>DELETE</button>
                </div>
                <div id="editor-${safeId}" class="editor-panel">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tag" style="margin-right:5px;"></i>Rename Image</label>
                        <input type="text" placeholder="${file.name}" class="form-control" id="rename-${safeId}">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-filter" style="margin-right:5px;"></i>Live Filters</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label"><input type="checkbox" onchange="updatePreview('${safeId}')" id="gray-${safeId}"> <i class="fas fa-tint"></i> Gray</label>
                            <label class="checkbox-label"><input type="checkbox" onchange="updatePreview('${safeId}')" id="invert-${safeId}"> <i class="fas fa-adjust"></i> Invert</label>
                            <label class="checkbox-label"><input type="checkbox" onchange="updatePreview('${safeId}')" id="sepia-${safeId}"> <i class="fas fa-fire"></i> Sepia</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-arrows-alt-h" style="margin-right:5px;"></i>Resize Width (px)</label>
                        <input type="number" placeholder="e.g. 800" class="form-control" id="resize-${safeId}" oninput="updatePreview('${safeId}')">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-water" style="margin-right:5px;"></i>Watermark</label>
                        <input type="text" placeholder="© Copyright" class="form-control" id="watermark-${safeId}" oninput="updatePreview('${safeId}')">
                    </div>
                    <button class="btn-save" onclick="saveEdits('${safeId}')"><i class="fas fa-save" style="margin-right:5px;"></i>SAVE CHANGES</button>
                    <button type="button" class="btn-save" style="background:linear-gradient(135deg, #ff416c, #ff4b2b);margin-top:10px;" onclick="resetEdits('${safeId}')"><i class="fas fa-undo" style="margin-right:5px;"></i>RESET</button>
                </div>
            </div>
            `;
            gallery.prepend(card);
            
            // Add 3D hover effect
            card.addEventListener('mousemove', e=>{
                const rect = card.getBoundingClientRect();
                const rotateX = ((e.clientY - rect.top - rect.height/2)/(rect.height/2)) * -8;
                const rotateY = ((e.clientX - rect.left - rect.width/2)/(rect.width/2)) * 8;
                card.style.transform = `perspective(2000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(30px) scale(1.05)`;
            });
            card.addEventListener('mouseleave', ()=>{
                card.style.transform='perspective(2000px) rotateX(2deg) rotateY(0deg) translateZ(20px) scale(1)';
            });
        }
        reader.readAsDataURL(file);
    }
}

function deleteCard(id) {
    if(confirm('Are you sure you want to delete this image?')) {
        document.getElementById('card-'+id).remove();
    }
}

function openModal(id){
    const img = document.getElementById('img-'+id);
    const modalImg = document.getElementById('modalImg');
    
    // Get current filters from the image
    currentModalFilters = img.style.filter;
    currentModalImage = img;
    
    // Apply the same filters to modal image
    modalImg.src = img.src;
    modalImg.style.filter = currentModalFilters;
    
    document.getElementById('imageModal').style.display='flex';
}

function closeModal(){ 
    document.getElementById('imageModal').style.display='none';
    currentModalImage = null;
    currentModalFilters = '';
}

// Update close button event
document.querySelector('.close-btn').onclick = closeModal;

window.onclick = e=>{ 
    if(e.target==document.getElementById('imageModal')) closeModal(); 
};

function toggleEditor(id){ 
    document.getElementById('editor-'+id).classList.toggle('active'); 
}

function updatePreview(id){
    const cardImg = document.getElementById('img-'+id);
    const gray = document.getElementById('gray-'+id);
    const invert = document.getElementById('invert-'+id);
    const sepia = document.getElementById('sepia-'+id);
    
    // Build filter string
    let filterStr = '';
    if(gray && gray.checked) filterStr += 'grayscale(100%) ';
    if(invert && invert.checked) filterStr += 'invert(100%) ';
    if(sepia && sepia.checked) filterStr += 'sepia(100%) ';
    
    // Apply filters to card image
    cardImg.style.filter = filterStr;
    
    // Handle resize (visual only)
    const resizeInput = document.getElementById('resize-'+id);
    if(resizeInput && resizeInput.value > 0) {
        cardImg.style.width = resizeInput.value + 'px';
        cardImg.style.height = 'auto';
    } else {
        cardImg.style.width = '100%';
        cardImg.style.height = '100%';
    }
    
    // Handle watermark text (visual overlay)
    const watermark = document.getElementById('watermark-'+id);
    if(watermark && watermark.value) {
        // Create or update watermark overlay
        let watermarkDiv = document.getElementById('watermark-overlay-'+id);
        if(!watermarkDiv) {
            watermarkDiv = document.createElement('div');
            watermarkDiv.id = 'watermark-overlay-'+id;
            watermarkDiv.style.position = 'absolute';
            watermarkDiv.style.bottom = '15px';
            watermarkDiv.style.left = '15px';
            watermarkDiv.style.color = 'white';
            watermarkDiv.style.background = 'linear-gradient(135deg, rgba(0,255,157,0.8), rgba(0,184,255,0.8))';
            watermarkDiv.style.padding = '8px 15px';
            watermarkDiv.style.borderRadius = '8px';
            watermarkDiv.style.fontSize = '14px';
            watermarkDiv.style.fontWeight = 'bold';
            watermarkDiv.style.zIndex = '10';
            watermarkDiv.style.boxShadow = '0 5px 15px rgba(0,0,0,0.3)';
            watermarkDiv.style.backdropFilter = 'blur(5px)';
            watermarkDiv.style.border = '1px solid rgba(255,255,255,0.2)';
            cardImg.parentElement.style.position = 'relative';
            cardImg.parentElement.appendChild(watermarkDiv);
        }
        watermarkDiv.textContent = watermark.value;
    } else {
        const watermarkDiv = document.getElementById('watermark-overlay-'+id);
        if(watermarkDiv) watermarkDiv.remove();
    }
}

function saveEdits(id){
    const card = document.getElementById('card-'+id);
    const cardImg = document.getElementById('img-'+id);
    const editor = document.getElementById('editor-'+id);
    
    // Rename
    const newName = document.getElementById('rename-'+id).value;
    if(newName) card.querySelector('.card-title').innerHTML = `<i class="fas fa-image" style="margin-right:8px;"></i>${newName}`;

    // Save the current filter state to the image's data attribute
    const currentFilters = cardImg.style.filter;
    cardImg.setAttribute('data-filters', currentFilters);
    
    // Close editor panel
    editor.classList.remove('active');

    // Show success animation
    card.style.transform = 'perspective(2000px) scale(1.1) translateZ(50px)';
    setTimeout(() => {
        card.style.transform = 'perspective(2000px) rotateX(2deg) translateZ(20px) scale(1)';
    }, 200);
    
    alert('✨ Changes saved successfully!');
}

function resetEdits(id){
    const cardImg = document.getElementById('img-'+id);
    const editor = document.getElementById('editor-'+id);
    
    // Reset filters
    document.getElementById('gray-'+id).checked = false;
    document.getElementById('invert-'+id).checked = false;
    document.getElementById('sepia-'+id).checked = false;
    cardImg.style.filter = '';
    cardImg.removeAttribute('data-filters');
    
    // Reset width
    document.getElementById('resize-'+id).value = '';
    cardImg.style.width = '100%';
    cardImg.style.height = '100%';
    
    // Reset watermark
    document.getElementById('watermark-'+id).value = '';
    const watermarkDiv = document.getElementById('watermark-overlay-'+id);
    if(watermarkDiv) watermarkDiv.remove();
}

// Add keyboard shortcut for modal
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape' && document.getElementById('imageModal').style.display === 'flex') {
        closeModal();
    }
});
</script>

</body>
</html>