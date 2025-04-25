<?php
session_start();
include '../config.php';

// Common validation function for both PHP and JS
function validateBookingDates($cin, $cout) {
    $today = strtotime(date('Y-m-d'));
    $checkin = strtotime($cin);
    $checkout = strtotime($cout);

    if ($checkin < $today) {
        return "Check-in date cannot be in the past.";
    }

    if ($checkout <= $checkin) {
        return "Check-out date must be after check-in date.";
    }

    return true;
}

// Handle form submission
if (isset($_POST['guestdetailsubmit'])) {
    $Name = $_POST['Name'] ?? '';
    $Email = $_POST['Email'] ?? '';
    $Country = $_POST['Country'] ?? '';
    $Phone = $_POST['Phone'] ?? '';
    $RoomType = $_POST['RoomType'] ?? '';
    $Bed = $_POST['Bed'] ?? '';
    $NoofRoom = $_POST['NoofRoom'] ?? '';
    $Meal = $_POST['Meal'] ?? '';
    $cin = $_POST['cin'] ?? '';
    $cout = $_POST['cout'] ?? '';
    
    // Validate required fields
    if (empty($Name) || empty($Email) || empty($Country)) {
        $error_message = "Please fill all the required fields.";
    } 
    // Validate dates
    else {
        $dateValidation = validateBookingDates($cin, $cout);
        if ($dateValidation !== true) {
            $error_message = $dateValidation;
        } else {
            // Calculate number of days
            $nodays = date_diff(date_create($cin), date_create($cout))->format('%a');
            
            // Check room availability
            $rsql = "SELECT type, COUNT(*) as count FROM room WHERE type = ? GROUP BY type";
            $stmt = $conn->prepare($rsql);
            $stmt->bind_param("s", $RoomType);
            $stmt->execute();
            $result = $stmt->get_result();
            $roomCount = ($row = $result->fetch_assoc()) ? $row['count'] : 0;
            
            $csql = "SELECT COUNT(*) as count FROM payment WHERE RoomType = ?";
            $stmt = $conn->prepare($csql);
            $stmt->bind_param("s", $RoomType);
            $stmt->execute();
            $result = $stmt->get_result();
            $bookedCount = ($row = $result->fetch_assoc()) ? $row['count'] : 0;
            
            $available = $roomCount - $bookedCount;
            
            if ($available <= 0) {
                $error_message = "Selected room type is fully booked.";
            } else {
                // Insert booking into database
                $sta = "NotConfirm";
                $stmt = $conn->prepare("INSERT INTO roombook(Name, Email, Country, Phone, RoomType, Bed, NoofRoom, Meal, cin, cout, stat, nodays) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssssi", $Name, $Email, $Country, $Phone, $RoomType, $Bed, $NoofRoom, $Meal, $cin, $cout, $sta, $nodays);
                
                if ($stmt->execute()) {
                    $success_message = "Reservation successful!";
                } else {
                    $error_message = "Something went wrong. Please try again.";
                }
            }
        }
    }
}

// Get room availability data for display
$roomTypes = ['Superior Room', 'Deluxe Room', 'Guest House', 'Single Room'];
$availability = [];

foreach ($roomTypes as $type) {
    // Get total rooms of this type
    $rsql = "SELECT COUNT(*) as count FROM room WHERE type = ?";
    $stmt = $conn->prepare($rsql);
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $result = $stmt->get_result();
    $roomCount = ($row = $result->fetch_assoc()) ? $row['count'] : 0;
    
    // Get booked rooms of this type
    $csql = "SELECT COUNT(*) as count FROM payment WHERE RoomType = ?";
    $stmt = $conn->prepare($csql);
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookedCount = ($row = $result->fetch_assoc()) ? $row['count'] : 0;
    
    $availability[$type] = $roomCount - $bookedCount;
}

// Get all bookings for display
$bookings = [];
$roombooktablesql = "SELECT * FROM roombook ORDER BY id DESC";
$roombookresult = mysqli_query($conn, $roombooktablesql);
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
    <title>BlueBird - Admin</title>
</head>

<body>
    <?php
    // Display error or success messages
    if (isset($error_message)) {
        echo "<script>
            swal({
                title: 'Error',
                text: '$error_message',
                icon: 'error'
            });
        </script>";
    }
    
    if (isset($success_message)) {
        echo "<script>
            swal({
                title: 'Success',
                text: '$success_message',
                icon: 'success'
            });
        </script>";
    }
    ?>

    <!-- Guest detail panel -->
    <div id="guestdetailpanel">
        <form action="" method="POST" class="guestdetailpanelform" id="bookingForm">
            <div class="head">
                <h3>RESERVATION</h3>
                <i class="fa-solid fa-circle-xmark" onclick="adduserclose()"></i>
            </div>
            <div class="middle">
                <div class="guestinfo">
                    <h4>Guest information</h4>
                    <input type="text" name="Name" placeholder="Enter Full name" required>
                    <input type="email" name="Email" placeholder="Enter Email" required>

                    <?php
                    $countries = array("Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia and Herzegowina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, the Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", "Croatia (Hrvatska)", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "France Metropolitan", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard and Mc Donald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao, People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Seychelles", "Sierra Leone", "Singapore", "Slovakia (Slovak Republic)", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "Spain", "Sri Lanka", "St. Helena", "St. Pierre and Miquelon", "Sudan", "Suriname", "Svalbard and Jan Mayen Islands", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (U.S.)", "Wallis and Futuna Islands", "Western Sahara", "Yemen", "Yugoslavia", "Zambia", "Zimbabwe");
                    ?>

                    <select name="Country" class="selectinput" required>
                        <option value selected >Select your country</option>
                        <?php
                            foreach($countries as $key => $value):
                            echo '<option value="'.$value.'">'.$value.'</option>';
                            endforeach;
                        ?>
                    </select>
                    <input type="text" name="Phone" placeholder="Enter Phone number" required>
                </div>

                <div class="line"></div>

                <div class="reservationinfo">
                    <h4>Reservation information</h4>
                    <select name="RoomType" class="selectinput" required id="roomTypeSelect">
                        <option value="" selected disabled>Type Of Room</option>
                        <?php foreach($roomTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $availability[$type] <= 0 ? 'disabled' : ''; ?>>
                                <?php echo strtoupper($type); ?> (<?php echo $availability[$type]; ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="Bed" class="selectinput" required>
                        <option value="" selected disabled>Bedding Type</option>
                        <option value="Single">Single</option>
                        <option value="Double">Double</option>
                        <option value="Triple">Triple</option>
                        <option value="Quad">Quad</option>
                        <option value="None">None</option>
                    </select>
                    <select name="NoofRoom" class="selectinput" required>
                        <option value="" selected disabled>No of Room</option>
                        <option value="1">1</option>
                    </select>
                    <select name="Meal" class="selectinput" required>
                        <option value="" selected disabled>Meal</option>
                        <option value="Room only">Room only</option>
                        <option value="Breakfast">Breakfast</option>
                        <option value="Half Board">Half Board</option>
                        <option value="Full Board">Full Board</option>
                    </select>
                    <div class="datesection">
                        <span>
                            <label for="cin"> Check-In</label>
                            <input name="cin" type="date" id="checkin-date" min="<?php echo date('Y-m-d'); ?>" required>
                        </span>
                        <span>
                            <label for="cout"> Check-Out</label>
                            <input name="cout" type="date" id="checkout-date" required disabled>
                        </span>
                    </div>
                </div>
            </div>
            <div class="footer">
                <button class="btn btn-success" name="guestdetailsubmit" type="submit">Submit</button>
            </div>
        </form>
    </div>

    <!-- Search and action section -->
    <div class="searchsection">
        <input type="text" name="search_bar" id="search_bar" placeholder="search..." onkeyup="searchFun()">
        <button class="adduser" id="adduser" onclick="adduseropen()"><i class="fa-solid fa-bookmark"></i> Add</button>
        <form action="./exportdata.php" method="post">
            <button class="exportexcel" id="exportexcel" name="exportexcel" type="submit"><i class="fa-solid fa-file-arrow-down"></i></button>
        </form>
    </div>

    <!-- Bookings table -->
    <div class="roombooktable table-responsive-xl">
        <table class="table table-bordered" id="table-data">
            <thead>
                <tr>
                    <th scope="col">Id</th>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Country</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Type of Room</th>
                    <th scope="col">Type of Bed</th>
                    <th scope="col">No of Room</th>
                    <th scope="col">Meal</th>
                    <th scope="col">Check-In</th>
                    <th scope="col">Check-Out</th>
                    <th scope="col">No of Day</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="action">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($res = mysqli_fetch_array($roombookresult)) : ?>
                <tr>
                    <td><?php echo $res['id'] ?></td>
                    <td><?php echo $res['Name'] ?></td>
                    <td><?php echo $res['Email'] ?></td>
                    <td><?php echo $res['Country'] ?></td>
                    <td><?php echo $res['Phone'] ?></td>
                    <td><?php echo $res['RoomType'] ?></td>
                    <td><?php echo $res['Bed'] ?></td>
                    <td><?php echo $res['NoofRoom'] ?></td>
                    <td><?php echo $res['Meal'] ?></td>
                    <td><?php echo $res['cin'] ?></td>
                    <td><?php echo $res['cout'] ?></td>
                    <td><?php echo $res['nodays'] ?></td>
                    <td><?php echo $res['stat'] ?></td>
                    <td class="action">
                        <?php if($res['stat'] != "Confirm") : ?>
                            <a href="roomconfirm.php?id=<?php echo $res['id'] ?>"><button class='btn btn-success'>Confirm</button></a>
                        <?php endif; ?>
                        <a href="roombookedit.php?id=<?php echo $res['id'] ?>"><button class="btn btn-primary">Edit</button></a>
                        <a href="roombookdelete.php?id=<?php echo $res['id'] ?>"><button class='btn btn-danger'>Delete</button></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
    // Helper function to format date as YYYY-MM-DD
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    // Initialize date fields on load
    document.addEventListener('DOMContentLoaded', function() {
        // Set check-in date default to today
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        const checkinField = document.getElementById('checkin-date');
        const checkoutField = document.getElementById('checkout-date');
        
        if (checkinField) {
            checkinField.value = formatDate(today);
            checkinField.min = formatDate(today);
        }
        
        if (checkoutField) {
            checkoutField.min = formatDate(tomorrow);
            checkoutField.disabled = false;
            checkoutField.value = formatDate(tomorrow);
        }
    });
    
    // Date validation
    document.getElementById('checkin-date')?.addEventListener('change', function() {
        const checkinDate = new Date(this.value);
        const checkoutField = document.getElementById('checkout-date');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Validate check-in date
        if (checkinDate < today) {
            swal({
                title: 'Invalid Date',
                text: 'Check-in date cannot be in the past',
                icon: 'error'
            });
            this.value = formatDate(today);
            checkoutField.disabled = true;
            return;
        }
        
        // Set minimum checkout date to be the day after check-in
        const minCheckout = new Date(checkinDate);
        minCheckout.setDate(minCheckout.getDate() + 1);
        checkoutField.min = formatDate(minCheckout);
        checkoutField.disabled = false;
        
        // Reset checkout if invalid
        const checkoutDate = new Date(checkoutField.value);
        if (checkoutDate <= checkinDate) {
            checkoutField.value = formatDate(minCheckout);
        }
    });
    
    // Form validation before submission
    document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
        const checkinField = document.getElementById('checkin-date');
        const checkoutField = document.getElementById('checkout-date');
        
        if (!checkinField || !checkoutField) return true;
        
        const checkinDate = new Date(checkinField.value);
        const checkoutDate = new Date(checkoutField.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Final validation
        if (checkinDate < today) {
            e.preventDefault();
            swal({
                title: 'Invalid Date',
                text: 'Check-in date cannot be in the past',
                icon: 'error'
            });
            return false;
        }
        
        if (checkoutDate <= checkinDate) {
            e.preventDefault();
            swal({
                title: 'Invalid Date',
                text: 'Check-out date must be after check-in date',
                icon: 'error'
            });
            return false;
        }
        
        return true;
    });
    
    // Search function
    function searchFun() {
        const filter = document.getElementById('search_bar').value.toUpperCase();
        const myTable = document.getElementById("table-data");
        const tr = myTable.getElementsByTagName('tr');

        for (let i = 0; i < tr.length; i++) {
            const tds = tr[i].getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < tds.length; j++) {
                if (tds[j]) {
                    const textValue = tds[j].textContent || tds[j].innerText;
                    if (textValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            
            tr[i].style.display = found ? "" : "none";
        }
    }
    
    // Show/hide guest detail panel
    function adduseropen() {
        document.getElementById('guestdetailpanel').style.display = "flex";
    }
    
    function adduserclose() {
        document.getElementById('guestdetailpanel').style.display = "none";
    }
    </script>
</body>
</html>