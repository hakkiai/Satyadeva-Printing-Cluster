<?php
include '../database/db_connect.php';

// Handle AJAX request to fetch pricing data
if (isset($_GET['action']) && $_GET['action'] === 'fetch_pricing') {
    $query = "SELECT * FROM pricing_table";
    $result = mysqli_query($conn, $query);
    $pricing_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $pricing_data[$row['category']] = [
            "member" => [
                "first" => (float)$row['member_first'],
                "next" => (float)$row['member_next']
            ],
            "non-member" => [
                "first" => (float)$row['non_member_first'],
                "next" => (float)$row['non_member_next']
            ]
        ];
    }
    echo json_encode($pricing_data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'add') {
        $category = $_POST['category'];
        $member_first = $_POST['member_first'];
        $member_next = $_POST['member_next'];
        $non_member_first = $_POST['non_member_first'];
        $non_member_next = $_POST['non_member_next'];
        $query = "INSERT INTO pricing_table (category, member_first, member_next, non_member_first, non_member_next) VALUES ('$category', $member_first, $member_next, $non_member_first, $non_member_next)";
        mysqli_query($conn, $query);
    } elseif ($action === 'edit') {
        $category = $_POST['category'];
        $member_first = $_POST['member_first'];
        $member_next = $_POST['member_next'];
        $non_member_first = $_POST['non_member_first'];
        $non_member_next = $_POST['non_member_next'];
        $query = "UPDATE pricing_table SET member_first=$member_first, member_next=$member_next, non_member_first=$non_member_first, non_member_next=$non_member_next WHERE category='$category'";
        mysqli_query($conn, $query);
    } elseif ($action === 'delete') {
        $category = $_POST['category'];
        $query = "DELETE FROM pricing_table WHERE category='$category'";
        mysqli_query($conn, $query);
    }
    exit;
}

$query = "SELECT * FROM pricing_table";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printing Charges Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Table Styling */
        #chargesTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.1);
        }
        #chargesTable th {
            background-color: #0056b3;
            color: #ffffff;
            font-weight: bold;
            padding: 14px;
            text-align: center;
            text-transform: uppercase;
        }
        #chargesTable td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        #chargesTable tbody tr:nth-child(even) {
            background-color: #eaf2ff;
        }
        #chargesTable tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        #chargesTable tbody tr:hover {
            background-color: #d4e3ff;
            transition: 0.3s ease-in-out;
        }
        .edit {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .edit:hover {
            background: linear-gradient(135deg, #45a049, #388e3c);
            transform: translateY(-2px);
            box-shadow: 0px 6px 12px rgba(0, 0, 0, 0.2);
        }
        .delete {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .delete:hover {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            transform: translateY(-2px);
            box-shadow: 0px 6px 12px rgba(0, 0, 0, 0.2);
        }
        .save {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .save:hover {
            background: linear-gradient(135deg, #1976D2, #1565C0);
            transform: translateY(-2px);
            box-shadow: 0px 6px 12px rgba(0, 0, 0, 0.2);
        }
        .add-new {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        .add-new:hover {
            background: linear-gradient(135deg, #F57C00, #E65100);
            transform: translateY(-2px);
            box-shadow: 0px 6px 12px rgba(0, 0, 0, 0.2);
        }
        #chargesTable input {
            width: 90%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
        }
    </style>
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
    <div class="container" style="width:70%;">
        <h2>Printing Charges Management</h2>
        <table id="chargesTable">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Member First</th>
                    <th>Member Next</th>
                    <th>Non-Member First</th>
                    <th>Non-Member Next</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                <tr>
                    <td class="editable" data-category="<?php echo $row['category']; ?>"> <?php echo $row['category']; ?> </td>
                    <td class="editable"> <?php echo $row['member_first']; ?> </td>
                    <td class="editable"> <?php echo $row['member_next']; ?> </td>
                    <td class="editable"> <?php echo $row['non_member_first']; ?> </td>
                    <td class="editable"> <?php echo $row['non_member_next']; ?> </td>
                    <td style="display:flex;gap:20px;">
                        <button class="edit" onclick="editRow(this)">Edit</button>
                        <button class="delete" onclick="deleteRow(this)">Delete</button>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <button onclick="addNewRow()" class="add-new">Add New Charge</button>
    </div>

    <script>
        function editRow(button) {
            let row = button.parentNode.parentNode;
            let cells = row.querySelectorAll(".editable");
            
            if (button.textContent === "Edit") {
                cells.forEach(cell => {
                    let input = document.createElement("input");
                    input.type = "text";
                    input.value = cell.innerText;
                    cell.innerHTML = "";
                    cell.appendChild(input);
                });
                button.textContent = "Save";
            } else {
                let data = {
                    action: "edit",
                    category: cells[0].dataset.category,
                    member_first: cells[1].querySelector("input").value,
                    member_next: cells[2].querySelector("input").value,
                    non_member_first: cells[3].querySelector("input").value,
                    non_member_next: cells[4].querySelector("input").value
                };
                fetch("printing_charges.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams(data)
                }).then(() => location.reload());
            }
        }

        function deleteRow(button) {
            let row = button.parentNode.parentNode;
            let category = row.querySelector(".editable").dataset.category;
            fetch("printing_charges.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ action: "delete", category })
            }).then(() => location.reload());
        }

        function addNewRow() {
            let table = document.getElementById("chargesTable").getElementsByTagName('tbody')[0];
            let row = table.insertRow();
            row.innerHTML = `
                <td><input type="text" placeholder="Category"></td>
                <td><input type="text" placeholder="Member First"></td>
                <td><input type="text" placeholder="Member Next"></td>
                <td><input type="text" placeholder="Non-Member First"></td>
                <td><input type="text" placeholder="Non-Member Next"></td>
                <td><button onclick="saveNewRow(this)" class="edit">Save</button></td>
            `;
        }

        function saveNewRow(button) {
            let row = button.parentNode.parentNode;
            let inputs = row.querySelectorAll("input");
            let data = {
                action: "add",
                category: inputs[0].value,
                member_first: inputs[1].value,
                member_next: inputs[2].value,
                non_member_first: inputs[3].value,
                non_member_next: inputs[4].value
            };
            fetch("printing_charges.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams(data)
            }).then(() => location.reload());
        }
    </script>
</body>
</html>