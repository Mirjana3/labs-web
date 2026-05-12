<?php
$conn = new mysqli("localhost", "root", "", "videoteka", 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>