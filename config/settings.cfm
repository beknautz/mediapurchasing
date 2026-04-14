<cfscript>
// ----------------------------------------------------------------
// config/settings.cfm
// Central configuration — override application.settings values
// loaded from DB, or set dev/prod environment flags here.
// ----------------------------------------------------------------

// App meta
application.appName    = "Media Buying Platform";
application.appVersion = "1.0.0";

// Environment: dev | staging | production
application.environment = "dev";

// BCrypt work factor for password hashing
application.bcryptFactor = 12;

// File upload settings
application.uploadPath    = expandPath("/uploads/bills/");
application.maxUploadMB   = 20;
application.allowedExts   = "pdf,jpg,jpeg,png,tif,tiff,csv,xlsx";

// Pagination
application.pageSize = 25;

// Reload settings (call Application.cfc onApplicationStart manually)
// Hit  /config/reload.cfm?reload=true&reloadPassword=yourpw
application.reloadPassword = "changeme";
</cfscript>
