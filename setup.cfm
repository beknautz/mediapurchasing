<!---
    One-time setup script.
    Run this ONCE from a browser on your server to set the admin password,
    then DELETE or rename this file immediately after.

    Access: https://yourdomain.com/setup.cfm
--->
<cfset dsn = application.datasource ?: "mediapurchasing">

<!--- Only allow from localhost in production --->
<!---
<cfif cgi.remote_addr NEQ "127.0.0.1" AND cgi.remote_addr NEQ "::1">
    <cfabort>
</cfif>
--->

<cfset done      = false>
<cfset errorMsg  = "">
<cfset newHash   = "">

<cfif structKeyExists(form, "password")>
    <cfif len(trim(form.password)) LT 8>
        <cfset errorMsg = "Password must be at least 8 characters.">
    <cfelseif form.password NEQ form.password2>
        <cfset errorMsg = "Passwords do not match.">
    <cfelse>
        <cftry>
            <cfset authSvc = new components.AuthService()>
            <cfset newHash = authSvc.hashPassword(form.password)>

            <!--- Check if admin user exists already --->
            <cfset existing = queryExecute(
                "SELECT id FROM users WHERE email = :email LIMIT 1",
                { email: { value: form.email, cfsqltype: "cf_sql_varchar" } },
                { datasource: dsn }
            )>

            <cfif existing.recordCount>
                <!--- Update existing user --->
                <cfset queryExecute(
                    "UPDATE users SET password_hash = :hash, name = :name, is_active = 1
                      WHERE email = :email",
                    {
                        hash  : { value: newHash,      cfsqltype: "cf_sql_varchar" },
                        name  : { value: form.name,    cfsqltype: "cf_sql_varchar" },
                        email : { value: form.email,   cfsqltype: "cf_sql_varchar" }
                    },
                    { datasource: dsn }
                )>
            <cfelse>
                <!--- Insert new admin --->
                <cfset queryExecute(
                    "INSERT INTO users (name, email, password_hash, role, is_active)
                     VALUES (:name, :email, :hash, 'admin', 1)",
                    {
                        name  : { value: form.name,    cfsqltype: "cf_sql_varchar" },
                        email : { value: form.email,   cfsqltype: "cf_sql_varchar" },
                        hash  : { value: newHash,      cfsqltype: "cf_sql_varchar" }
                    },
                    { datasource: dsn }
                )>
            </cfif>
            <cfset done = true>
        <cfcatch type="any">
            <cfset errorMsg = "DB error: #cfcatch.message#">
        </cfcatch>
        </cftry>
    </cfif>
</cfif>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>First-Time Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container">
<div class="row justify-content-center">
<div class="col-sm-8 col-md-5">

    <div class="text-center mb-4">
        <h2 class="fw-bold">MediaBuy Pro</h2>
        <p class="text-muted">First-Time Admin Setup</p>
    </div>

    <cfif done>
        <div class="card border-success">
            <div class="card-body text-center py-4">
                <h4 class="text-success">Setup Complete!</h4>
                <p class="text-muted">Admin account is ready.<br>
                   <strong>Delete this file from your server now</strong> (<code>setup.cfm</code>).</p>
                <a href="/auth/login.cfm" class="btn btn-primary mt-2">Go to Login</a>
                <cfif len(newHash)>
                    <details class="mt-3 text-start">
                        <summary class="small text-muted">Show generated hash (for SQL seed reference)</summary>
                        <code class="small d-block mt-2 p-2 bg-light border rounded"
                              style="word-break:break-all">#encodeForHTML(newHash)#</code>
                    </details>
                </cfif>
            </div>
        </div>
    <cfelse>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="mb-3">Create Admin Account</h5>

                <cfif len(errorMsg)>
                    <div class="alert alert-danger py-2">#encodeForHTML(errorMsg)#</div>
                </cfif>

                <div class="alert alert-warning py-2 small">
                    <strong>Security:</strong> Delete or rename <code>setup.cfm</code> after running this.
                </div>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Admin Name</label>
                        <input type="text" name="name" class="form-control" required
                               value="#encodeForHTMLAttribute(form.name ?: 'System Admin')#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="#encodeForHTMLAttribute(form.email ?: 'admin@example.com')#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <small class="text-muted">(min 8 chars)</small></label>
                        <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password2" class="form-control" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create Admin Account</button>
                </form>
            </div>
        </div>
    </cfif>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
