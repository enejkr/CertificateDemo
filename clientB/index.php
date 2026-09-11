<?php
$url = 'https://localhost:8443/api/login.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($ch);
    $message = $response === false ? 'NAPAKA: ' . curl_error($ch) : $response;

    curl_close($ch);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Client</title>
</head>
<body>

<h1>Client</h1>

<form method="post">
    <button type="submit">Poveži</button>
</form>

<p><?php echo htmlspecialchars($message); ?></p>

</body>
</html>