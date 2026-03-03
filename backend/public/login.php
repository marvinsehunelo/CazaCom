<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in (based on PHP session set after successful token creation)
if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    header("Location: index.php");
    exit;
}

// Load required dependencies
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Session.php'; // <<< NEW: Include Session Model

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = $_POST['phone_number'] ?? '';
    $pin = $_POST['pin'] ?? '';

    if (!$phone_number || !$pin) {
        $errorMsg = "Phone number and PIN are required";
    } else {
        // Initialize DB and Controllers
        $database = new Database();
        $db = $database->getConnection();
        $controller = new AuthController($db);
        $res = $controller->login(['phone_number'=>$phone_number,'pin'=>$pin]);

        if ($res['status'] === 'success') {
            
            // --- 1. Database Session Creation ---
            $sessionModel = new Session($db);
            
            // Call the model to insert the new token and user_id into the 'sessions' table
            $isSessionSaved = $sessionModel->create($res['user']['id'], $res['token']);

            if ($isSessionSaved) {
                // --- 2. PHP Session and Cookie Setup ---
                
                // Set the essential PHP session variable for page access (index.php guard)
                $_SESSION['user'] = $res['user'];
                
                // Set the token as a secure, HTTP-only cookie for JavaScript to use
                // The JavaScript in index.php expects this 'authToken' cookie.
                setcookie('authToken', $res['token'], [
                    'expires' => time() + (7 * 24 * 60 * 60), // Expires in 7 days
                    'path' => '/',
                    'httponly' => true, // Prevents client-side JS access to token (Security)
                    'samesite' => 'Lax'
                ]);

                // Redirect on success
                header("Location: index.php");
                exit;
            } else {
                // Handle token insertion failure
                $errorMsg = "Authentication successful, but failed to create a secure session. Please try again.";
            }

        } else {
            $errorMsg = $res['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CazaCom Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body {
    font-family: 'Poppins', sans-serif;
    background-color: #000000;
    color: #ffffff;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}
.card {
    background-color: #0d0d0d;
    padding: 3rem;
    border: 1px solid #1a1a1a;
    box-shadow: 0 0 50px rgba(0, 255, 200, 0.1), 0 0 10px rgba(0, 255, 200, 0.2);
    max-width: 400px;
    width: 100%;
    border-radius: 0;
}
input {
    background-color: #1a1a1a;
    color: #ffffff;
    padding: 0.75rem;
    margin-bottom: 1rem;
    border: 1px solid #333;
    width: 100%;
}
button {
    background-color: #00ffc8;
    color: #000000;
    font-weight: 600;
    padding: 0.75rem;
    width: 100%;
    transition: all 0.2s ease;
}
button:hover {
    background-color: #00e6b8;
    box-shadow: inset 0 0 0 2px #000000;
}
.error {
    color: #ff4d4d;
    margin-bottom: 1rem;
    text-align: center;
}
h1 {
    text-align: center;
    margin-bottom: 2rem;
    font-size: 1.75rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 2px;
}
</style>
</head>
<body>
<div class="card">
    <h1>CazaCom Login</h1>

    <?php if($errorMsg): ?>
        <div class="error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="phone_number" placeholder="Phone Number" required>
        <input type="password" name="pin" placeholder="PIN" required>
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
