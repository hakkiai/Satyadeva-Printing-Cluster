<?php 
include '../database/db_connect.php'; // Ensure this path is correct

// Handle adding a customer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_customer'])) {
    $customer_name = $_POST['customer_name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $email = $_POST['email'] ?? '';
    $gst_number = $_POST['gst_number'] ?? '';
    $firm_name = $_POST['firm_name'] ?? '';
    $firm_location = $_POST['firm_location'] ?? '';
    $address = $_POST['address'] ?? '';
    $is_member = $_POST['is_member'] ?? ''; // Capture customer type

    if (!empty($customer_name) && !empty($phone_number) && !empty($email) && !empty($is_member)) {
        // Check if the customer already exists
        $stmt = $conn->prepare("SELECT id FROM customers WHERE customer_name = ?");
        $stmt->bind_param("s", $customer_name);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo "<script>alert('Customer already exists! Adding a duplicate is not possible.'); window.location.href='add_edit_customers.php';</script>";
        } else {
            // Insert new customer
            $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone_number, email, gst_number, firm_name, firm_location, address, is_member) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $customer_name, $phone_number, $email, $gst_number, $firm_name, $firm_location, $address, $is_member);
            
            if ($stmt->execute()) {
                echo "<script>alert('Customer added successfully!'); window.location.href='add_edit_customers.php';</script>";
            } else {
                echo "<script>alert('Error adding customer.');</script>";
            }
        }
        $stmt->close();
    } else {
        echo "<script>alert('Please fill in all required fields.');</script>";
    }
}

// Handle updating a customer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_customer'])) {
    $id = $_POST['id'];
    $customer_name = $_POST['customer_name'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];
    $gst_number = $_POST['gst_number'];
    $firm_name = $_POST['firm_name'];
    $firm_location = $_POST['firm_location'];
    $address = $_POST['address'];
    $is_member = $_POST['is_member'] ?? ''; // Capture customer type

    $stmt = $conn->prepare("UPDATE customers SET customer_name=?, phone_number=?, email=?, gst_number=?, firm_name=?, firm_location=?, address=?, is_member=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $customer_name, $phone_number, $email, $gst_number, $firm_name, $firm_location, $address, $is_member, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Customer updated successfully!'); window.location.href='add_edit_customers.php';</script>";
    } else {
        echo "<script>alert('Error updating customer.');</script>";
    }
    $stmt->close();
}

// Handle deleting a customer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_customer'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM customers WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "<script>alert('Customer deleted successfully!'); window.location.href='add_edit_customers.php';</script>";
    } else {
        echo "<script>alert('Error deleting customer.');</script>";
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        function editCustomer(customer) {
            document.getElementById('id').value = customer.id;
            document.getElementById('customer_name').value = customer.customer_name;
            document.getElementById('firm_name').value = customer.firm_name;
            document.getElementById('firm_location').value = customer.firm_location;
            document.getElementById('gst_number').value = customer.gst_number;
            document.getElementById('email').value = customer.email;
            document.getElementById('phone_number').value = customer.phone_number;
            document.getElementById('address').value = customer.address;
            // Fix dropdown selection
            let isMemberDropdown = document.getElementById('is_member');
                for (let i = 0; i < isMemberDropdown.options.length; i++) {
                    if (isMemberDropdown.options[i].value === customer.is_member) {
                        isMemberDropdown.selectedIndex = i;
                        break;
                    }
                }

            document.getElementById('edit-section').style.display = 'block';
            document.getElementById('add-section').style.display = 'none';
        }

        function cancelEdit() {
            document.getElementById('edit-section').style.display = 'none';
        }

        // function showAddForm() {
        //     document.getElementById('add-section').style.display = 'block';
        // }
        // document.addEventListener("DOMContentLoaded", function () {
        //     document.getElementById('edit-section').style.display = 'none';
        //     document.getElementById('add-section').style.display = 'none';
        // });

        
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Admin Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='add_vendor.php'">Add Vendor</button>
            <button onclick="location.href='add_edit_customers.php'">Add/Edit Customer</button>
            <button onclick="location.href='admin_inventory.php'">Inventory</button>
            <button onclick="location.href='sales.php'">Sales</button>
            <button onclick="location.href='printing_charges.php'">Printing Charges</button>
            <button onclick="location.href='reports.php'">Reports</button> <!-- New Button -->
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="container" id="search-section">
        <h2>Customer Management</h2>
        <form method="GET">
            <input type="text" name="search_query" class="search-input" placeholder="Search Customer...">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_query']) && !empty($_GET['search_query'])): ?>
    <div class="container">
        <?php
        $search = $conn->real_escape_string($_GET['search_query']);
        $sql = "SELECT * FROM customers WHERE customer_name LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customerJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                echo "<div class='vendor-card' id='customer-" . $row['id'] . "'>
                    <strong class='vendor-name'>" . htmlspecialchars($row['customer_name']) . "</strong>
                    <div class='vendor-actions'>
                        <button class='edit-btn' onclick='editCustomer($customerJson)'>Edit</button>
                        <form method='POST' action='' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this customer?\");'>
                            <input type='hidden' name='id' value='" . $row['id'] . "'>
                            <button type='submit' name='delete_customer' class='delete-btn'>Delete</button>
                        </form>
                    </div>
                    <p>Phone: " . htmlspecialchars($row['phone_number']) . "</p>
                    <p>Email: " . htmlspecialchars($row['email']) . "</p>
                    <p>GST: " . htmlspecialchars($row['gst_number']) . "</p>
                    <p>Firm Name: " . htmlspecialchars($row['firm_name']) . "</p>
                    <p>Location: " . htmlspecialchars($row['firm_location']) . "</p>
                    <p>Address: " . htmlspecialchars($row['address']) . "</p>
                    <p>Customer Type: " . htmlspecialchars($row['is_member'] === 'member' ? 'Member' : 'Non-Member') . "</p>
                </div>";
            }
            echo "<script>document.getElementById('edit-section').style.display = 'none';</script>";
        } else {
            echo "<p id='no-results'>No customers found.</p>";
            // echo "<button id='show-add-form' class='search-btn' onclick='showAddForm()' style='margin-left:250px;'>Add Customer</button>";
        }
        $stmt->close();
        ?>
    </div>
    <?php endif; ?>

    <div id="edit-section" class="container" style="display:none;">
        <h2>Edit Customer</h2>
        <form method="POST" action="" class="form">
            <input type="hidden" name="id" id="id">
            <div class="form-group">
                <label for="customer_name">Customer Name :</label>
                <input type="text" name="customer_name" id="customer_name" placeholder="Enter Customer Name" required>
            </div>
            <div class="form-group">
                <label for="firm_name">Firm Name :</label>
                <input name="firm_name" id="firm_name" placeholder="Enter Firm Name" required>
            </div>
            <div class="form-group">
                <label for="firm_location">Firm Location :</label>
                <input name="firm_location" id="firm_location" placeholder="Enter Firm Location" required>
            </div>
            <div class="form-group">
                <label for="gst_number">GST Number :</label>
                <input name="gst_number" id="gst_number" placeholder="Enter GST Number" required>
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" placeholder="Enter Email" required>
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number :</label>
                <input type="text" name="phone_number" id="phone_number" placeholder="Enter Phone Number" required>
            </div>
            <div class="form-group">
                <label for="address">Address :</label>
                <input type="text" name="address" id="address" placeholder="Enter Address:">
            </div>
            <div class="form-group">
                <label>Customer Type:</label>
                <select name="is_member" id="is_member" required>
                    <option value="">Select Customer Type</option>
                    <option value="member">Member</option>
                    <option value="non-member">Non Member</option>
                </select>
            </div>
            <button type="submit" name="update_customer">Update Customer</button>
            <button type="button" onclick="cancelEdit()">Cancel</button>
        </form>
    </div>

    <div id="add-section" class="container" style="display:block;">
        <h2>Add New Customer</h2>
        <form method="POST" action="" class="form">
            <div class="form-group">
                <label for="customer_name">Customer Name :</label>
                <input type="text" name="customer_name" placeholder="Enter Customer Name" required>
            </div>
            <div class="form-group">
                <label for="firm_name">Firm Name :</label>
                <input name="firm_name" placeholder="Enter Firm Name" required>
            </div>
            <div class="form-group">
                <label for="firm_location">Firm Location :</label>
                <input name="firm_location" placeholder="Enter Firm Location" required>
            </div>
            <div class="form-group">
                <label for="gst_number">GST Number :</label>
                <input name="gst_number" placeholder="Enter GST Number" required>
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" placeholder="Enter Email" required>
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number :</label>
                <input type="text" name="phone_number" placeholder="Enter Phone Number" required>
            </div>
            <div class="form-group">
                <label for="address">Address :</label>
                <input type="text" name="address" placeholder="Enter Address:">
            </div>
            <div class="form-group">
                <label>Customer Type:</label>
                <select name="is_member" required>
                    <option value="">Select Customer Type</option>
                    <option value="member">Member</option>
                    <option value="non-member">Non Member</option>
                </select>
            </div>
            <button type="submit" name="add_customer">Add Customer</button>
        </form>
    </div>
</body>
</html>

