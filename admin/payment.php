<?php
    session_start();
    include '../config.php';
    
    // Check if user is authenticated
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlueBird - Payment History</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <!-- Sweet Alert -->
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <!-- CSS for table and search bar -->
    <link rel="stylesheet" href="css/roombook.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-4 mt-3">
            <div class="col-md-6">
                <h2>Payment History</h2>
            </div>
            <div class="col-md-6">
                <div class="searchsection">
                    <input type="text" name="search_bar" id="search_bar" placeholder="Search by name, room type..." class="form-control" onkeyup="searchFun()">
                </div>
            </div>
        </div>
        
        <div class="roombooktable table-responsive">
            <?php
                // Prepare and execute statement to prevent SQL injection
                $paymentTableSql = "SELECT * FROM payment ORDER BY id DESC";
                $paymentResult = mysqli_query($conn, $paymentTableSql);
                $numRecords = mysqli_num_rows($paymentResult);
            ?>
            
            <?php if($numRecords > 0): ?>
                <table class="table table-bordered table-hover" id="table-data">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Room Type</th>
                            <th scope="col">Bed Type</th>
                            <th scope="col">Check In</th>
                            <th scope="col">Check Out</th>
                            <th scope="col">Days</th>
                            <th scope="col">Rooms</th>
                            <th scope="col">Meal</th>
                            <th scope="col">Room Rent</th>
                            <th scope="col">Bed Rent</th>
                            <th scope="col">Meals</th>
                            <th scope="col">Total Bill</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($paymentResult)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['RoomType']); ?></td>
                            <td><?php echo htmlspecialchars($row['Bed']); ?></td>
                            <td><?php echo htmlspecialchars($row['cin']); ?></td>
                            <td><?php echo htmlspecialchars($row['cout']); ?></td>
                            <td><?php echo htmlspecialchars($row['noofdays']); ?></td>
                            <td><?php echo htmlspecialchars($row['NoofRoom']); ?></td>
                            <td><?php echo htmlspecialchars($row['meal']); ?></td>
                            <td><?php echo number_format($row['roomtotal'], 2); ?></td>
                            <td><?php echo number_format($row['bedtotal'], 2); ?></td>
                            <td><?php echo number_format($row['mealtotal'], 2); ?></td>
                            <td><?php echo number_format($row['finaltotal'], 2); ?></td>
                            <td class="action">
                                <a href="invoiceprint.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-print me-1"></i>Print
                                </a>
                                <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>">
                                    <i class="fa-solid fa-trash me-1"></i>Delete
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No payment records found.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Advanced search function - searches across multiple columns
        function searchFun() {
            const filter = document.getElementById('search_bar').value.toUpperCase();
            const myTable = document.getElementById("table-data");
            const tr = myTable.getElementsByTagName('tr');
            
            // Skip header row (i=0)
            for(let i = 1; i < tr.length; i++) {
                let display = false;
                // Search in Name (1), RoomType (2), and Bed (3) columns
                const searchColumns = [1, 2, 3];
                
                for(let j = 0; j < searchColumns.length; j++) {
                    const td = tr[i].getElementsByTagName('td')[searchColumns[j]];
                    if (td) {
                        const textValue = td.textContent || td.innerText;
                        if (textValue.toUpperCase().indexOf(filter) > -1) {
                            display = true;
                            break; // Found a match, no need to check other columns
                        }
                    }
                }
                
                tr[i].style.display = display ? "" : "none";
            }
        }
        
        // Add confirmation for delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-btn');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    
                    swal({
                        title: "Are you sure?",
                        text: "Once deleted, you will not be able to recover this payment record!",
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    })
                    .then((willDelete) => {
                        if (willDelete) {
                            window.location.href = `paymantdelete.php?id=${id}`;
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>