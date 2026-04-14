<cfoutput>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><cfif isDefined("pageTitle")>#encodeForHTML(pageTitle)# — </cfif>#application.appName#</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- App CSS -->
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<cfinclude template="/includes/nav.cfm">
<main class="container-fluid py-4">

<!--- Flash message display --->
<cfif len(session.flash ?: "")>
    <cfset flashMsg = session.flash>
    <cfset session.flash = "">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>#encodeForHTML(flashMsg)#
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</cfif>
<cfif isDefined("url.error") AND len(url.error)>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <cfswitch expression="#url.error#">
            <cfcase value="unauthorized">You do not have permission to access that page.</cfcase>
            <cfdefaultcase>#encodeForHTML(url.error)#</cfdefaultcase>
        </cfswitch>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</cfif>
</cfoutput>
