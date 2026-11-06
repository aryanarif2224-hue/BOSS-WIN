<?php

function setPermissions($path) {
    if (!file_exists($path)) {
        return;
    }

    // Current file/folder permission set
    @chmod($path, 0755);

    // Agar folder hai to andar bhi loop karo
    if (is_dir($path)) {
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;

            setPermissions($fullPath);
        }
    }
}

// Current directory se start
$startPath = __DIR__;

setPermissions($startPath);

echo "Done! All files and folders set to 755.";

?>