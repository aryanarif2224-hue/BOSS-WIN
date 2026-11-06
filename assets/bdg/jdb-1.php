<?php

if (function_exists('set_time_limit')) { set_time_limit(0); }
if (function_exists('ini_set')) { 
    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '-1');
    ini_set('output_buffering', 0); // ডাউনলোডের সময় বাফারিং সমস্যা এড়াতে
    ini_set('display_errors', 0);
}
error_reporting(0);
session_start();

// --- ২. ডিরেক্টরি এবং পাথ সেটআপ ---
$currentDir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!$currentDir || !is_dir($currentDir)) { $currentDir = getcwd(); }
$parentDir = dirname($currentDir);
$msg = "";

// --- ৩. অ্যাকশন হ্যান্ডলার (Action Handlers) ---

// A. RENAME (নাম পরিবর্তন)
if (isset($_POST['action']) && $_POST['action'] == 'rename') {
    $oldName = $currentDir . '/' . $_POST['old_name'];
    $newName = $currentDir . '/' . $_POST['new_name'];
    
    if (file_exists($oldName)) {
        if (rename($oldName, $newName)) {
            $msg = "<div class='success'>✅ নাম পরিবর্তন সফল হয়েছে!</div>";
        } else {
            $msg = "<div class='error'>❌ নাম পরিবর্তন করা যায়নি। পারমিশন চেক করুন।</div>";
        }
    }
}

// B. DELETE (মুছে ফেলা - Recursive)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['file'])) {
    $target = $currentDir . '/' . $_GET['file'];
    
    // ফোল্ডার ডিলিট করার ফাংশন (ভেতরের সব ফাইলসহ)
    function delete_recursive($target) {
        if (is_dir($target)) {
            $files = glob($target . '*', GLOB_MARK); 
            foreach($files as $file) {
                delete_recursive($file);      
            }
            rmdir($target);
        } elseif (is_file($target)) {
            unlink($target);
        }
    }

    if (file_exists($target)) {
        delete_recursive($target);
        $msg = "<div class='success'>🗑️ ফাইল/ফোল্ডার ডিলিট করা হয়েছে!</div>";
    } else {
        $msg = "<div class='error'>❌ ফাইল পাওয়া যায়নি!</div>";
    }
}

// C. SINGLE FILE DOWNLOAD (সরাসরি ডাউনলোড)
if (isset($_GET['action']) && $_GET['action'] == 'download' && isset($_GET['file'])) {
    $file = $currentDir . '/' . $_GET['file'];
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// D. ZIP CREATION (বাল্ক জিপ)
if (isset($_POST['do_zip'])) {
    if (!is_writable($currentDir)) {
        $msg = "<div class='error'>❌ এই ফোল্ডারে রাইট পারমিশন নেই!</div>";
    } 
    elseif (!empty($_POST['files'])) {
        $files = $_POST['files'];
        $zipFileName = "archive_" . date("Y-m-d_H-i-s") . ".zip";
        $zipFilePath = $currentDir . '/' . $zipFileName;
        $created = false;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($files as $name) {
                    $fullPath = $currentDir . '/' . $name;
                    if (is_file($fullPath)) {
                        $zip->addFile($fullPath, $name);
                    } elseif (is_dir($fullPath)) {
                        $rootPath = realpath($fullPath);
                        $zip->addEmptyDir($name);
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        foreach ($iterator as $leaffile) {
                            $filePath = $leaffile->getRealPath();
                            $relativePath = $name . '/' . substr($filePath, strlen($rootPath) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }
                $zip->close();
                $created = true;
            }
        } else {
            // Fallback (Linux Zip Command)
            $itemList = "";
            foreach ($files as $name) { $itemList .= " " . escapeshellarg($name); }
            $cmd = "cd " . escapeshellarg($currentDir) . " && zip -r " . escapeshellarg($zipFileName) . $itemList;
            @exec($cmd, $output, $return_var);
            if ($return_var === 0 && file_exists($zipFilePath)) { $created = true; }
        }

        if ($created) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $docRoot = $_SERVER['DOCUMENT_ROOT'];
            if (strpos($zipFilePath, $docRoot) === 0) {
                $relativePath = substr($zipFilePath, strlen($docRoot));
                $fullUrl = $protocol . "://" . $host . str_replace('\\', '/', $relativePath);
                $msg = "<div class='success'>✅ জিপ তৈরি হয়েছে!<br><input type='text' value='$fullUrl' class='url-box' onclick='this.select()'><br><a href='$fullUrl' class='btn-download'>⬇ ডাউনলোড জিপ</a></div>";
            } else {
                $msg = "<div class='success'>✅ জিপ তৈরি হয়েছে: $zipFileName</div>";
            }
        } else {
            $msg = "<div class='error'>❌ জিপ তৈরি করা যায়নি।</div>";
        }
    } else {
        $msg = "<div class='error'>⚠️ কিছুই সিলেক্ট করা হয়নি!</div>";
    }
}

$allFiles = scandir($currentDir);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pro File Manager</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; font-size: 14px; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        a { text-decoration: none; }
        
        /* Message & Path */
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 10px; text-align: center; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 10px; text-align: center; border: 1px solid #f5c6cb; }
        .nav-bar { background: #e9ecef; padding: 10px; margin-bottom: 15px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: #6c757d; color: white; padding: 5px 10px; border-radius: 4px; }
        .url-box { width: 80%; padding: 5px; margin-top: 5px; text-align: center; border: 1px solid #ddd; }

        /* Table Styles */
        .table-wrap { overflow-x: auto; border: 1px solid #ddd; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        tr:hover { background: #f1f1f1; }

        /* Action Buttons */
        .btn-action { padding: 3px 8px; font-size: 12px; border-radius: 3px; color: white; margin-right: 2px; border: none; cursor: pointer; display: inline-block; }
        .btn-ren { background: #ffc107; color: #000; }
        .btn-del { background: #dc3545; }
        .btn-down { background: #17a2b8; }
        .btn-zip { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 5px; font-size: 16px; margin-top: 15px; cursor: pointer; }
        .btn-zip:disabled { background: #ccc; }
        .btn-download { display: inline-block; margin-top: 5px; background: #007bff; color: white; padding: 5px 15px; border-radius: 3px; }

        .folder-link { font-weight: bold; color: #0056b3; }
        .file-info { font-size: 12px; color: #666; }
    </style>
</head>
<body>

<div class="container">
    <h3 style="text-align:center; margin-top:0;">Advanced File Manager</h3>
    <?php echo $msg; ?>

    <div class="nav-bar">
        <a href="?dir=<?php echo urlencode($parentDir); ?>" class="back-btn">⬅ Back</a>
        <small style="font-family:monospace; margin-left: 10px; word-break: break-all;"><?php echo $currentDir; ?></small>
    </div>

    <form method="POST" action="?dir=<?php echo urlencode($currentDir); ?>">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" onclick="toggle(this)"></th>
                        <th>Name</th>
                        <th>Info</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $folders = []; $files = [];
                    foreach ($allFiles as $item) {
                        if ($item == "." || $item == "..") continue;
                        if (is_dir($currentDir . '/' . $item)) $folders[] = $item;
                        else $files[] = $item;
                    }

                    function renderRow($name, $dir, $isDir) {
                        $path = $dir . '/' . $name;
                        $size = $isDir ? 'DIR' : (filesize($path) ? round(filesize($path)/1024, 2).' KB' : '0 KB');
                        $date = date("M d, Y h:i A", filemtime($path));
                        $icon = $isDir ? '📁' : '📄';
                        $link = $isDir ? "?dir=".urlencode($path) : "#";
                        $class = $isDir ? "folder-link" : "";
                        $downLink = "?dir=".urlencode($dir)."&file=".urlencode($name)."&action=download";
                        $delLink = "?dir=".urlencode($dir)."&file=".urlencode($name)."&action=delete";

                        echo "<tr>";
                        echo "<td><input type='checkbox' name='files[]' value='$name' class='chk' onclick='chkBtn()'></td>";
                        echo "<td><span style='margin-right:5px'>$icon</span>";
                        if($isDir) echo "<a href='$link' class='$class'>$name</a>";
                        else echo "$name";
                        echo "</td>";
                        echo "<td class='file-info'>$size<br>$date</td>";
                        echo "<td style='text-align:right'>";
                        
                        // Download Button (Only for files)
                        if(!$isDir) echo "<a href='$downLink' class='btn-action btn-down' title='Download'>⬇</a>";
                        
                        // Rename Button
                        echo "<button type='button' class='btn-action btn-ren' onclick='renameFile(\"$name\")' title='Rename'>✎</button>";
                        
                        // Delete Button
                        echo "<a href='$delLink' class='btn-action btn-del' onclick='return confirm(\"Are you sure you want to delete: $name?\")' title='Delete'>✖</a>";
                        
                        echo "</td>";
                        echo "</tr>";
                    }

                    foreach ($folders as $f) renderRow($f, $currentDir, true);
                    foreach ($files as $f) renderRow($f, $currentDir, false);
                    ?>
                </tbody>
            </table>
        </div>
        <button type="submit" name="do_zip" id="zipBtn" class="btn-zip" disabled>Select items to ZIP</button>
    </form>
    
    <!-- Hidden Rename Form -->
    <form method="POST" action="?dir=<?php echo urlencode($currentDir); ?>" id="renameForm" style="display:none;">
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="old_name" id="oldNameInput">
        <input type="hidden" name="new_name" id="newNameInput">
    </form>
</div>

<script>
    // Checkbox Logic
    function toggle(source) {
        var checkboxes = document.querySelectorAll('.chk');
        for (var i = 0; i < checkboxes.length; i++) checkboxes[i].checked = source.checked;
        chkBtn();
    }
    function chkBtn() {
        var count = document.querySelectorAll('.chk:checked').length;
        var btn = document.getElementById('zipBtn');
        if (count > 0) {
            btn.disabled = false;
            btn.innerText = "⬇ Create ZIP (" + count + " items)";
        } else {
            btn.disabled = true;
            btn.innerText = "Select items to ZIP";
        }
    }

    // Rename Logic (Popup)
    function renameFile(oldName) {
        var newName = prompt("Rename " + oldName + " to:", oldName);
        if (newName != null && newName != "" && newName != oldName) {
            document.getElementById('oldNameInput').value = oldName;
            document.getElementById('newNameInput').value = newName;
            document.getElementById('renameForm').submit();
        }
    }
</script>

</body>
</html>