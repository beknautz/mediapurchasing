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

        // BCrypt verify — uses ColdFusion 2021+ native function
        if (!BCryptCheckPassword(arguments.password, u.password_hash)) {
            return { success: false, message: "Invalid email or password." };
        }

        // Update last login
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
    // Hash a password — ColdFusion 2021+ native BCrypt
    // ----------------------------------------------------------------
    public string function hashPassword(required string plaintext) {
        return GenerateBCryptHash(arguments.plaintext);
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
            // CREATE
            if (!structKeyExists(d, "password") || !len(d.password)) {
                return { success: false, message: "Password is required for new users." };
            }
            var hash = hashPassword(d.password);
            queryExecute(
                "INSERT INTO users (name, email, password_hash, role, phone)
                 VALUES (:name, :email, :hash, :role, :phone)",
                {
                    name : { value: d.name,  cfsqltype: "cf_sql_varchar" },
                    email: { value: d.email, cfsqltype: "cf_sql_varchar" },
                    hash : { value: hash,    cfsqltype: "cf_sql_varchar" },
                    role : { value: d.role,  cfsqltype: "cf_sql_varchar" },
                    phone: { value: d.phone ?: "", cfsqltype: "cf_sql_varchar" }
                },
                { datasource: variables.dsn }
            );
            var newId = queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id;
            auditLog("create_user", "user", newId, "Created user #d.name#");
            return { success: true, id: newId };
        } else {
            // UPDATE
            var setParts = "name=:name, email=:email, role=:role, phone=:phone, is_active=:isActive";
            var params = {
                name    : { value: d.name,      cfsqltype: "cf_sql_varchar" },
                email   : { value: d.email,     cfsqltype: "cf_sql_varchar" },
                role    : { value: d.role,      cfsqltype: "cf_sql_varchar" },
                phone   : { value: d.phone ?: "", cfsqltype: "cf_sql_varchar" },
                isActive: { value: d.is_active ?: 1, cfsqltype: "cf_sql_tinyint" },
                id      : { value: d.id,        cfsqltype: "cf_sql_integer" }
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

}
