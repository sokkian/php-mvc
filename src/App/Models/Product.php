<?php

declare(strict_types=1);

namespace App\Models;

use Framework\Model;
use PDO;

/**
 * Product model
 * 
 * Represents a product in the database with CRUD operations
 * and business logic validation.
 * 
 * @package App\Models
 */
class Product extends Model
{
    /**
     * Database table name
     * 
     * Uncomment and set if the table name differs from the class name.
     * By default, it will use "product" (lowercase class name).
     * 
     * @var string
     */
    // protected $table = "products";

    /**
     * Mass-assignable columns
     * 
     * Only these columns can be inserted or updated through insert() and update().
     * This prevents SQL injection through column names and mass-assignment vulnerabilities.
     * 
     * @var array<string>
     */
    protected array $fillable = ['name', 'description'];

    /**
     * Validate product data
     * 
     * Implements business logic validation for product fields.
     * Uses addError() to register validation errors.
     * 
     * @param array $data Data to validate
     * @return void
     */
    protected function validate(array $data): void
    {
        if (empty($data["name"])) {
            
            $this->addError("name", "Name is required");

        }
    }

    /**
     * Get total count of products
     * 
     * @return int Total number of products in the database
     */
    public function getTotal(): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM product";

        $conn = $this->database->getConnection();

        $stmt = $conn->query($sql);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $row["total"];
    }
}