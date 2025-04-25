<?php
include '../config.php';

// Fetch room data
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM roombook WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("<script>
        swal({
            title: 'Error',
            text: 'Booking not found',
            icon: 'error'
        }).then(() => {
            window.location.href='roombook.php';
        });
    </script>");
}

// Extract booking data
$Name = $row['Name'];
$Email = $row['Email'];
$Country = $row['Country'];
$Phone = $row['Phone'];
$cin = $row['cin'];
$cout = $row['cout'];
$noofday = $row['nodays'];
$stat = $row['stat'];
$RoomType = $row['RoomType'];
$Bed = $row['Bed'];
$NoofRoom = $row['NoofRoom'];
$Meal = $row['Meal'];

// Get today's date in YYYY-MM-DD format
$today = date('Y-m-d');

// Prevent editing past bookings
if($cin < $today) {
    die("<script>
        swal({
            title: 'Cannot Edit',
            text: 'Past bookings cannot be modified',
            icon: 'error'
        }).then(() => {
            window.location.href='roombook.php';
        });
    </script>");
}

/**
 * Calculate room prices based on selections
 * @param string $roomType The type of room
 * @param string $bedType The type of bed
 * @param string $mealType The type of meal
 * @param int $days Number of days
 * @param int $roomCount Number of rooms
 * @return array Returns array with calculated totals
 */
function calculatePrices($roomType, $bedType, $mealType, $days, $roomCount) {
    // Room type base price
    $type_of_room = 0;
    switch($roomType) {
        case "Superior Room": $type_of_room = 3000; break;
        case "Deluxe Room": $type_of_room = 2000; break;
        case "Guest House": $type_of_room = 1500; break;
        case "Single Room": $type_of_room = 1000; break;
    }
    
    // Bed multiplier
    $type_of_bed = 0;
    switch($bedType) {
        case "Single": $type_of_bed = $type_of_room * 0.01; break;
        case "Double": $type_of_bed = $type_of_room * 0.02; break;
        case "Triple": $type_of_bed = $type_of_room * 0.03; break;
        case "Quad": $type_of_bed = $type_of_room * 0.04; break;
    }
    
    // Meal multiplier
    $type_of_meal = 0;
    switch($mealType) {
        case "Breakfast": $type_of_meal = $type_of_bed * 2; break;
        case "Half Board": $type_of_meal = $type_of_bed * 3; break;
        case "Full Board": $type_of_meal = $type_of_bed * 4; break;
    }
    
    // Calculate totals
    $ttot = $type_of_room * $days * $roomCount;
    $mepr = $type_of_meal * $days;
    $btot = $type_of_bed * $days;
    $fintot = $ttot + $mepr + $btot;
    
    return [
        'roomtotal' => $ttot,
        'bedtotal' => $btot,
        'mealtotal' => $mepr,
        'finaltotal' => $fintot
    ];
}

/**
 * Validate booking dates
 * @param string $cin Check-in date
 * @param string $cout Check-out date
 * @return true|string Returns true if valid, error message if invalid
 */
function validateBookingDates($cin, $cout) {
    $today = date('Y-m-d');
    
    if ($cin < $today) {
        return "Check-in date cannot be in the past.";
    }

    if ($cout <= $cin) {
        return "Check-out date must be after check-in date.";
    }

    return true;
}

// Process form submission
if (isset($_POST['guestdetailedit'])) {
    // Get form data with basic sanitization
    $EditName = trim($_POST['Name']);
    $EditEmail = filter_var($_POST['Email'], FILTER_SANITIZE_EMAIL);
    $EditCountry = trim($_POST['Country']);
    $EditPhone = trim($_POST['Phone']);
    $EditRoomType = trim($_POST['RoomType']);
    $EditBed = trim($_POST['Bed']);
    $EditNoofRoom = intval($_POST['NoofRoom']);
    $EditMeal = trim($_POST['Meal']);
    $Editcin = trim($_POST['cin']);
    $Editcout = trim($_POST['cout']);
    
    // Validate dates
    $dateValidation = validateBookingDates($Editcin, $Editcout);
    if ($dateValidation !== true) {
        echo "<script>
            swal({
                title: 'Invalid Date',
                text: '$dateValidation',
                icon: 'error'
            });
        </script>";
    } else {
        // Calculate number of days
        $Editnoofday = date_diff(date_create($Editcin), date_create($Editcout))->format('%a');
        
        // Use prepared statement for update
        $stmt = $conn->prepare("UPDATE roombook SET Name=?, Email=?, Country=?, Phone=?, RoomType=?, Bed=?, NoofRoom=?, Meal=?, cin=?, cout=?, nodays=? WHERE id=?");
        $stmt->bind_param("sssssssssssi", $EditName, $EditEmail, $EditCountry, $EditPhone, $EditRoomType, $EditBed, $EditNoofRoom, $EditMeal, $Editcin, $Editcout, $Editnoofday, $id);
        
        if ($stmt->execute()) {
            // Calculate prices
            $prices = calculatePrices($EditRoomType, $EditBed, $EditMeal, $Editnoofday, $EditNoofRoom);
            
            // Update payment information
            $stmt = $conn->prepare("UPDATE payment SET Name=?, Email=?, RoomType=?, Bed=?, NoofRoom=?, Meal=?, cin=?, cout=?, noofdays=?, roomtotal=?, bedtotal=?, mealtotal=?, finaltotal=? WHERE id=?");
            $stmt->bind_param("ssssssssddddi", 
                $EditName, 
                $EditEmail, 
                $EditRoomType, 
                $EditBed, 
                $EditNoofRoom, 
                $EditMeal, 
                $Editcin, 
                $Editcout, 
                $Editnoofday, 
                $prices['roomtotal'], 
                $prices['bedtotal'], 
                $prices['mealtotal'], 
                $prices['finaltotal'], 
                $id
            );
            
            if ($stmt->execute()) {
                echo "<script>
                    swal({
                        title: 'Success',
                        text: 'Booking updated successfully',
                        icon: 'success'
                    }).then(() => {
                        window.location.href='roombook.php';
                    });
                </script>";
                exit();
            } else {
                echo "<script>
                    swal({
                        title: 'Error',
                        text: 'Failed to update payment',
                        icon: 'error'
                    });
                </script>";
            }
        } else {
            echo "<script>
                swal({
                    title: 'Error',
                    text: 'Failed to update booking',
                    icon: 'error'
                });
            </script>";
        }
    }
}

// List of countries (abbreviated for clarity)
$countries = array("Afghanistan", "Albania", "Algeria", "...");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- sweet alert -->
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <link rel="stylesheet" href="./css/roombook.css">
    <style>
        #editpanel{
            position: fixed;
            z-index: 1000;
            height: 100%;
            width: 100%;
            display: flex;
            justify-content: center;
            background-color: #00000079;
        }
        #editpanel .guestdetailpanelform{
            height: 620px;
            width: 1170px;
            background-color: #ccdff4;
            border-radius: 10px;  
            position: relative;
            top: 20px;
            animation: guestinfoform .3s ease;
        }
    </style>
    <title>Edit Reservation</title>
</head>
<body>
    <div id="editpanel">
        <form method="POST" class="guestdetailpanelform" id="bookingForm">
            <div class="head">
                <h3>EDIT RESERVATION</h3>
                <a href="./roombook.php"><i class="fa-solid fa-circle-xmark"></i></a>
            </div>
            <div class="middle">
                <div class="guestinfo">
                    <h4>Guest information</h4>
                    <input type="text" name="Name" placeholder="Enter Full name" value="<?php echo htmlspecialchars($Name) ?>" required>
                    <input type="email" name="Email" placeholder="Enter Email" value="<?php echo htmlspecialchars($Email) ?>" required>

                    <select name="Country" class="selectinput" required>
                        <option value="">Select your country</option>
                        <?php foreach($countries as $value): ?>
                            <option value="<?php echo htmlspecialchars($value) ?>" <?php echo ($value == $Country) ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($value) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="Phone" placeholder="Enter Phoneno" value="<?php echo htmlspecialchars($Phone) ?>" required>
                </div>

                <div class="line"></div>

                <div class="reservationinfo">
                    <h4>Reservation information</h4>
                    <select name="RoomType" class="selectinput" required>
                        <option value="">Type Of Room</option>
                        <option value="Superior Room" <?php echo ($RoomType == 'Superior Room') ? 'selected' : '' ?>>SUPERIOR ROOM</option>
                        <option value="Deluxe Room" <?php echo ($RoomType == 'Deluxe Room') ? 'selected' : '' ?>>DELUXE ROOM</option>
                        <option value="Guest House" <?php echo ($RoomType == 'Guest House') ? 'selected' : '' ?>>GUEST HOUSE</option>
                        <option value="Single Room" <?php echo ($RoomType == 'Single Room') ? 'selected' : '' ?>>SINGLE ROOM</option>
                    </select>
                    <select name="Bed" class="selectinput" required>
                        <option value="">Bedding Type</option>
                        <option value="Single" <?php echo ($Bed == 'Single') ? 'selected' : '' ?>>Single</option>
                        <option value="Double" <?php echo ($Bed == 'Double') ? 'selected' : '' ?>>Double</option>
                        <option value="Triple" <?php echo ($Bed == 'Triple') ? 'selected' : '' ?>>Triple</option>
                        <option value="Quad" <?php echo ($Bed == 'Quad') ? 'selected' : '' ?>>Quad</option>
                        <option value="None" <?php echo ($Bed == 'None') ? 'selected' : '' ?>>None</option>
                    </select>
                    <select name="NoofRoom" class="selectinput" required>
                        <option value="">No of Room</option>
                        <option value="1" <?php echo ($NoofRoom == '1') ? 'selected' : '' ?>>1</option>
                    </select>
                    <select name="Meal" class="selectinput" required>
                        <option value="">Meal</option>
                        <option value="Room only" <?php echo ($Meal == 'Room only') ? 'selected' : '' ?>>Room only</option>
                        <option value="Breakfast" <?php echo ($Meal == 'Breakfast') ? 'selected' : '' ?>>Breakfast</option>
                        <option value="Half Board" <?php echo ($Meal == 'Half Board') ? 'selected' : '' ?>>Half Board</option>
                        <option value="Full Board" <?php echo ($Meal == 'Full Board') ? 'selected' : '' ?>>Full Board</option>
                    </select>
                    <div class="datesection">
                        <span>
                            <label for="cin">Check-In</label>
                            <input name="cin" type="date" id="checkin-date" value="<?php echo $cin ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </span>
                        <span>
                            <label for="cout">Check-Out</label>
                            <input name="cout" type="date" id="checkout-date" value="<?php echo $cout ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </span>
                    </div>
                </div>
            </div>
            <div class="footer">
                <button class="btn btn-success" name="guestdetailedit">Update Booking</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get date inputs
        const checkinDateInput = document.getElementById('checkin-date');
        const checkoutDateInput = document.getElementById('checkout-date');
        const bookingForm = document.getElementById('bookingForm');
        
        // Set today's date
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayFormatted = today.toISOString().split('T')[0];
        
        // Update checkout minimum date when check-in changes
        checkinDateInput.addEventListener('change', function() {
            if (!this.value) return;
            
            const checkinDate = new Date(this.value);
            const nextDay = new Date(checkinDate);
            nextDay.setDate(nextDay.getDate() + 1);
            
            // Update minimum checkout date
            const minCheckoutFormatted = nextDay.toISOString().split('T')[0];
            checkoutDateInput.min = minCheckoutFormatted;
            
            // If current checkout date is now invalid, reset it
            if (checkoutDateInput.value && new Date(checkoutDateInput.value) <= checkinDate) {
                checkoutDateInput.value = minCheckoutFormatted;
            }
        });
        
        // Validate form before submission
        bookingForm.addEventListener('submit', function(e) {
            const checkinDate = new Date(checkinDateInput.value);
            const checkoutDate = new Date(checkoutDateInput.value);
            
            // Check if dates are valid
            if (checkinDate < today) {
                e.preventDefault();
                swal({
                    title: 'Invalid Date',
                    text: 'Check-in date cannot be in the past',
                    icon: 'error'
                });
                return;
            }
            
            if (checkoutDate <= checkinDate) {
                e.preventDefault();
                swal({
                    title: 'Invalid Date',
                    text: 'Check-out date must be after check-in date',
                    icon: 'error'
                });
                return;
            }
        });
        
        // Initialize the checkout date minimum value
        if (checkinDateInput.value) {
            const checkinDate = new Date(checkinDateInput.value);
            const nextDay = new Date(checkinDate);
            nextDay.setDate(nextDay.getDate() + 1);
            checkoutDateInput.min = nextDay.toISOString().split('T')[0];
        }
    });
    </script>
</body>
</html>