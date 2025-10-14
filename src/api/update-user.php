<?php 

function POST()
{
  $images = $_FILES['file'];
  $destination = '/uploads';

  $uploadedImageList = [];

  foreach($images['name'] as $index => $filename) {
    if (file_exists(CONFIG['buildFilesDirectory'] . "$destination/$filename")) unlink(CONFIG['buildFilesDirectory'] ."$destination/$filename");

    $tmpname = $images['tmp_name'][$index];
    move_uploaded_file($tmpname, CONFIG['buildFilesDirectory'] . "$destination/$filename");

    $uploadedImageList[] = "$destination/$filename";
  }

  // Handle data
  Database::instance()->query(
    "INSERT INTO 
      users (dept, id, name, role, username, photo)
      VALUES (:dept, :id, :name, :role, :username, :photo)
    
    ON DUPLICATE KEY UPDATE
      dept = :dept,
      id = :id,
      name = :name,
      username = :username,
      photo = :photo",
    [
      ':dept' => $_POST['dept'],
      ':id' => $_POST['id'],
      ':name' => $_POST['name'],
      ':role' => "Student",
      ':username' => $_POST['username'],
      ':photo' => json_encode($uploadedImageList)
    ]
  );

  return "Uploaded user successfully!";
}