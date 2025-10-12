<?php

function GET()
{
  $users = Database::instance()->query("SELECT * FROM users ORDER BY id DESC", [])->fetchEntireList();
  $logs = Database::instance()->query("SELECT * FROM logs ORDER BY time DESC", [])->fetchEntireList();

  return json(["users" => $users, "logs" => $logs]);
}