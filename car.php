<?php
// Function to get all available cars
function getAllCars($conn, $branchId = null) {
    if ($branchId) {
        $query = "SELECT * FROM cars WHERE available = 'Y' AND branch_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $branchId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT * FROM cars WHERE available = 'Y'";
        $result = $conn->query($query);
    }

    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}



// Function to search for available cars based on filters
function searchCars($conn, $searchParams) {
    $whereClauses = ["available = 'Y'"]; // Always filter for available cars

    // Check for filters and build WHERE clauses
    if (!empty($searchParams['min_price'])) {
        $whereClauses[] = "price_per_day >= " . (int)$searchParams['min_price'];
    }
    if (!empty($searchParams['max_price'])) {
        $whereClauses[] = "price_per_day <= " . (int)$searchParams['max_price'];
    }
    if (!empty($searchParams['manufacturer'])) {
        $manufacturer = $conn->real_escape_string($searchParams['manufacturer']);
        $whereClauses[] = "manufacturer LIKE '%" . $manufacturer . "%'";
    }
    if (!empty($searchParams['model'])) {
        $model = $conn->real_escape_string($searchParams['model']);
        $whereClauses[] = "model LIKE '%" . $model . "%'";
    }
    if (!empty($searchParams['year'])) {
        $whereClauses[] = "year = " . (int)$searchParams['year'];
    }

    // Build the full SQL query
    $query = "SELECT * FROM cars";
    
    // Add WHERE clauses if there are any
    if (!empty($whereClauses)) {
        $query .= " WHERE " . implode(" AND ", $whereClauses);
    }

    // Execute the query and fetch results
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        // Return the cars as an array
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return []; // Return an empty array if no cars match the search
    }
}

// Function to register a car
function registerCar($conn, $carData) {
    // Prepare the SQL statement
    $stmt = $conn->prepare(
        "INSERT INTO cars (manufacturer, model, year, price_per_day, fuel_type, capacity, available, branch_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    // Bind the parameters
    $stmt->bind_param(
        "ssidsisi", // 's' for string, 'i' for integer, 'd' for double
        $carData['manufacturer'], 
        $carData['model'], 
        $carData['year'], 
        $carData['price_per_day'], 
        $carData['fuel_type'],
        $carData['capacity'],
        $carData['available'],
        $carData['branch_id']
    );

    // Execute the statement and check for success
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'Car registered successfully!',
            'car_id' => $stmt->insert_id // Get the ID of the inserted car
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Error registering car: ' . $stmt->error
        ];
    }
}

// Function to get random available cars
function getRandomCars($conn, $branchId = null) {
    if ($branchId) {
        $query = "SELECT * FROM cars WHERE available = 'Y' AND branch_id = ? ORDER BY RAND() LIMIT 3";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $branchId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT * FROM cars WHERE available = 'Y' ORDER BY RAND() LIMIT 3";
        $result = $conn->query($query);
    }

    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}



function getCarById($conn, $carId) {
    // Fetch car details and booking reservations
    $stmt = $conn->prepare("
        SELECT 
            c.*, 
            b.book_date, 
            b.return_date 
        FROM cars c
        LEFT JOIN booking b ON c.car_id = b.car_id
        WHERE c.car_id = ?
    ");
    
    $stmt->bind_param("i", $carId);
    $stmt->execute();
    $result = $stmt->get_result();

    $carData = null;
    $reservationDates = [];

    while ($row = $result->fetch_assoc()) {
        if (!$carData) {
            $carData = $row; // Store car details
        }
        if (!empty($row['book_date']) && !empty($row['return_date'])) {
            $reservationDates[] = [
                'book_date' => $row['book_date'],
                'return_date' => $row['return_date']
            ];
        }
    }

    if ($carData) {
        $carData['reservations'] = $reservationDates;
    }

    // Fetch car reviews with user full names
    $reviewStmt = $conn->prepare("
        SELECT 
            CONCAT(u.fname, ' ', u.lname) AS user_name, 
            r.rate, 
            r.review 
        FROM car_reviews r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.car_id = ?
    ");
    
    $reviewStmt->bind_param("i", $carId);
    $reviewStmt->execute();
    $reviewResult = $reviewStmt->get_result();

    $reviews = [];
    while ($reviewRow = $reviewResult->fetch_assoc()) {
        $reviews[] = [
            'user_name' => $reviewRow['user_name'],
            'rate' => $reviewRow['rate'],
            'review' => $reviewRow['review'] ?? null // Handle NULL reviews
        ];
    }

    if ($carData) {
        $carData['reviews'] = $reviews;
    }

    return $carData;
}



// Improved addCarReview function with error logging
function addCarReview($conn, $user_id, $car_id, $rate, $review = null) {
    $query = "INSERT INTO car_reviews (user_id, car_id, rate, review) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return ["success" => false, "message" => "SQL Error: " . $conn->error];
    }

    $stmt->bind_param("iiis", $user_id, $car_id, $rate, $review);

    if ($stmt->execute()) {
        return ["success" => true, "message" => "Review added successfully"];
    } else {
        return ["success" => false, "message" => "Insert failed: " . $stmt->error];
    }
}





?>
