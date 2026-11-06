<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'clubnext_999');
define('DB_PASSWORD', 'clubnext_999');
define('DB_NAME', 'clubnext_999');

function getDBConnection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>
