<?php
function getAllCreditCards($conn, $userId) {
    // Adjusted to use the correct table name and column names based on your schema
    $stmt = $conn->prepare("SELECT * FROM credit_card WHERE user_id = ?"); 
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $creditCards = [];
    while ($row = $result->fetch_assoc()) {
        $creditCards[] = $row;
    }
    $stmt->close();
    return $creditCards;
}

function addCreditCard($conn, $userId, $cardData) {
    // Adjusted to use the correct table name and column names based on your schema
    $stmt = $conn->prepare("INSERT INTO credit_card (user_id, card_number, expiration_date, cardholder_name, cvv) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $cardData['card_number'], $cardData['expiration_date'], $cardData['cardholder_name'], $cardData['cvv']);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ["message" => "Credit card added successfully."];
    } else {
        $stmt->close();
        return ["error" => "Failed to add credit card: " . $stmt->error];
    }
}


function deleteCreditCard($conn, $data) {
    // Validate credit card ID
    if (!isset($data['card_id']) || empty($data['card_id'])) {
        return ["error" => "Credit card ID is required."];
    }

    $cardId = $data['card_id'];

    // Check if the credit card exists
    $checkQuery = "SELECT card_id FROM credit_card WHERE card_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $cardId);
    $stmt->execute();
    $result = $stmt->get_result();
    $card = $result->fetch_assoc();
    $stmt->close();

    if (!$card) {
        return ["error" => "Credit card not found."];
    }

    // Delete the credit card
    $deleteQuery = "DELETE FROM credit_card WHERE card_id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $cardId);

    if ($stmt->execute()) {
        $stmt->close();
        return ["message" => "Credit card deleted successfully."];
    } else {
        $stmt->close();
        return ["error" => "Failed to delete credit card."];
    }
}


?>
