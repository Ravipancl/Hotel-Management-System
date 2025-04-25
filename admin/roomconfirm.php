<?php
include '../config.php';

// Validate ID and prevent SQL injection
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid booking ID");
}
$id = (int)$_GET['id'];

// Use prepared statement to fetch booking details
$stmt = $conn->prepare("SELECT * FROM roombook WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Booking not found");
}

$Name = $row['Name'];
$Email = $row['Email'];
$Country = $row['Country'];
$Phone = $row['Phone'];
$RoomType = $row['RoomType'];
$Bed = $row['Bed'];
$NoofRoom = $row['NoofRoom'];
$Meal = $row['Meal'];
$cin = $row['cin'];
$cout = $row['cout'];
$noofday = $row['nodays'];
$stat = $row['stat'];

// Date validation - prevent confirming past bookings
$today = date('Y-m-d');
if ($cin < $today) {
    die("<script>
        swal({
            title: 'Cannot Confirm',
            text: 'Past bookings cannot be confirmed',
            icon: 'error'
        }).then(() => {
            window.location.href='roombook.php';
        });
    </script>");
}

if ($cout <= $cin) {
    die("<script>
        swal({
            title: 'Invalid Dates',
            text: 'Check-out must be after check-in',
            icon: 'error'
        }).then(() => {
            window.location.href='roombook.php';
        });
    </script>");
}

if ($stat == "NotConfirm") {
    // Start transaction for atomic operations
    $conn->begin_transaction();
    
    try {
        // Update booking status
        $st = "Confirm";
        $stmt = $conn->prepare("UPDATE roombook SET stat = ? WHERE id = ?");
        $stmt->bind_param("si", $st, $id);
        $stmt->execute();
        
        // Calculate room rates
        $type_of_room = 0;
        switch ($RoomType) {
            case "Superior Room": $type_of_room = 3000; break;
            case "Deluxe Room": $type_of_room = 2000; break;
            case "Guest House": $type_of_room = 1500; break;
            case "Single Room": $type_of_room = 1000; break;
        }
        
        $type_of_bed = 0;
        switch ($Bed) {
            case "Single": $type_of_bed = $type_of_room * 0.01; break;
            case "Double": $type_of_bed = $type_of_room * 0.02; break;
            case "Triple": $type_of_bed = $type_of_room * 0.03; break;
            case "Quad": $type_of_bed = $type_of_room * 0.04; break;
        }
        
        $type_of_meal = 0;
        switch ($Meal) {
            case "Breakfast": $type_of_meal = $type_of_bed * 2; break;
            case "Half Board": $type_of_meal = $type_of_bed * 3; break;
            case "Full Board": $type_of_meal = $type_of_bed * 4; break;
        }
        
        $ttot = $type_of_room * $noofday * $NoofRoom;
        $mepr = $type_of_meal * $noofday;
        $btot = $type_of_bed * $noofday;
        $fintot = $ttot + $mepr + $btot;
        
        // Insert payment with prepared statement
        $stmt = $conn->prepare("INSERT INTO payment(id, Name, Email, RoomType, Bed, NoofRoom, cin, cout, noofdays, roomtotal, bedtotal, meal, mealtotal, finaltotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssdddsdd", $id, $Name, $Email, $RoomType, $Bed, $NoofRoom, $cin, $cout, $noofday, $ttot, $btot, $Meal, $mepr, $fintot);
        $stmt->execute();
        
        // Commit transaction if all queries succeed
        $conn->commit();
        
        header("Location: roombook.php");
        exit();
    } catch (Exception $e) {
        // Rollback if any query fails
        $conn->rollback();
        echo "<script>
            swal({
                title: 'Error',
                text: 'Failed to confirm booking',
                icon: 'error'
            }).then(() => {
                window.location.href = 'roombook.php';
            });
        </script>";
    }
} else {
    echo "<script>
        swal({
            title: 'Already Confirmed',
            text: 'This booking is already confirmed',
            icon: 'info'
        }).then(() => {
            window.location.href = 'roombook.php';
        });
    </script>";
}
?>