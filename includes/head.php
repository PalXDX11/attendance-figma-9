<?php
// Shared <head> + opening <body> for the admin pages.
// Set $pageTitle BEFORE including this file.
$pageTitle = $pageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> | Oppa's Samgyeopsal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="icon" href="../assets/images/logo.jpeg">
</head>
<body>
