<?php
function getReservationsInPeriod($conn, $startDate, $endDate) {
    $query = "
        SELECT b.*, c.manufacturer, c.model, u.fname, u.lname, u.email
        FROM booking b
        JOIN cars c ON b.car_id = c.car_id  -- Changed table and column name to match schema
        JOIN users u ON b.user_id = u.user_id  -- Changed table and column name to match schema
        WHERE b.book_date >= ? AND b.return_date <= ?  -- Changed to match the correct column names
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getCarStatusOnDay($conn, $specificDate) {
    $query = "
        SELECT c.car_id, c.manufacturer, c.model, 
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM booking b 
                WHERE b.car_id = c.car_id  -- Changed column name to match schema
                AND ? BETWEEN b.book_date AND b.return_date  -- Changed column names
            ) THEN 'Reserved'
            ELSE 'Available'
        END AS status
        FROM cars c  -- Changed table name to match schema
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $specificDate);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getReservationsByCustomer($conn, $customerId) {
    $query = "
        SELECT b.*, c.manufacturer, c.model, u.fname, u.lname, u.email
        FROM booking b
        JOIN cars c ON b.car_id = c.car_id  -- Changed table and column name to match schema
        JOIN users u ON b.user_id = u.user_id  -- Changed table and column name to match schema
        WHERE b.user_id = ?  -- Correct column name
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getDailyPaymentsInPeriod($conn, $startDate, $endDate) {
    $query = "
        SELECT 
            DATE(p.payment_date) AS payment_date, 
            SUM(p.price) AS total_payments
        FROM payment p
        WHERE p.payment_date BETWEEN ? AND ?
        GROUP BY DATE(p.payment_date)
        ORDER BY payment_date
    ";
    
    // Prepare the SQL statement
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return ["error" => "Failed to prepare statement: " . $conn->error];
    }

    // Bind parameters to the query
    $stmt->bind_param("ss", $startDate, $endDate);

    // Execute the query
    if (!$stmt->execute()) {
        return ["error" => "Execution failed: " . $stmt->error];
    }

    // Get the result and fetch all rows as associative array
    $result = $stmt->get_result();
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return ["error" => "Failed to fetch results: " . $stmt->error];
    }
}

?>
