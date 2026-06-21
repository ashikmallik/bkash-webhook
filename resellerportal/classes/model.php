<?php

class Model
{
    private $conn;

    public function __construct()
    {
        include 'config/db.php';
        $this->conn = $conn;
    }
    
    public function rowSql($sql)
{
    $result = mysqli_query($this->conn, $sql);

    if (!$result) {
        die(mysqli_error($this->conn));
    }

    return mysqli_fetch_assoc($result);
}

public function updateArray($table, $data, $where)
{
    $fields = [];

    foreach ($data as $column => $value) {
        $value = mysqli_real_escape_string($this->conn, $value);
        $fields[] = "`$column`='$value'";
    }

    $sql = "UPDATE `$table`
            SET " . implode(', ', $fields) . "
            WHERE $where";

    $result = mysqli_query($this->conn, $sql);

    if (!$result) {
        die(mysqli_error($this->conn));
    }

    return true;
}

public function insertGetId($sql)
{
    $result = mysqli_query($this->conn, $sql);

    if (!$result) {
        die(mysqli_error($this->conn));
    }

    return mysqli_insert_id($this->conn);
}

public function updateSql($sql)
{
    $result = mysqli_query($this->conn, $sql);

    if (!$result) {
        die(mysqli_error($this->conn));
    }

    return true;
}

    // Get customer by ID
    public function getCustomerById($id)
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT * FROM re_tbl_agent WHERE ag_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        return mysqli_fetch_assoc($result);
    }

    // Get customer by cus_id
    public function getCustomerByCusId($cus_id)
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT * FROM re_tbl_agent WHERE ip = ? AND entry_by != 14"
        );

        mysqli_stmt_bind_param($stmt, "s", $cus_id);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        return mysqli_fetch_assoc($result);
    }
}