<?php
include '../database/db_connect.php';

// Fetch all subcategories and their corresponding items
$subcategories = [];
$items = [];

// Fetch subcategories
$sql = "SELECT id, subcategory_name FROM inventory_subcategories where category_id=(select id from inventory_categories where category_name='Paper')";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row;
}

// Fetch items grouped by subcategory_id
$sql = "SELECT id, item_name, subcategory_id FROM inventory_items";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $items[$row['subcategory_id']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Sheet</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .checkbox-group {
            display: flex;
            flex-direction:row;
            /* flex-wrap: wrap; */
            gap: 10px;
        }
    </style>
    <script>
        function editCustomer(customer) {
            document.querySelector('input[name="customer_name"]').value = customer.customer_name;
            document.querySelector('input[name="phone_number"]').value = customer.phone_number;

            // Scroll to the form for better UX
            document.querySelector('.job-sheet-form').scrollIntoView({ behavior: "smooth" });
        }
        function togglePaperBill() {
            var paperSource = document.getElementById("paper-source").value;
            var paperBillContainer = document.getElementById("paper-bill-container");

            // Show Paper Bill field only if "Customer" is selected
            paperBillContainer.style.display = (paperSource === "Publication") ? "block" : "none";
        }
        function toggleOptions() {
            var ryobiDropdown = document.getElementById("ryobi-options");
            var webDropdown = document.getElementById("web-options");
            var webSubOptions = document.getElementById("web-sub-options");
            var selectedOption = document.querySelector('input[name="printing_type[]"]:checked').value;

            // Show RYOBI dropdown only if RYOBI is selected
            ryobiDropdown.style.display = (selectedOption === "RYOBI") ? "block" : "none";

            // Show Web dropdown only if Web is selected
            webDropdown.style.display = (selectedOption === "Web") ? "block" : "none";

            // Hide web sub-options initially
            webSubOptions.style.display = "none";
        }

        function toggleWebSubOptions() {
            var webType = document.getElementById("webType").value;
            var webSubOptions = document.getElementById("web-sub-options");

            // Show Web Sub-options only if "Black" or "Color" is selected
            if (webType === "black" || webType === "color") {
                webSubOptions.style.display = "block";
            } else {
                webSubOptions.style.display = "none";
            }
        }

        function toggleCTPDropdown() {
            var ctpCheckbox = document.getElementById("ctp-checkbox");
            var ctpDropdown = document.getElementById("ctp-dropdown");

            // Show dropdown if checkbox is checked, otherwise hide it
            ctpDropdown.style.display = ctpCheckbox.checked ? "block" : "none";
        }

        // Store the items in JavaScript for client-side filtering
        var itemsData = <?= json_encode($items) ?>;

        function updateTypeDropdown() {
            var subcategoryId = document.getElementById("paper").value;
            var typeDropdown = document.getElementById("type");
            var typeContainer = document.getElementById("type-container");

            typeDropdown.innerHTML = '<option value="">Select Type</option>';

            if (subcategoryId && itemsData[subcategoryId]) {
                itemsData[subcategoryId].forEach(item => {
                    var option = document.createElement("option");
                    option.value = item.id;
                    option.textContent = item.item_name;
                    typeDropdown.appendChild(option);
                });
                typeContainer.style.display = "block"; // Show the Type dropdown
            } else {
                typeContainer.style.display = "none"; // Hide the Type dropdown if no items found
            }
        }
    </script>
</head>
<body>

    <div class="navbar">
        <h2 class="brand">Receptionist Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='reception_quotation.php'">Add Quotation</button>
            <button onclick="location.href='pending_list.php'">Pending List</button>
            <button onclick="location.href='job_sheet.php'">Job Sheet</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="container">
        <h2>Customer Management</h2>
        <h3 style="padding-left:20px;">Select Customer</h3>
        <form method="GET">
            <input type="text" name="search_query" class="search-input" placeholder="Search Customer...">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_query'])): ?>
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
                    </div>
                    <p>Phone: " . htmlspecialchars($row['phone_number']) . "</p>
                </div>";
            }
        } else {
            echo "<p>No customers found.</p>";
        }
        $stmt->close();
        ?>
    </div>
    <?php endif; ?>

    <div class="container job-sheet-form">
        <h2>Job Sheet</h2>
        <div action="reception_quotation.php" method="POST" class="form">
            <div class="form-group">
                <label>Customer Name:</label>
                <input type="text" name="customer_name" placeholder="Enter Customer Name" required>
            </div>
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="text" name="phone_number" placeholder="Enter Phone Number" required>
            </div>
            <div class="form-group">
                <label>Job Name:</label>
                <input type="text" name="job_name" placeholder="Enter Job Name" required>
            </div>
            <div class="form-group">
                <label>Paper (Subcategory):</label>
                <select name="paper" id="paper" onchange="updateTypeDropdown()">
                    <option value="">Select Paper</option>
                    <?php foreach ($subcategories as $subcategory): ?>
                        <option value="<?= $subcategory['id'] ?>"><?= htmlspecialchars($subcategory['subcategory_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Type Dropdown (Initially Hidden) -->
            <div class="form-group" id="type-container" style="display: none;">
                <label>Type:</label>
                <select name="type" id="type">
                    <option value="">Select Type</option>
                </select>
            </div>

            <div class="form-group">
                <label>Quantity:</label>
                <input type="number" name="quantity" placeholder="Enter Quantity" required>
            </div>

            <div class="form-group">
                <label>Paper Source:</label>
                <select name="paper_source" id="paper-source" onchange="togglePaperBill()">
                    <option value="select">Select Paper Source</option>
                    <option value="Customer">Customer</option>
                    <option value="Publication">Publication</option>
                </select>
            </div>

            <div class="form-group">
                <label>Printing Type:</label>
                <div class="checkbox-group">
                    <input type="radio" name="printing_type[]" value="DD" onchange="toggleOptions()"> D/D
                    <input type="radio" name="printing_type[]" value="DC" onchange="toggleOptions()"> D/C
                    <input type="radio" name="printing_type[]" value="SD" onchange="toggleOptions()"> S/D
                    <input type="radio" name="printing_type[]" value="RYOBI" onchange="toggleOptions()"> RYOBI
                    <input type="radio" name="printing_type[]" value="Web" onchange="toggleOptions()"> Web
                </div>
            </div>

            <div class="form-group" id="ryobi-options" style="display: none;">
                <label>RYOBI Type:</label>
                <select name="ryobi_type">
                    <option>Select RYOBI Type</option>
                    <option value="black">Black</option>
                    <option value="color">Color</option>
                </select>
            </div>

            <div class="form-group" id="web-options" style="display: none;">
                <label>Web Type:</label>
                <select name="web_type" id="webType" onchange="toggleWebSubOptions()">
                    <option value="">Select web color</option>
                    <option value="black">Black</option>
                    <option value="color">Color</option>
                </select>
            </div>

            <div class="form-group" id="web-sub-options" style="display: none;">
                <label>No of Papers:</label>
                <select name="web_size">
                    <option>Select Pages</option>
                    <option value="8">8</option>
                    <option value="16">16</option>
                </select>
            </div>

            <div class="form-group">
                <label>Striking:</label>
                <select name="striking">
                    <option value="select">Select striking type</option>
                    <option value="Customer">One Side</option>
                    <option value="Company">Back and Back</option>
                    <option value="Company">Gripper to tale</option>
                    <option value="Company">Front and Back</option>
                </select>
            </div>

            <div class="form-group">
                <label>CTP:</label>
                <input type="checkbox" id="ctp-checkbox" onchange="toggleCTPDropdown()">   
            </div>

            <!-- CTP Size Dropdown -->
            <div class="form-group" id="ctp-dropdown" style="display: none;">
                <label>Select CTP Size:</label>
                <select name="ctp_size">
                    <option value="700x945">700x945</option>
                    <option value="610x890">610x890</option>
                    <option value="605x760">605x760</option>
                    <option value="560x670">560x670</option>
                    <option value="335x485">335x485</option>
                </select>
            </div>
            <div class="form-group">
                <label>Digital:</label>
                <input placeholder="Enter details:"></input>
            </div>

            <div class="form-group">
                <label>No. of Plates:</label>
                <input type="number" name="plates" placeholder="Enter no of Plates" required>
            </div>
            
            <!-- Paper Bill Field (Initially Hidden) -->
            <div class="form-group" id="paper-bill-container" style="display: none;">
                <label>Paper Bill Amount (in Rs.):</label>
                <input type="number" name="paper_bill" id="paper-bill">
            </div>

            <div class="form-group">
                <label>Printing:</label>
                <input type="text" name="printing" placeholder="Enter Printing details">
            </div>

            <div class="form-group">
                <label>Cutting:</label>
                <input type="text" name="cutting" placeholder="Enter Cutting details">
            </div>

            <div class="form-group">
                <label>Lamination:</label>
                <input type="text" name="lamination" placeholder="Enter Lamination details">
            </div>

            <div class="form-group">
                <label>Pinning:</label>
                <input type="text" name="pinning" placeholder="Enter Pinning details">
            </div>

            <div class="form-group">
                <label>Binding:</label>
                <input type="text" name="binding" placeholder="Enter Binding details">
            </div>

            <div class="form-group">
                <label>Finishing:</label>
                <input type="text" name="finishing" placeholder="Enter Finishing details">
            </div>

            <div class="form-group">
                <label>Others:</label>
                <input type="text" name="others" placeholder="Enter Other details">
            </div>

            <div class="form-group">
                <label>Bill Amount (in Rs.):</label>
                <input type="number" name="bill_amount" required>
            </div>
            <div class="form-group">
                <label>Amount in Words:</label>
                <input type="text" name="amount_words" required>
            </div>
            <button type="submit">Submit</button>
        </form>
    </div>

</body>
</html>
