<?php

declare(strict_types=1);

namespace Framework;

use PDO;
use App\Database;
use RuntimeException;

/**
 * Base Model class for database operations
 * 
 * Provides common CRUD operations and validation mechanisms for all models.
 * Each concrete model must define the $fillable property to specify which
 * columns can be mass-assigned, preventing SQL injection and mass-assignment
 * vulnerabilities.
 * 
 * @package Framework
 */
abstract class Model
{
    /**
     * Database table name
     * 
     * If not specified, it will be automatically derived from the class name
     * by converting it to lowercase.
     * 
     * @var string|null
     */
    protected $table;

    /**
     * Validation errors
     * 
     * @var array<string, string> Array of field => error message
     */
    protected array $errors = [];

    /**
     * Columns that are mass assignable
     * 
     * Must be defined in each model to prevent SQL injection and mass-assignment
     * vulnerabilities. Only columns listed here can be inserted or updated through
     * the insert() and update() methods.
     * 
     * @var array<string> List of allowed column names
     */
    protected array $fillable = [];

    /**
     * Constructor
     * 
     * @param Database $database Database connection instance
     */
    public function __construct(protected Database $database)
    {
    }

    /**
     * Validates and filters columns to only allow those defined in $fillable
     * 
     * This method ensures that only columns explicitly listed in the $fillable
     * property can be used in insert/update operations, preventing SQL injection
     * through column names and mass-assignment vulnerabilities.
     * 
     * @param array $data Data to validate
     * @return array Filtered data containing only fillable columns
     * @throws RuntimeException if $fillable is not defined in the model
     */
    private function validateAndFilterColumns(array $data): array
    {
        if (empty($this->fillable)) {
            throw new RuntimeException(
                "Property \$fillable must be defined in " . static::class . 
                " to prevent SQL injection and mass-assignment vulnerabilities"
            );
        }
        
        // Filter to only include fillable columns
        $filtered = array_intersect_key($data, array_flip($this->fillable));
        
        // Log ignored columns for debugging (optional)
        $ignored = array_diff_key($data, $filtered);
        if (!empty($ignored)) {
            error_log(
                "Ignored non-fillable columns in " . static::class . ": " 
                . implode(', ', array_keys($ignored))
            );
        }
        
        return $filtered;
    }

    /**
     * Update a record in the database
     * 
     * Updates an existing record with the provided data. Only columns defined
     * in $fillable will be updated. The data is validated before updating.
     * 
     * @param string $id Record ID to update
     * @param array $data Associative array of column => value pairs
     * @return bool True if update succeeded, false if validation failed
     */
    public function update(string $id, array $data): bool
    {
        // Validate and filter columns
        $data = $this->validateAndFilterColumns($data);

        // Validate data
        $this->validate($data);

        if ( ! empty($this->errors)) {
            return false;
        }
        
        $sql = "UPDATE {$this->getTable()} ";

        unset($data["id"]);

        $assignments = array_keys($data);

        array_walk($assignments, function (&$value) {
            $value = "$value = ?";
        });

        $sql .= " SET " . implode(", ", $assignments);

        $sql .= " WHERE id = ?";

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare($sql);

        $i = 1;

        foreach ($data as $value) {

            $type = match(gettype($value)) {
                "boolean" => PDO::PARAM_BOOL,
                "integer" => PDO::PARAM_INT,
                "NULL" => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };

            $stmt->bindValue($i++, $value, $type);

        }

        $stmt->bindValue($i, $id, PDO::PARAM_INT);

        return $stmt->execute();        
    }

    /**
     * Validate data values
     * 
     * Override this method in concrete models to implement business logic
     * validation. Use addError() to register validation errors.
     * 
     * @param array $data Data to validate
     * @return void
     */
    protected function validate(array $data): void
    {
        // Custom implementation for each model
    }

    /**
     * Get the ID of the last inserted record
     * 
     * @return string Last insert ID
     */
    public function getInsertID(): string
    {
        $conn = $this->database->getConnection();

        return $conn->lastInsertId();
    }

    /**
     * Add a validation error
     * 
     * @param string $field Field name that failed validation
     * @param string $message Error message
     * @return void
     */
    protected function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    /**
     * Get all validation errors
     * 
     * @return array<string, string> Array of field => error message
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the table name for this model
     * 
     * If $table property is not set, derives the table name from the class name
     * by converting it to lowercase.
     * 
     * @return string Table name
     */
    private function getTable(): string
    {
        if ($this->table !== null) {

            return $this->table;

        }

        $parts = explode("\\", $this::class);

        return strtolower(array_pop($parts));
    }

    /**
     * Find all records in the table
     * 
     * @return array Array of records as associative arrays
     */
    public function findAll(): array
    {
        $pdo = $this->database->getConnection();

        $sql = "SELECT *
                FROM {$this->getTable()}";

        $stmt = $pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a record by ID
     * 
     * @param string $id Record ID to find
     * @return array|bool Record as associative array, or false if not found
     */
    public function find(string $id): array|bool
    {
        $conn = $this->database->getConnection();

        $sql = "SELECT *
                FROM {$this->getTable()}
                WHERE id = :id";

        $stmt = $conn->prepare($sql);

        $stmt->bindValue(":id", $id, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Insert a new record into the database
     * 
     * Creates a new record with the provided data. Only columns defined in
     * $fillable will be inserted. The data is validated before insertion.
     * 
     * @param array $data Associative array of column => value pairs
     * @return bool True if insert succeeded, false if validation failed
     */
    public function insert(array $data): bool
    {
        // Validate and filter columns first
        $data = $this->validateAndFilterColumns($data);

        $this->validate($data);

        if ( ! empty($this->errors)) {
            return false;
        }

        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));

        $sql = "INSERT INTO {$this->getTable()} ($columns)
                VALUES ($placeholders)";

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare($sql);

        $i = 1;

        foreach ($data as $value) {

            $type = match(gettype($value)) {
                "boolean" => PDO::PARAM_BOOL,
                "integer" => PDO::PARAM_INT,
                "NULL" => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };

            $stmt->bindValue($i++, $value, $type);

        }

        return $stmt->execute();
    }

    /**
     * Delete a record from the database
     * 
     * @param string $id Record ID to delete
     * @return bool True if deletion succeeded, false otherwise
     */
    public function delete(string $id): bool
    {
        $sql = "DELETE FROM {$this->getTable()}
                WHERE id = :id";

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare($sql);

        $stmt->bindValue(":id", $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}