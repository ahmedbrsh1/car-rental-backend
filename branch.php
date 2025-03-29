<?php
function getAllBranches($conn) {
    $query = "SELECT branch_id, location FROM branch";  // Changed to match schema
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>
