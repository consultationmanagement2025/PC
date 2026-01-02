<?php
$conn = new mysqli("localhost", "root", "", "pc_db");

if ($conn->connect_error) {
    die("Database connection failed");
}
