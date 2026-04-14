component {

    this.name               = "MediaBuyingApp";
    this.sessionManagement  = true;
    this.sessionTimeout     = createTimeSpan(0, 2, 0, 0);  // 2 hours
    this.setClientCookies   = true;
    this.secureJSON         = true;

    // Datasource
    this.datasource = "mediapurchasing";

    // Mappings
    this.mappings["/components"] = expandPath("./components");
    this.mappings["/includes"]   = expandPath("./includes");
    this.mappings["/config"]     = expandPath("./config");

    // ----------------------------------------------------------------
    // Application start
    // ----------------------------------------------------------------
    public boolean function onApplicationStart() {
        // Core app constants (defined here so they're always available,
        // even if DB is unreachable during an early error)
        application.appName     = "Media Buying Platform";
        application.appVersion  = "1.0.0";
        application.environment = "dev";   // change to "production" on live server
        application.datasource  = this.datasource;
        application.pageSize    = 25;
        application.bcryptFactor = 12;
        application.uploadPath  = expandPath("./uploads/bills/");
        application.maxUploadMB = 20;
        application.allowedExts = "pdf,jpg,jpeg,png,tif,tiff,csv,xlsx";
        application.reloadPassword = "changeme";

        // Load workflow settings from DB into application scope
        loadSettings();
        return true;
    }

    // ----------------------------------------------------------------
    // Session start
    // ----------------------------------------------------------------
    public void function onSessionStart() {
        session.user    = {};
        session.loggedIn = false;
        session.role    = "";
        session.flash   = "";
    }

    // ----------------------------------------------------------------
    // Request start  — authentication gate
    // ----------------------------------------------------------------
    public boolean function onRequestStart(required string targetPage) {

        // Pages accessible without login
        var publicPages = [
            "/auth/login.cfm",
            "/auth/logout.cfm",
            "/auth/forgot_password.cfm",
            "/auth/reset_password.cfm",
            "/approvals/portal.cfm",      // client-facing approval portal
            "/api/sendgrid_inbound.cfm",  // webhook
            "/api/twilio_sms.cfm",        // webhook
            "/setup.cfm"                  // first-time setup — delete after use
        ];

        var normalizedTarget = "/" & replace(arguments.targetPage, "\", "/", "all");

        var isPublic = false;
        for (var p in publicPages) {
            if (findNoCase(p, normalizedTarget)) {
                isPublic = true;
                break;
            }
        }

        if (!isPublic && !session.loggedIn) {
            location(url="/auth/login.cfm", addtoken=false);
            return false;
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Error handling
    // ----------------------------------------------------------------
    public void function onError(required any exception, required string eventName) {
        var errorDetail = {
            message   : arguments.exception.message   ?: "An unexpected error occurred.",
            detail    : arguments.exception.detail    ?: "",
            stackTrace: arguments.exception.stackTrace ?: ""
        };

        // Log to CF logs
        writeLog(
            file = "mediapurchasing_errors",
            type = "error",
            text = "Error in #arguments.eventName#: #errorDetail.message# | #errorDetail.detail#"
        );

        // Show user-friendly error page
        include "/includes/error.cfm";
    }

    // ----------------------------------------------------------------
    // Load workflow settings into application scope
    // ----------------------------------------------------------------
    private void function loadSettings() {
        try {
            var q = queryExecute(
                "SELECT setting_key, setting_value FROM workflow_settings",
                {},
                { datasource: this.datasource }
            );
            application.settings = {};
            for (var row in q) {
                application.settings[row.setting_key] = row.setting_value;
            }
        } catch (any e) {
            application.settings = {};
        }
    }

}
