<cfset pageTitle = "Forgot Password">
<cfset sent     = false>
<cfset errorMsg = "">

<cfif structKeyExists(form, "email") AND len(trim(form.email))>
    <cfset userQ = queryExecute(
        "SELECT id, name, email FROM users WHERE email=:email AND is_active=1 LIMIT 1",
        { email: { value: form.email, cfsqltype: "cf_sql_varchar" } },
        { datasource: application.datasource }
    )>
    <cfif userQ.recordCount>
        <cfset token   = lCase(replace(createUUID(), "-", "", "all"))>
        <cfset expires = dateAdd("h", 2, now())>
        <cfset queryExecute(
            "UPDATE users SET reset_token=:token, reset_expires=:exp WHERE id=:id",
            {
                token: { value: token,      cfsqltype: "cf_sql_varchar" },
                exp  : { value: expires,    cfsqltype: "cf_sql_timestamp" },
                id   : { value: userQ.id,   cfsqltype: "cf_sql_integer" }
            },
            { datasource: application.datasource }
        )>
        <!--- In production, send this via EmailService; for now just display the link --->
        <cfset resetLink = "#application.settings.app_base_url ?: ''#/auth/reset_password.cfm?token=#token#">
    </cfif>
    <cfset sent = true>
</cfif>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password — MediaBuy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-8 col-md-5 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3"><i class="bi bi-key me-2"></i>Reset Password</h5>
                    <cfif sent>
                        <div class="alert alert-success">
                            If that email is registered, a reset link has been sent.
                        </div>
                        <a href="/auth/login.cfm" class="btn btn-outline-secondary w-100">Back to Login</a>
                    <cfelse>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" required autofocus>
                            </div>
                            <button class="btn btn-primary w-100">Send Reset Link</button>
                        </form>
                        <div class="mt-3 text-center">
                            <a href="/auth/login.cfm" class="small text-muted">Back to login</a>
                        </div>
                    </cfif>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
