<?php 

function POST()
{
  $images = $_FILES['file'];
  $destination = 'dist/uploads/';

  $uploadedImageList = [];

  foreach($images['name'] as $index => $filename) {
    if (file_exists("$destination$filename")) unlink("$destination$filename");

    $tmpname = $images['tmp_name'][$index];
    move_uploaded_file($tmpname, "$destination$filename");

    $uploadedImageList[] = "$destination$filename";
  }

  // Handle data
  Database::instance()->query(
    "INSERT INTO 
      users (dept, id, name, password, role, username, photo)
      VALUES (:dept, :id, :name, :password, :role, :username, :photo)
    
    ON DUPLICATE KEY UPDATE
      dept = :dept,
      id = :id,
      name = :name,
      password = :password,
      role = :role,
      username = :username,
      photo = :photo",
    [
      ':dept' => $_POST['dept'],
      ':id' => $_POST['id'],
      ':name' => $_POST['name'],
      ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
      ':role' => $_POST['role'],
      ':username' => $_POST['username'],
      ':photo' => json_encode($uploadedImageList)
    ]
  );

  return "Uploaded user successfully!";
}