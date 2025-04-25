<?php
// Database connection settings
$server = "localhost";
$username = "root";
$password = "";
$database = "bluebirdhotel";

// Set default timezone (adjust to your hotel's timezone)
date_default_timezone_set('Asia/Kolkata'); // Example for Indian time

// Establish database connection
$conn = mysqli_connect($server, $username, $password, $database);

if(!$conn) {
    die("<script>alert('Connection Failed.')</script>");
}

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Utility function to validate future dates
function validateFutureDate($date) {
    $today = date('Y-m-d');
    if ($date < $today) {
        // Log the attempt
        error_log("Past date attempt: $date (Today: $today)");
        return false;
    }
    return true;
}

// Strict date validation for check-in/check-out
function validateBookingDates($cin, $cout) {
    $today = date('Y-m-d');
    
    // Basic validation
    if (empty($cin) || empty($cout)) {
        return "Dates cannot be empty";
    }
    
    // Check if dates are valid
    if (!strtotime($cin) || !strtotime($cout)) {
        return "Invalid date format";
    }
    
    // Format dates for comparison
    $cin = date('Y-m-d', strtotime($cin));
    $cout = date('Y-m-d', strtotime($cout));
    
    // Check for past dates
    if ($cin < $today) {
        return "Check-in date cannot be in the past";
    }
    
    // Check if checkout is after checkin
    if ($cout <= $cin) {
        return "Check-out date must be after check-in date";
    }
    
    return true;
}

// Secure function to calculate days between dates
function calculateDays($cin, $cout) {
    $cin = new DateTime($cin);
    $cout = new DateTime($cout);
    $interval = $cin->diff($cout);
    return $interval->days;
}
?>