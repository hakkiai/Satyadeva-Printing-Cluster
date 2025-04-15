<?php
session_start();
session_unset();        // Optional: Unsets all session variables
session_destroy();      // Destroys the session

// Redirect to home page
header("Location: ../Home/index.php");
exit();
?>
