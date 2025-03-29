<?php
// Include necessary files
require_once 'db.php'; // Database connection
require_once 'car.php'; // Car-related functions
require_once 'credit_card.php'; // Credit card-related functions
require_once 'user.php'; // User-related functions
require_once 'auth.php'; // Authentication functions
require_once 'booking.php'; // Booking-related functions
require_once 'reports.php'; // Reports functions
require_once 'branch.php'; // Branch functions

// Enable CORS for all domains (change to your frontend URL for production)
header("Access-Control-Allow-Origin: http://localhost:5173"); // Update the domain for production
header("Access-Control-Allow-Methods: GET,PATCH, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}



// Initialization code
$currentDate = date('Y-m-d');

// Update status to 'Picked Up'
$sql_pickup = "UPDATE booking SET Status = 'Picked Up' WHERE book_date <= '$currentDate' AND Status = 'Reserved'";
$conn->query($sql_pickup);

// Update status to 'Returned'
$sql_return = "UPDATE booking SET Status = 'Returned' WHERE return_date <= '$currentDate' AND Status = 'Picked Up'";
$conn->query($sql_return);

// Log message for debugging
error_log("Status updates executed on: $currentDate");

// Action Handling
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    if ($action === 'registerUser') {
        registerUser($conn, $data); // Register user
    } 
    elseif ($action === 'loginUser') {
        loginUser($conn, $data); // Login user
    } 
    elseif ($action === 'getAllCars') {
        // Check if branch_id is passed as a query parameter
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        if ($branchId) {
            $cars = getAllCars($conn, $branchId);
        } else {
            $cars = getAllCars($conn);
        }

        echo json_encode($cars);
    }
    elseif ($action === 'searchCars') {
        $searchedCars = searchCars($conn, $data);
        echo json_encode($searchedCars);
    }
    elseif ($action === 'registerCar') {
        $response = registerCar($conn, $data);
        echo json_encode($response);
    }
    elseif ($action === 'getAllBranches') {
        $branches = getAllBranches($conn);
        echo json_encode($branches);
    }
    elseif ($action === 'getReport') {
        $data = json_decode(file_get_contents("php://input"), true);
        $reportType = $data['report_type'];
        $response = [];

        switch ($reportType) {
            case 'reservations_in_period':
                $response = getReservationsInPeriod($conn, $data['start_date'], $data['end_date']);
                break;

            case 'car_status_on_day':
                $response = getCarStatusOnDay($conn, $data['specific_date']);
                break;

            case 'reservations_by_customer':
                $response = getReservationsByCustomer($conn, $data['customer_id']);
                break;

            case 'daily_payments_in_period':
                $response = getDailyPaymentsInPeriod($conn, $data['start_date'], $data['end_date']);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid report type']);
                exit();
        }

        echo json_encode($response);
    }
    elseif ($action === "getUserData") {
        
    
        $email = getEmailFromHeader(); // Extract email from the Authorization header
    
        if (!$email) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized. No valid Authorization header found."]);
            exit();
        }
    
        $userData = getUserDataByEmail($conn, $email);
    
        if ($userData) {
            header("Content-Type: application/json");
            echo json_encode($userData);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User not found."]);
        }
    }

    elseif ($action === "deleteUser") {
        
    
        $email = getEmailFromHeader(); 
    
        if (!$email) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized. No valid Authorization header found."]);
            exit();
        }
    
        $user = validateEmail($email,$conn);

        if ($user) {
            $userId = $user['user_id'];
            
            if (deleteUser($userId, $conn)) {
                echo json_encode(["success" => true, "message" => "User deleted successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Failed to delete user."]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User not found."]);
        }
    }

    
    elseif ($action === 'getRandomCars') {
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
    
        if ($branchId) {
            $randomCars = getRandomCars($conn, $branchId);
        } else {
            $randomCars = getRandomCars($conn);
        }
    
        echo json_encode($randomCars);
    }
    
    elseif ($action === 'getCarById' && isset($_GET['id'])) {
        $car = getCarById($conn, $_GET['id']);
        if ($car) {
            echo json_encode($car);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Car not found']);
        }
    }
    elseif ($action === 'getAllCreditCards') {
        $email = getEmailFromHeader();
        if ($email) {
            $user = validateEmail($email, $conn);
            if ($user) {
                $creditCards = getAllCreditCards($conn, $user['user_id']);
                echo json_encode($creditCards);
            } else {
                http_response_code(401); // Unauthorized
                echo json_encode(["error" => "Invalid email."]);
            }
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(["error" => "Email not provided."]);
        }
    } elseif ($action === 'addCreditCard') {
        $email = getEmailFromHeader();
        if ($email) {
            $user = validateEmail($email, $conn);
            if ($user) {
                // Validate required fields
                $requiredFields = ['card_number', 'expiration_date', 'cvv', 'cardholder_name'];
                foreach ($requiredFields as $field) {
                    if (empty($data[$field])) {
                        http_response_code(400); // Bad Request
                        echo json_encode(["error" => "Missing Fields! Please ensure you've entered all required information."]);
                        exit;
                    }
                }
    
                $response = addCreditCard($conn, $user['user_id'], $data);
                echo json_encode($response);
            } else {
                http_response_code(401); // Unauthorized
                echo json_encode(["error" => "Invalid email."]);
            }
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(["error" => "Email not provided."]);
        }
    }
    

    elseif ($action === "deleteCard") {
        if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
            $response = deleteCreditCard($conn, $data);
            echo json_encode($response);
        } else {
            echo json_encode(["error" => "Invalid request method. Use DELETE."]);
        }
    }
    
    elseif ($action === 'updateUserInfo'){
        $email = getEmailFromHeader();
        if ($email) {
            $user = validateEmail($email, $conn);
            if ($user) {
                updateUserInfoByEmail($conn,$email,$data);
            }
        }
    }

    elseif ($action === 'addReview') {
    
        // Get user email from Authorization header
        $email = getEmailFromHeader();
        if (!$email) {
            http_response_code(401); // Unauthorized
            echo json_encode(["success" => false, "message" => "Unauthorized: Missing Authorization header"]);
            exit;
        }
    
        // Validate the email and get the user ID
        $user = validateEmail($email, $conn);
        if (!$user) {
            http_response_code(401); // Unauthorized
            echo json_encode(["success" => false, "message" => "Unauthorized: Invalid email"]);
            exit;
        }
    
        $user_id = $user['user_id'];
    
        // Validate required fields
        if (!isset($data['car_id'], $data['rate'])) {
            http_response_code(400); // Bad Request
            echo json_encode(["success" => false, "message" => "Missing required fields"]);
            exit;
        }
    
        $car_id = $data['car_id'];
        $rate = $data['rate'];
        $review = isset($data['review']) ? $data['review'] : null;
    
        // Validate rating (1-5)
        if ($rate < 1 || $rate > 5) {
            http_response_code(400); // Bad Request
            echo json_encode(["success" => false, "message" => "Invalid rating. Must be between 1 and 5"]);
            exit;
        }
    
        // Insert review and return response
        $response = addCarReview($conn, $user_id, $car_id, $rate, $review);
    
        // Set HTTP response code based on success/failure
        if (!$response['success']) {
            http_response_code(500); // Internal Server Error
        }
    
        echo json_encode($response);
    }
    elseif ($action === 'createBooking') {
        $email = getEmailFromHeader();
        if ($email) {
            $user = validateEmail($email, $conn);
            if ($user) {
                // Validate incoming booking data
                $requiredFields = ['car_id', 'card_id', 'pick-location', 'from', 'to', 'drop-location'];
                foreach ($requiredFields as $field) {
                    if (empty($data[$field])) {
                        http_response_code(400); // Bad Request
                        echo json_encode(["error" => "Missing Fields! Please ensure that you've entered all required fields and selected a payment method."]);
                        exit();
                    }
                }
    
                // Get user and car details
                $userId = $user['user_id'];
                $carId = $data['car_id'];
                $creditCardId = $data['card_id'];
                $bookPlace = $data['pick-location'];
                $dropPlace = $data['drop-location'];
                $startDate = $data['from'];
                $endDate = $data['to'];
    
                // Call the createBooking function from booking.php
                $response = createBooking($conn, $userId, $carId, $creditCardId, $startDate, $bookPlace, $endDate, $dropPlace);
    
                // Return response
                echo json_encode($response);
            } else {
                http_response_code(401); // Unauthorized
                echo json_encode(['error' => 'Invalid email.']);
            }
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Email not provided in Authorization header.']);
        }
    }
    
    elseif ($action === "cancelReservation") {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $data = json_decode(file_get_contents("php://input"), true);
            $response = cancelBooking($conn, $data);
            echo json_encode($response);
        } else {
            echo json_encode(["error" => "Invalid request method."]);
        }
    }
    
    else {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid action.']);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'No action specified.']);
}

$conn->close();
?>
