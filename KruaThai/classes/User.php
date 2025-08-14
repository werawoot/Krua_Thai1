<?php
/**
 * Krua Thai - User Management Class
 * File: classes/User.php
 * Description: Complete user management with authentication, verification, and profile management
 */

class User {
    private $conn;
    private $table_name = "users";

    // User properties matching database schema
    public $id;
    public $email;
    public $password_hash;
    public $first_name;
    public $last_name;
    public $phone;
    public $date_of_birth;
    public $gender;
    public $role = 'customer';
    public $status = 'pending_verification';
    public $email_verified = 0;
    public $email_verification_token;
    public $delivery_address;
    public $address_line_2;
    public $city;
    public $state;
    public $zip_code;
    public $country = 'Thailand';
    public $delivery_instructions;
    public $dietary_preferences;
    public $allergies;
    public $spice_level = 'medium';
    public $registration_method;
    public $last_login;
    public $failed_login_attempts = 0;
    public $locked_until;
    public $password_reset_token;
    public $password_reset_expires;
    public $created_at;
    public $updated_at;

    /**
     * Constructor
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Check if email already exists
     * @return bool True if exists, false otherwise
     */
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if phone number already exists
     * @return bool True if exists, false otherwise
     */
    public function phoneExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE phone = :phone LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Create new user account
     * @return bool True if successful, false otherwise
     */
    public function create() {
        // Generate UUID and verification token
        $this->id = generateUUID();
        $this->email_verification_token = generateToken();
        
        // Hash password
        if (!empty($this->password_hash)) {
            $this->password_hash = password_hash($this->password_hash, PASSWORD_BCRYPT);
        }

      $query = "INSERT INTO " . $this->table_name . " 
          (id, email, password_hash, first_name, last_name, phone, date_of_birth, 
           gender, role, status, email_verified, email_verification_token, 
           delivery_address, address_line_2, city, state, zip_code, country, 
           delivery_instructions, dietary_preferences, allergies, spice_level,
           registration_method, created_at, updated_at) 
          VALUES 
          (:id, :email, :password_hash, :first_name, :last_name, :phone, :date_of_birth, 
           :gender, :role, :status, :email_verified, :email_verification_token, 
           :delivery_address, :address_line_2, :city, :state, :zip_code, :country, 
           :delivery_instructions, :dietary_preferences, :allergies, :spice_level,
           :registration_method, NOW(), NOW())";
        $stmt = $this->conn->prepare($query);

        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':date_of_birth', $this->date_of_birth);
        $stmt->bindParam(':gender', $this->gender);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':email_verified', $this->email_verified);
        $stmt->bindParam(':email_verification_token', $this->email_verification_token);
        $stmt->bindParam(':delivery_address', $this->delivery_address);
        $stmt->bindParam(':address_line_2', $this->address_line_2);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':state', $this->state);
        $stmt->bindParam(':zip_code', $this->zip_code);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':delivery_instructions', $this->delivery_instructions);
        $stmt->bindParam(':dietary_preferences', $this->dietary_preferences);
        $stmt->bindParam(':allergies', $this->allergies);
        $stmt->bindParam(':spice_level', $this->spice_level);
        $stmt->bindParam(':registration_method', $this->registration_method); // ← เพิ่มนี้


        try {
            if ($stmt->execute()) {
                // Log the registration
                logActivity('user_registered', $this->id, getRealIPAddress(), [
                    'email' => $this->email,
                    'name' => $this->first_name . ' ' . $this->last_name
                ]);
                return true;
            }
        } catch (PDOException $e) {
            error_log("User creation failed: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Verify email using token
     * @param string $token Verification token
     * @return bool True if successful, false otherwise
     */
    public function verifyEmail($token) {
        $query = "UPDATE " . $this->table_name . " 
                  SET email_verified = 1, status = 'active', email_verification_token = NULL, updated_at = NOW() 
                  WHERE email_verification_token = :token AND email_verified = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        
        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Log email verification
                logActivity('email_verified', null, getRealIPAddress(), [
                    'token' => substr($token, 0, 10) . '...'
                ]);
                return true;
            }
        } catch (PDOException $e) {
            error_log("Email verification failed: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Get user by email
     * @param string $email User email
     * @return bool True if found, false otherwise
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $this->populateFromArray($row);
            return true;
        }
        return false;
    }

    /**
     * Get user by ID
     * @param string $id User ID
     * @return bool True if found, false otherwise
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $this->populateFromArray($row);
            return true;
        }
        return false;
    }

    /**
     * Authenticate user login
     * @param string $email User email
     * @param string $password Plain text password
     * @return array Result with success status and messages
     */
    public function authenticate($email, $password) {
        $result = [
            'success' => false,
            'message' => '',
            'user_id' => null,
            'requires_verification' => false,
            'account_locked' => false
        ];

        if (!$this->getByEmail($email)) {
            $result['message'] = 'Invalid email or password';
            logActivity('login_failed', null, getRealIPAddress(), ['email' => $email, 'reason' => 'email_not_found']);
            return $result;
        }

        // Check if account is locked
        if ($this->locked_until && strtotime($this->locked_until) > time()) {
            $result['account_locked'] = true;
            $result['message'] = 'Account is temporarily locked due to multiple failed login attempts. Please try again later.';
            logActivity('login_blocked', $this->id, getRealIPAddress(), ['reason' => 'account_locked']);
            return $result;
        }

        // Check account status
        if ($this->status === 'pending_verification') {
            $result['requires_verification'] = true;
            $result['message'] = 'Please verify your email address before logging in.';
            return $result;
        }

        if ($this->status === 'suspended') {
            $result['message'] = 'Your account has been suspended. Please contact support.';
            logActivity('login_blocked', $this->id, getRealIPAddress(), ['reason' => 'account_suspended']);
            return $result;
        }

        if ($this->status === 'inactive') {
            $result['message'] = 'Your account is inactive. Please contact support.';
            logActivity('login_blocked', $this->id, getRealIPAddress(), ['reason' => 'account_inactive']);
            return $result;
        }

        // Verify password
        if (password_verify($password, $this->password_hash)) {
            // Successful login
            $this->updateLastLogin();
            $this->resetFailedAttempts();
            
            $result['success'] = true;
            $result['user_id'] = $this->id;
            $result['message'] = 'Login successful';
            
            logActivity('login_success', $this->id, getRealIPAddress(), [
                'email' => $this->email,
                'role' => $this->role
            ]);
        } else {
            // Failed login
            $this->incrementFailedAttempts();
            $result['message'] = 'Invalid email or password';
            
            logActivity('login_failed', $this->id, getRealIPAddress(), [
                'email' => $email,
                'reason' => 'wrong_password',
                'attempts' => $this->failed_login_attempts + 1
            ]);
        }

        return $result;
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . " 
                  SET last_login = NOW(), updated_at = NOW() 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    /**
     * Reset failed login attempts
     */
    private function resetFailedAttempts() {
        $query = "UPDATE " . $this->table_name . " 
                  SET failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    /**
     * Increment failed login attempts and lock if necessary
     */
    private function incrementFailedAttempts() {
        $new_attempts = $this->failed_login_attempts + 1;
        $locked_until = null;
        
        // Lock account for 15 minutes after 5 failed attempts
        if ($new_attempts >= 5) {
            $locked_until = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutes
        }
        
        $query = "UPDATE " . $this->table_name . " 
                  SET failed_login_attempts = :attempts, locked_until = :locked_until, updated_at = NOW() 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':attempts', $new_attempts);
        $stmt->bindParam(':locked_until', $locked_until);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    /**
     * Generate password reset token
     * @return string Reset token
     */
    public function generatePasswordResetToken() {
        $token = generateToken();
        $expires = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour
        
        $query = "UPDATE " . $this->table_name . " 
                  SET password_reset_token = :token, password_reset_expires = :expires, updated_at = NOW() 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires', $expires);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            logActivity('password_reset_requested', $this->id, getRealIPAddress(), [
                'email' => $this->email
            ]);
            return $token;
        }
        
        return false;
    }

    /**
     * Reset password using token
     * @param string $token Reset token
     * @param string $new_password New password
     * @return bool True if successful, false otherwise
     */
    public function resetPassword($token, $new_password) {
        // Verify token and check expiry
        $query = "SELECT id, email, first_name FROM " . $this->table_name . " 
                  WHERE password_reset_token = :token 
                  AND password_reset_expires > NOW() 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return false;
        }
        
        $user_data = $stmt->fetch();
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Update password and clear reset token
        $query = "UPDATE " . $this->table_name . " 
                  SET password_hash = :password, password_reset_token = NULL, 
                      password_reset_expires = NULL, failed_login_attempts = 0, 
                      locked_until = NULL, updated_at = NOW() 
                  WHERE password_reset_token = :token";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':token', $token);
        
        if ($stmt->execute()) {
            logActivity('password_reset_completed', $user_data['id'], getRealIPAddress(), [
                'email' => $user_data['email']
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Update user profile
     * @param array $data Profile data
     * @return bool True if successful, false otherwise
     */
    public function updateProfile($data) {
        $allowed_fields = [
            'first_name', 'last_name', 'phone', 'date_of_birth', 'gender',
            'delivery_address', 'address_line_2', 'city', 'state', 'zip_code',
            'delivery_instructions', 'dietary_preferences', 'allergies', 'spice_level'
        ];
        
        $update_fields = [];
        $params = [':id' => $this->id];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_fields[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }
        
        if (empty($update_fields)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " 
                  SET " . implode(', ', $update_fields) . ", updated_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            logActivity('profile_updated', $this->id, getRealIPAddress(), [
                'updated_fields' => array_keys($data)
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Change user password
     * @param string $current_password Current password
     * @param string $new_password New password
     * @return array Result with success status and message
     */
    public function changePassword($current_password, $new_password) {
        $result = ['success' => false, 'message' => ''];
        
        // Verify current password
        if (!password_verify($current_password, $this->password_hash)) {
            $result['message'] = 'Current password is incorrect';
            logActivity('password_change_failed', $this->id, getRealIPAddress(), [
                'reason' => 'wrong_current_password'
            ]);
            return $result;
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $query = "UPDATE " . $this->table_name . " 
                  SET password_hash = :password, updated_at = NOW() 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            $result['success'] = true;
            $result['message'] = 'Password changed successfully';
            logActivity('password_changed', $this->id, getRealIPAddress());
        } else {
            $result['message'] = 'Failed to update password';
        }
        
        return $result;
    }

    /**
     * Get user's full name
     * @return string Full name
     */
    public function getFullName() {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get user's dietary preferences as array
     * @return array Dietary preferences
     */
    public function getDietaryPreferences() {
        if (empty($this->dietary_preferences)) {
            return [];
        }
        
        $preferences = json_decode($this->dietary_preferences, true);
        return is_array($preferences) ? $preferences : [];
    }

    /**
     * Get user's allergies as array
     * @return array Allergies
     */
    public function getAllergies() {
        if (empty($this->allergies)) {
            return [];
        }
        
        $allergies = json_decode($this->allergies, true);
        return is_array($allergies) ? $allergies : [];
    }

    /**
     * Check if user has specific role
     * @param string $role Role to check
     * @return bool True if user has role, false otherwise
     */
    public function hasRole($role) {
        return $this->role === $role;
    }

    /**
     * Check if user is admin
     * @return bool True if admin, false otherwise
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }

    /**
     * Check if account is active
     * @return bool True if active, false otherwise
     */
    public function isActive() {
        return $this->status === 'active';
    }

    /**
     * Check if email is verified
     * @return bool True if verified, false otherwise
     */
    public function isEmailVerified() {
        return $this->email_verified == 1;
    }

    /**
     * Get all users (admin function)
     * @param array $filters Filter criteria
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Users data
     */
    public function getAllUsers($filters = [], $limit = 50, $offset = 0) {
        $where_conditions = [];
        $params = [];
        
        // Build WHERE clause from filters
        if (!empty($filters['role'])) {
            $where_conditions[] = "role = :role";
            $params[':role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT id, email, first_name, last_name, phone, role, status, 
                         email_verified, last_login, created_at 
                  FROM " . $this->table_name . " 
                  $where_clause 
                  ORDER BY created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count total users
     * @param array $filters Filter criteria
     * @return int Total count
     */
    public function countUsers($filters = []) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['role'])) {
            $where_conditions[] = "role = :role";
            $params[':role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " $where_clause";
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }

    /**
     * Update user status (admin function)
     * @param string $user_id User ID
     * @param string $status New status
     * @return bool True if successful, false otherwise
     */
    public function updateUserStatus($user_id, $status) {
        $allowed_statuses = ['active', 'inactive', 'suspended', 'pending_verification'];
        
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = NOW() 
                  WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            logActivity('user_status_updated', $user_id, getRealIPAddress(), [
                'new_status' => $status,
                'updated_by' => $this->id
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Populate object properties from array
     * @param array $data Data array
     */
    private function populateFromArray($data) {
  $properties = [
    'id', 'email', 'password_hash', 'first_name', 'last_name', 'phone',
    'date_of_birth', 'gender', 'role', 'status', 'email_verified',
    'email_verification_token', 'delivery_address', 'address_line_2',
    'city', 'state', 'zip_code', 'country', 'delivery_instructions',
    'dietary_preferences', 'allergies', 'spice_level', 'registration_method',
    'last_login', 'failed_login_attempts', 'locked_until', 'password_reset_token',
    'password_reset_expires', 'created_at', 'updated_at'
];
        
        foreach ($properties as $property) {
            if (isset($data[$property])) {
                $this->$property = $data[$property];
            }
        }
    }

    /**
     * Resend verification email
     * @return bool True if successful, false otherwise
     */
    public function resendVerificationEmail() {
        if ($this->email_verified) {
            return false; // Already verified
        }
        
        // Generate new token if needed
        if (empty($this->email_verification_token)) {
            $this->email_verification_token = generateToken();
            $query = "UPDATE " . $this->table_name . " 
                      SET email_verification_token = :token, updated_at = NOW() 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $this->email_verification_token);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
        }
        
        // Send email
        $sent = sendVerificationEmail($this->email, $this->first_name, $this->email_verification_token);
        
        if ($sent) {
            logActivity('verification_email_resent', $this->id, getRealIPAddress(), [
                'email' => $this->email
            ]);
        }
        
        return $sent;
    }
}
?>