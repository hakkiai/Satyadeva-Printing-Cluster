<?php 
include '../database/db_connect.php'; // Ensure this path is correct

// Handle adding a vendor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vendor'])) {
    $vendor_name = $_POST['vendor_name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $email = $_POST['email'] ?? '';
    $gst_number = $_POST['gst_number'] ?? '';
    $hsn_number = $_POST['hsn_number'] ?? '';
    $invoice_number = $_POST['invoice_number'] ?? '';
    $date_of_supply = !empty($_POST['date_of_supply']) ? $_POST['date_of_supply'] : date('Y-m-d');


    if (!empty($vendor_name) && !empty($phone_number) && !empty($email)) {
        $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, phone_number, email, gst_number, hsn_number, invoice_number, date_of_supply) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $vendor_name, $phone_number, $email, $gst_number, $hsn_number, $invoice_number, $date_of_supply);
        
        if ($stmt->execute()) {
            echo "<script>alert('Vendor added successfully!'); window.location.href='add_vendor.php';</script>";
        } else {
            echo "<script>alert('Error adding vendor.');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Please fill in all required fields.');</script>";
    }
}

// Handle updating a vendor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_vendor'])) {
    $id = $_POST['id'];
    $vendor_name = $_POST['vendor_name'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];
    $gst_number = $_POST['gst_number'];
    $hsn_number = $_POST['hsn_number'];
    $date_of_supply = !empty($_POST['date_of_supply']) ? $_POST['date_of_supply'] : date('Y-m-d');


    $stmt = $conn->prepare("UPDATE vendors SET vendor_name=?, phone_number=?, email=?, gst_number=?, hsn_number=?,  date_of_supply=? WHERE id=?");
    $stmt->bind_param("ssssssi", $vendor_name, $phone_number, $email, $gst_number, $hsn_number, $date_of_supply, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Vendor updated successfully!'); window.location.href='add_vendor.php';</script>";
    } else {
        echo "<script>alert('Error updating vendor.');</script>";
    }
    $stmt->close();
}

// Handle deleting a vendor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_vendor'])) {
    $vendor_id = $_POST['id'];

    // Check if vendor_id exists in the inventory table
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE vendor_id = ?");
    $checkStmt->bind_param("i", $vendor_id);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        echo "<script>alert('This vendor cannot be deleted as it is linked to inventory.'); window.location.href='add_vendor.php';</script>";
    } else {
        // Proceed with deletion
        $stmt = $conn->prepare("DELETE FROM vendors WHERE vendor_id = ?");
        $stmt->bind_param("i", $vendor_id);

        if ($stmt->execute()) {
            echo "<script>alert('Vendor deleted successfully!'); window.location.href='add_vendor.php';</script>";
        } else {
            echo "<script>alert('Error deleting vendor.');</script>";
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        function editVendor(vendor) {
        console.log(vendor); // Debugging to check the data received

        document.getElementById('id').value = vendor.id || '';
        document.getElementById('vendor_name').value = vendor.vendor_name || '';
        document.getElementById('phone_number').value = vendor.phone_number || '';
        document.getElementById('email').value = vendor.email || '';
        document.getElementById('gst_number').value = vendor.gst_number || '';
        document.getElementById('hsn_number').value = vendor.hsn_number || '';
        document.getElementById('date_of_supply').value = vendor.date_of_supply || '';

        document.getElementById('edit-section').style.display = 'block';
        document.getElementById('add-section').style.display = 'none';


        // Scroll to the form to make editing easy
        window.scrollTo({ top: document.querySelector('.form').offsetTop, behavior: 'smooth' });
    }

    function cancelEdit() {
            document.getElementById('edit-section').style.display = 'none';
            document.getElementById('add-section').style.display = 'block';
        }

    </script>

</head>
<body>
    <div class="navbar">
        <h2 class="brand">Admin Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='add_vendor.php'">Add Vendor</button>
            <button onclick="location.href='add_edit_customers.php'">Add/Edit Customers</button>
            <button onclick="location.href='admin_inventory.php'">Inventory</button>
            <button onclick="location.href='sales.php'">Sales</button>
            <button onclick="location.href='printing_charges.php'">Printing Charges</button>
            <button onclick="location.href='reports.php'">Reports</button> <!-- New Button -->
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>



    <div class="container">
        <h2>Vendor Management</h2>
        <form method="GET">
            <input type="text" name="search_query" class="search-input" placeholder="Search Vendor...">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_query'])): ?>
    <div class="container">
        <?php
        $search = $conn->real_escape_string($_GET['search_query']);
        $sql = "SELECT * FROM vendors WHERE vendor_name LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $vendorJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); // FIXED JSON
                echo "<div class='vendor-card' id='vendor-" . $row['id'] . "'>
                    <strong class='vendor-name'>" . htmlspecialchars($row['vendor_name']) . "</strong>
                    <div class='vendor-actions'>
                        <button class='edit-btn' onclick='editVendor($vendorJson)'>Edit</button>
                        <form method='POST' action='' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this vendor?\");'>
                            <input type='hidden' name='id' value='" . $row['id'] . "'>
                            <button type='submit' name='delete_vendor' class='delete-btn'>Delete</button>
                        </form>
                    </div>
                    <p>Phone: <span>" . htmlspecialchars($row['phone_number']) . "</span></p>
                    <p>Email: <span>" . htmlspecialchars($row['email']) . "</span></p>
                    <p>GST: <span>" . htmlspecialchars($row['gst_number']) . "</span></p>
                    <p>HSN: <span>" . htmlspecialchars($row['hsn_number']) . "</span></p>
                    <p>Registration Date: <span>" . htmlspecialchars($row['date_of_supply']) . "</span></p>
                </div>";
            }
        } else {
            echo "<p>No vendors found.</p>";
        }
        $stmt->close();
        ?>
    </div>
    <?php endif; ?>

    <div id=edit-section class="container" style="display:none">
        <h2>Edit Vendor</h2>
        <form method="POST" action="" class="form">
            <input type="hidden" name="id" id="id"> <!-- Hidden input to store vendor ID -->
            
            <div class="form-group">
                <label for="vendor_name">Vendor Name :</label>
                <input type="text" name="vendor_name" id="vendor_name" placeholder="Enter Vendor Name" required>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number :</label>
                <input type="text" name="phone_number" id="phone_number" placeholder="Enter Phone Number" required>
            </div>

            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" placeholder="Enter Email" required>
            </div>

            <div class="form-group">
                <label for="gst_number">GST Number :</label>
                <input type="text" name="gst_number" id="gst_number" placeholder="Enter GST Number">
            </div>

            <div class="form-group">
                <label for="hsn_number">HSN Number :</label>
                <input type="text" name="hsn_number" id="hsn_number" placeholder="Enter HSN Number">
            </div>

            <div class="form-group">
                <label for="date_of_supply">Registration Date :</label>
                <input type="date" name="date_of_supply" id="date_of_supply">
            </div>
            <button type="submit" name="update_vendor">Update Vendor</button>
            <button type="button" onclick="cancelEdit()">Cancel</button>
        </form>
    </div>
    <div id=add-section class="container" style="display:block">
        <h2>Add Vendor</h2>
        <form method="POST" action="" class="form">
        <input type="hidden" name="id" id="id"> <!-- Hidden input to store vendor ID -->
        
        <div class="form-group">
            <label for="vendor_name">Vendor Name :</label>
            <input type="text" name="vendor_name" id="vendor_name" placeholder="Enter Vendor Name" required>
        </div>

        <div class="form-group">
            <label for="phone_number">Phone Number :</label>
            <input type="text" name="phone_number" id="phone_number" placeholder="Enter Phone Number" required>
        </div>

        <div class="form-group">
            <label for="email">Email :</label>
            <input type="email" name="email" id="email" placeholder="Enter Email" required>
        </div>

        <div class="form-group">
            <label for="gst_number">GST Number :</label>
            <input type="text" name="gst_number" id="gst_number" placeholder="Enter GST Number">
        </div>

        <div class="form-group">
            <label for="hsn_number">HSN Number :</label>
            <input type="text" name="hsn_number" id="hsn_number" placeholder="Enter HSN Number">
        </div>

        <div class="form-group">
            <label for="date_of_supply">Registration Date :</label>
            <input type="date" name="date_of_supply" id="date_of_supply">
        </div>

        <button type="submit" name="add_vendor">Add Vendor</button>
    </form>
</div>
</body>
</html>