<?php
header('Content-Type: application/json');
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['pw']) ? $input['pw'] : '';

$response = ['success' => false];

if ($username && $password) {
    $mysqli = new mysqli('localhost', 'root', '', 'practise');
    if ($mysqli->connect_errno) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT id, name, username, email, dob, role, password_hash FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($aid, $name, $uname, $email, $dob, $role, $password_hash);
        $stmt->fetch();

        if (password_verify($password, $password_hash)) {
            $_SESSION['admin_id'] = $aid;
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_username'] = $uname;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_dob'] = $dob;
            $_SESSION['admin_role'] = $role;
            $response['success'] = true;
        } else {
            $response['error'] = 'Invalid password';
        }
    } else {
        $response['error'] = 'Admin not found';
    }

    $stmt->close();
    $mysqli->close();
} else {
    $response['error'] = 'Username and password are required';
}

echo json_encode($response);
