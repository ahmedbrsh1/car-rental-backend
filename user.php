<?php

// Register a new user
function registerUser($conn, $data) {
    $requiredFields = ['fname', 'lname', 'email', 'lic_num', 'phone_number', 'gender', 'password'];
    
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(["error" => "Some required fields are missing."]);
            exit();
        }
    }
    

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(["error" => "Email already exists"]);
        exit();
    }

    $stmt->close();

    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

    // Insert user into database
    $stmt = $conn->prepare("INSERT INTO users (fname, lname, email, lic_num, phone_number, gender, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $data['fname'], $data['lname'], $data['email'], $data['lic_num'], $data['phone_number'], $data['gender'], $hashedPassword);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "User registered successfully.", "email" => $data['email']]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $stmt->error]);
    }

    $stmt->close();
}


// Log in an existing user
function loginUser($conn, $data) {
    // Check if both email and password are provided
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["error" => "Email or password incorrect."]);
        exit();
    }

    // Prepare SQL query to check if the email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the user exists and verify password
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($data['password'], $user['password'])) {
            echo json_encode(["message" => "Login successful.", "user_id" => $user['user_id'], "email" => $user['email']]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Email or password incorrect."]);
        }
    } else {
        http_response_code(401); // Keep response consistent
        echo json_encode(["error" => "Email or password incorrect."]);
    }

    $stmt->close();
}






function getUserDataByEmail($conn, $email) {
    // Prepare query to get user information
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $userResult = $stmt->get_result();

    if ($userResult->num_rows === 0) {
        return null; // User not found
    }

    $userData = $userResult->fetch_assoc();
    $userId = $userData['user_id'];

    // Get user's credit cards
    $stmt = $conn->prepare("SELECT card_id, card_number, expiration_date, cardholder_name FROM credit_card WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $creditCardResult = $stmt->get_result();
    $creditCards = [];
    while ($row = $creditCardResult->fetch_assoc()) {
        $creditCards[] = $row;
    }

    // Get user's bookings
    $stmt = $conn->prepare("
        SELECT 
            b.book_id, b.book_place, b.book_date, b.return_date, b.status, 
            c.manufacturer, c.model, c.year, c.fuel_type, c.capacity
        FROM booking b
        JOIN cars c ON b.car_id = c.car_id
        WHERE b.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $bookingResult = $stmt->get_result();
    $bookings = [];
    while ($row = $bookingResult->fetch_assoc()) {
        $bookings[] = $row;
    }

    // Combine all data into one response
    return [
        "user" => $userData,
        "credit_cards" => $creditCards,
        "bookings" => $bookings
    ];
}

function updateUserInfoByEmail($conn, $email, $data) {
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["error" => "Valid email is required."]);
        exit();
    }

    // Allowed fields that can be updated
    $allowedFields = ['phone_number', 'lic_num'];

    // Filter only allowed fields from input data
    $updates = [];
    $params = [];
    $types = ""; // Used for bind_param

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= "s"; // Assuming all fields are strings
        }
    }

    // If no valid fields are provided, return an error response
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(["error" => "No valid fields provided for update."]);
        exit();
    }

    // Prepare the SQL query dynamically
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE email = ?";
    $params[] = $email; // Add email to params for WHERE clause
    $types .= "s"; // Email is also a string

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $conn->error]);
        exit();
    }

    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);

    // Execute query
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "User information updated successfully."]);
        } else {
            http_response_code(200); // No changes, but request is valid
            echo json_encode(["message" => "No changes were made."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update user information."]);
    }

    $stmt->close();
}


function deleteUser($userId, $conn) {
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}










?>
