<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error — #application.appName ?: "MediaBuy Pro"#</title>
    <cfset _env = application.environment ?: "production">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Application Error</h5>
                </div>
                <div class="card-body">
                    <p class="lead">#encodeForHTML(errorDetail.message ?: "An unexpected error occurred.")#</p>
                    <cfif _env EQ "dev" AND len(errorDetail.detail ?: "")>
                        <hr>
                        <pre class="bg-light p-3 rounded small">#encodeForHTML(errorDetail.detail)#</pre>
                    </cfif>
                    <a href="/dashboard.cfm" class="btn btn-primary mt-3">
                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
