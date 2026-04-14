<cfif session.loggedIn>
    <cflocation url="/dashboard.cfm" addtoken="false">
</cfif>

<cfset errorMsg = "">
<cfif structKeyExists(form, "email")>
    <cfset authSvc = new components.AuthService()>
    <cfset result  = authSvc.login(form.email, form.password)>
    <cfif result.success>
        <cfset session.loggedIn = true>
        <cfset session.user     = result.user>
        <cfset session.role     = result.user.role>
        <cfset redirect = structKeyExists(url, "redirect") && len(url.redirect) ? url.redirect : "/dashboard.cfm">
        <cflocation url="#redirect#" addtoken="false">
    <cfelse>
        <cfset errorMsg = result.message>
    </cfif>
</cfif>
<cfoutput>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — MediaBuy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-8 col-md-5 col-lg-4">

            <div class="text-center mb-4">
                <h2 class="fw-bold"><i class="bi bi-broadcast-pin me-2 text-primary"></i>MediaBuy Pro</h2>
                <p class="text-muted">Media Buying Automation Platform</p>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3">Sign In</h5>

                    <cfif len(errorMsg)>
                        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-2"></i>#encodeForHTML(errorMsg)#</div>
                    </cfif>

                    <form method="post" action="/auth/login.cfm<cfif structKeyExists(url,'redirect')>?redirect=#encodeForURL(url.redirect)#</cfif>">
                        <div class="mb-3">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="#encodeForHTMLAttribute(form.email ?: '')#"
                                   required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                            </button>
                        </div>
                    </form>

                    <div class="mt-3 text-center">
                        <a href="/auth/forgot_password.cfm" class="small text-muted">Forgot password?</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
</cfoutput>
