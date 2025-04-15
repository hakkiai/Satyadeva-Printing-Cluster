<?php
include '../database/db_connect.php'; // Include the database connection

// Query to fetch pricing data
$sql = "SELECT * FROM pricing_table";
$result = $conn->query($sql);

$pricingData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $category = $row['category'];
        // Map the database category to the expected format in JavaScript
        $mappedCategory = $category;
        if ($category === "RYOBI_COLOR") {
            $mappedCategory = "RYOBI_COLOR"; // Already matches
        } elseif ($category === "RYOBI") {
            $mappedCategory = "RYOBI";
        } elseif ($category === "DC") {
            $mappedCategory = "DC";
        } elseif ($category === "DD") {
            $mappedCategory = "DD";
        } elseif ($category === "SDD") {
            $mappedCategory = "SDD";
        }

        $pricingData[$mappedCategory] = [
            "member" => [
                "first" => (float)$row['member_first'],
                "next" => (float)$row['member_next']
            ],
            "non_member" => [
                "first" => (float)$row['non_member_first'],
                "next" => (float)$row['non_member_next']
            ]
        ];
    }
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($pricingData);

$conn->close();
?>