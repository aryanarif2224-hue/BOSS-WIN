
<?php
// #################### কনফিগারেশন ####################

// একটি শক্তিশালী এবং গোপন পাসওয়ার্ড সেট করুন
$password = '1234';

// জিপ ফাইলের নাম
$zipFile = 'backup.zip';

// ######################################################


// নিরাপত্তা পরীক্ষা: পাসওয়ার্ড না দিলে বা ভুল দিলে ফর্ম দেখাবে
if (!isset($_POST['password']) || $_POST['password'] !== $password) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Authentication Required</title></head><body>';
    echo '<form method="post" style="text-align:center; margin-top: 50px;">';
    echo '<h2>Enter Password to Create Backup</h2>';
    if (isset($_POST['password'])) {
        echo '<p style="color:red;">Invalid Password!</p>';
    }
    echo '<input type="password" name="password" size="30" /> <br/><br/>';
    echo '<input type="submit" value="Create Backup" />';
    echo '</form></body></html>';
    die();
}

// এক্সিকিউশন টাইম লিমিট তুলে দেওয়া হলো
@set_time_limit(0);

// আউটপুট বাফারিং বন্ধ করা হলো যাতে লাইভ আউটপুট দেখা যায়
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
@ob_implicit_flush(true);

while (ob_get_level() > 0) {
    ob_end_flush();
}

// যে ডিরেক্টরি জিপ করা হবে
$directoryToZip = __DIR__;

// শেল আর্গুমেন্ট হিসেবে নিরাপদ করা
$zipFileSafe = escapeshellarg($zipFile);

// যে ফাইলগুলো জিপ থেকে বাদ যাবে
$excludePatterns = [
    '*.zip',             // সমস্ত .zip ফাইল বাদ যাবে
    basename(__FILE__)   // এই PHP স্ক্রিপ্টটি বাদ যাবে
];

$excludeCmd = '';
foreach ($excludePatterns as $pattern) {
    $excludeCmd .= ' -x ' . escapeshellarg($pattern);
}

// বর্তমান ডিরেক্টরিতে কাজ করার জন্য chdir ব্যবহার করা
chdir($directoryToZip);

// সবচেয়ে দ্রুতগতির জন্য -0 ফ্ল্যাগ সহ জিপ কমান্ড
// stderr (2) কে stdout (1) এ পাঠানো হলো যাতে এরর দেখা যায়
$cmd = "zip -r -0 $zipFileSafe . $excludeCmd 2>&1";

// প্রসেস শুরু করা
$process = popen($cmd, 'r');

// প্রসেস সফলভাবে শুরু হলে আউটপুট দেখানো
if (is_resource($process)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Starting backup process...\n\n";
    echo "The following patterns will be excluded:\n";
    foreach ($excludePatterns as $p) {
        echo "- $p\n";
    }
    echo "\n";


    while (!feof($process)) {
        $buffer = fgets($process);
        echo htmlspecialchars($buffer); // আউটপুটকে নিরাপদভাবে দেখানো
        @ob_flush();
        @flush();
    }
    
    // প্রসেস বন্ধ করা এবং এক্সিট কোড নেওয়া
    $exitCode = pclose($process);

    echo "\n----------------------------------------\n";
    // এক্সিট কোড পরীক্ষা করা (0 মানে সফল)
    if ($exitCode === 0) {
        echo "SUCCESS: Backup created successfully!\n";
        // বর্তমান URL বের করে ডাউনলোডের লিংক তৈরি করা
        $downloadLink = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $zipFile;
        echo "Download Link: $downloadLink\n";
    } else {
        echo "ERROR: The zip process failed with exit code: $exitCode.\n";
        echo "Please check file permissions and ensure the 'zip' command is available on the server.\n";
    }

} else {
    echo "Failed to start the zipping process. Please check server configuration.";
}

?>
