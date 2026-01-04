<?php
// login.php
header('Content-Type: application/json');
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$mhid = isset($input['mhid']) ? trim($input['mhid']) : '';
$password = isset($input['pw']) ? $input['pw'] : '';

$response = ['success' => false];

if ($mhid && $password) {
    // DB connect
    $mysqli = new mysqli('localhost', 'root', '', 'practise');
    if ($mysqli->connect_errno) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'error'=>'Database connection failed']);
        exit;
    }

    // ✅ Fetch all required fields for session
    $stmt = $mysqli->prepare("SELECT id, mhid, phone, first_name, email, password_hash FROM users WHERE mhid = ?");
    $stmt->bind_param("s", $mhid);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows === 1){
        $stmt->bind_result($uid, $user_mhid, $user_phone, $user_fname, $user_email, $password_hash);
        $stmt->fetch();

        if (password_verify($password, $password_hash)) {
            // ✅ SET ALL REQUIRED SESSION VARIABLES
            $_SESSION['user_id'] = $uid;
            $_SESSION['mhid'] = $user_mhid;
            $_SESSION['phone'] = $user_phone;
            $_SESSION['first_name'] = $user_fname;
            $_SESSION['email'] = $user_email;
            
            $response['success'] = true;
        } else {
            $response['error'] = 'Invalid password';
        }
    } else {
        $response['error'] = 'MHID not found';
    }

    $stmt->close();
    $mysqli->close();
} else {
    $response['error'] = 'MHID and password are required';
}

echo json_encode($response);
?>
