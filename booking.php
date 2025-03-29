<?php
// Include necessary files for database connection
require_once 'db.php'; // Database connection
require_once 'car.php'; // Car-related functions
require_once 'credit_card.php'; // Credit card-related functions

// Function to create a booking
function createBooking($conn, $userId, $carId, $creditCardId, $startDate, $bookPlace, $endDate, $dropPlace) {
    // Validate if the car is available and fetch price per day
    $carQuery = "SELECT available, price_per_day FROM cars WHERE car_id = ?";
    $stmt = $conn->prepare($carQuery);
    $stmt->bind_param("i", $carId);
    $stmt->execute();
    $result = $stmt->get_result();
    $car = $result->fetch_assoc();
    $stmt->close();

    if (!$car || $car['available'] == 'N') { 
        return ["error" => "Car is not available."];
    }

    // Validate input data
    if (empty($bookPlace) || empty($startDate) || empty($endDate) || empty($creditCardId) || empty($dropPlace)) {
        return ["error" => "All fields are required."];
    }

    $days = ceil((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)) + 1;
if ($days <= 0) {
    return ["error" => "Invalid booking dates."];
}
$pricePaid = $days * $car['price_per_day'];


    // Insert payment details (including credit_card_id)
    $paymentQuery = "INSERT INTO payment (price, payment_date, card_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($paymentQuery);
    $stmt->bind_param("isi", $pricePaid, $startDate, $creditCardId); 
    if ($stmt->execute()) {
        $paymentId = $stmt->insert_id;
        $stmt->close();
    } else {
        $stmt->close();
        return ["error" => "Failed to process payment."];
    }

    // Determine booking status based on dates
    $currentDate = date("Y-m-d");

    if ($currentDate < $startDate) {
        $status = "Pending Pick-Up";
    } elseif ($currentDate >= $startDate && $currentDate <= $endDate) {
        $status = "Picked Up";
    } else {
        $status = "Completed";
    }

    // Insert booking details
    $bookingQuery = "INSERT INTO booking (car_id, user_id, book_place, drop_place, book_date, return_date, pay_id, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($bookingQuery);
    $stmt->bind_param("iissssis", $carId, $userId, $bookPlace, $dropPlace, $startDate, $endDate, $paymentId, $status); 

    if ($stmt->execute()) {
        // Update car availability to 'not available'
        $updateCarQuery = "UPDATE cars SET available = 'N' WHERE car_id = ?";
        $stmt = $conn->prepare($updateCarQuery);
        $stmt->bind_param("i", $carId);
        $stmt->execute();
        $stmt->close();

        return ["message" => "Booking created successfully."];
    } else {
        $stmt->close();
        return ["error" => "Failed to create booking."];
    }
}



function cancelBooking($conn, $data) {
    // Extract booking ID
    if (!isset($data['book_id']) || empty($data['book_id'])) {
        return ["error" => "Booking ID is required."];
    }

    $bookId = $data['book_id'];

    // Check if the booking exists
    $checkQuery = "SELECT car_id FROM booking WHERE book_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        return ["error" => "Booking not found."];
    }

    // Update the booking status to 'Cancelled'
    $updateQuery = "UPDATE booking SET status = 'Cancelled' WHERE book_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $bookId);

    if ($stmt->execute()) {
        $stmt->close();

        // Make the car available again
        $updateCarQuery = "UPDATE cars SET available = 'Y' WHERE car_id = ?";
        $stmt = $conn->prepare($updateCarQuery);
        $stmt->bind_param("i", $booking['car_id']);
        $stmt->execute();
        $stmt->close();

        return ["message" => "Booking cancelled successfully."];
    } else {
        $stmt->close();
        return ["error" => "Failed to cancel booking."];
    }
}




?>
