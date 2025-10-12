<?php

class Database
{
  private static self $instance;
  private PDO $pdo;

  public static function instance()
  {
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  public function connect($host, $dbname, $user, $pass)
  {
    $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    return $this;
  }

  public function query($sql, $args = [])
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($args);

    return new DatabaseStatement($stmt);
  }

  private function __construct()
  {
  }
}

class DatabaseStatement
{
  private PDOStatement $stmt;

  public function __construct(PDOStatement $stmt)
  {
    $this->stmt = $stmt;
  }

  // Returns as an associative array
  public function fetchEntireList()
  {
    return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  // Return one row
  public function fetchOneRow()
  {
    return $this->stmt->fetch(PDO::FETCH_ASSOC);
  }
}