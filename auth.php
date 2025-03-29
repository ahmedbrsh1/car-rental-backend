<?php
// Get email from the Authorization header
function getEmailFromHeader() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        // Expecting format: "Bearer <email>"
        if (strpos($authHeader, 'Bearer ') === 0) {
            return str_replace('Bearer ', '', $authHeader);  // Extract email
        }
    }
    return null;  // No email in header
}

// Validate email against the database
function validateEmail($email, $conn) {
    $query = "SELECT user_id, email FROM users WHERE email = ?";  // Corrected table name from 'users' to 'user'
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();  // Return user details
    } else {
        return null;  // Invalid email
    }
}
?>
