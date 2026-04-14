component extends="BaseService" {

    public any function init() {
        super.init();
        return this;
    }

    // ----------------------------------------------------------------
    // Login — returns struct {success, user, message}
    // ----------------------------------------------------------------
    public struct function login(required string email, required string password) {
        var q = queryExecute(
            "SELECT id, name, email, password_hash, role, phone, is_active
               FROM users
              WHERE email = :email
              LIMIT 1",
            { email: { value: arguments.email, cfsqltype: "cf_sql_varchar" } },
            { datasource: variables.dsn }
        );

        if (!q.recordCount) {
            return { success: false, message: "Invalid email or password." };
        }

        var u = q;
        if (!u.is_active) {
            return { success: false, message: "Your account is inactive. Contact an administrator." };
        }

        if (!checkPassword(arguments.password, u.password_hash)) {
            return { success: false, message: "Invalid email or password." };
        }

        queryExecute(
            "UPDATE users SET last_login = NOW() WHERE id = :id",
            { id: { value: u.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        var userStruct = {
            id   : u.id,
            name : u.name,
            email: u.email,
            role : u.role,
            phone: u.phone
        };

        auditLog("login", "user", u.id, "Successful login");

        return { success: true, user: userStruct, message: "Login successful." };
    }

    // ----------------------------------------------------------------
    // Hash a password with PBKDF2-HMAC-SHA256
    // Stored format:  pbkdf2sha256:<iterations>:<saltHex>:<hashHex>
    // Uses only standard Java libraries — no external JARs required.
    // ----------------------------------------------------------------
    public string function hashPassword(required string plaintext) {
        var iterations = 100000;
        var keyLen     = 256;   // bits

        // 16 random bytes for salt — generateSeed() returns a native Java byte[]
        var rng       = createObject("java", "java.security.SecureRandom").init();
        var saltBytes = rng.generateSeed(16);

        var hashHex = _pbkdf2(arguments.plaintext, saltBytes, iterations, keyLen);
        var saltHex = lCase(binaryEncode(saltBytes, "hex"));

        return "pbkdf2sha256:#iterations#:#saltHex#:#hashHex#";
    }

    // ----------------------------------------------------------------
    // Verify a plaintext password against a stored hash
    // ----------------------------------------------------------------
    public boolean function checkPassword(
        required string plaintext,
        required string storedHash
    ) {
        // Format: pbkdf2sha256:<iterations>:<saltHex>:<hashHex>
        if (left(arguments.storedHash, 10) NEQ "pbkdf2sha2") {
            return false;
        }

        var parts = listToArray(arguments.storedHash, ":");
        if (arrayLen(parts) NEQ 4) return false;

        var iterations = val(parts[2]);
        var saltBytes  = binaryDecode(parts[3], "hex");
        var expected   = parts[4];
        var actual     = _pbkdf2(arguments.plaintext, saltBytes, iterations, 256);

        // Constant-time comparison to resist timing attacks
        return _constantEquals(actual, expected);
    }

    // ----------------------------------------------------------------
    // Get all users (admin)
    // ----------------------------------------------------------------
    public query function getUsers(string role = "", numeric page = 1) {
        var sql = "SELECT id, name, email, role, phone, is_active, last_login, created_at
                     FROM users
                    WHERE 1=1";
        var params = {};

        if (len(trim(arguments.role))) {
            sql &= " AND role = :role";
            params["role"] = { value: arguments.role, cfsqltype: "cf_sql_varchar" };
        }
        sql &= " ORDER BY name";
        return queryExecute(sql, params, { datasource: variables.dsn });
    }

    // ----------------------------------------------------------------
    // Save user (create or update)
    // ----------------------------------------------------------------
    public struct function saveUser(required struct data) {
        var d = arguments.data;

        if (!structKeyExists(d, "id") || !d.id) {
            if (!structKeyExists(d, "password") || !len(d.password)) {
                return { success: false, message: "Password is required for new users." };
            }
            var hash = hashPassword(d.password);
            queryExecute(
                "INSERT INTO users (name, email, password_hash, role, phone)
                 VALUES (:name, :email, :hash, :role, :phone)",
                {
                    name : { value: d.name,        cfsqltype: "cf_sql_varchar" },
                    email: { value: d.email,        cfsqltype: "cf_sql_varchar" },
                    hash : { value: hash,           cfsqltype: "cf_sql_varchar" },
                    role : { value: d.role,         cfsqltype: "cf_sql_varchar" },
                    phone: { value: d.phone ?: "",  cfsqltype: "cf_sql_varchar" }
                },
                { datasource: variables.dsn }
            );
            var newId = queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id;
            auditLog("create_user", "user", newId, "Created user #d.name#");
            return { success: true, id: newId };
        } else {
            var setParts = "name=:name, email=:email, role=:role, phone=:phone, is_active=:isActive";
            var params = {
                name    : { value: d.name,           cfsqltype: "cf_sql_varchar"  },
                email   : { value: d.email,          cfsqltype: "cf_sql_varchar"  },
                role    : { value: d.role,           cfsqltype: "cf_sql_varchar"  },
                phone   : { value: d.phone ?: "",    cfsqltype: "cf_sql_varchar"  },
                isActive: { value: d.is_active ?: 1, cfsqltype: "cf_sql_tinyint"  },
                id      : { value: d.id,             cfsqltype: "cf_sql_integer"  }
            };
            if (structKeyExists(d, "password") && len(d.password)) {
                setParts &= ", password_hash=:hash";
                params["hash"] = { value: hashPassword(d.password), cfsqltype: "cf_sql_varchar" };
            }
            queryExecute(
                "UPDATE users SET #setParts# WHERE id=:id",
                params,
                { datasource: variables.dsn }
            );
            auditLog("update_user", "user", d.id, "Updated user #d.name#");
            return { success: true, id: d.id };
        }
    }

    // ----------------------------------------------------------------
    // Require role — redirect if not authorized
    // ----------------------------------------------------------------
    public void function requireRole(required string roles) {
        var allowed = listToArray(arguments.roles);
        if (!session.loggedIn || !arrayFindNoCase(allowed, session.role)) {
            location(url="/dashboard.cfm?error=unauthorized", addtoken=false);
            abort;
        }
    }

    // ================================================================
    // Private helpers
    // ================================================================

    // PBKDF2-HMAC-SHA256 using standard Java — returns lowercase hex
    private string function _pbkdf2(
        required string  password,
        required any     saltBytes,
        required numeric iterations,
        required numeric keyBits
    ) {
        var spec = createObject("java", "javax.crypto.spec.PBEKeySpec").init(
            javaCast("char[]", arguments.password.toCharArray()),
            arguments.saltBytes,
            javaCast("int",    arguments.iterations),
            javaCast("int",    arguments.keyBits)
        );
        var factory   = createObject("java", "javax.crypto.SecretKeyFactory")
                            .getInstance("PBKDF2WithHmacSHA256");
        var hashBytes = factory.generateSecret(spec).getEncoded();
        spec.clearPassword();   // wipe key material from memory
        return lCase(binaryEncode(hashBytes, "hex"));
    }

    // Constant-time string comparison (prevents timing attacks)
    private boolean function _constantEquals(
        required string a,
        required string b
    ) {
        if (len(arguments.a) NEQ len(arguments.b)) return false;
        var result = 0;
        var len    = len(arguments.a);
        for (var i = 1; i <= len; i++) {
            result = bitOr(result, bitXor(asc(mid(arguments.a, i, 1)), asc(mid(arguments.b, i, 1))));
        }
        return (result EQ 0);
    }

}
