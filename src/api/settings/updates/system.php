<?php

function POST()
{
  $system_name = $_POST['system_name'];
  $institution = $_POST['institution'];
  $timezone = $_POST['timezone'];
  $datetime_format = $_POST['datetime_format'];

  Database::instance()->query(
    "UPDATE settings 
      SET 
        system_name = ?, 
        institution = ?, 
        timezone = ?, 
        datetime_format = ? 
    WHERE id = 1",
    [$system_name, $institution, $timezone, $datetime_format]
  );
  
  return "System configuration updated successfully!";
}