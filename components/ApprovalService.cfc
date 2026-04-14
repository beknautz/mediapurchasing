component extends="BaseService" {

    public any function init() {
        super.init();
        return this;
    }

    // ----------------------------------------------------------------
    // Create an approval request — returns the token
    // ----------------------------------------------------------------
    public struct function createApproval(required numeric mediaBuyId, required numeric clientId) {
        var token     = createUUID() & createUUID();
        token         = lCase(replace(token, "-", "", "all"));
        var expiryHrs = val(getSetting("approval_expiry_hours", "72"));
        var expiresAt = dateAdd("h", expiryHrs, now());

        queryExecute(
            "INSERT INTO approvals (media_buy_id, client_id, requested_by, token, status, expires_at)
             VALUES (:buyId, :clientId, :reqBy, :token, 'pending', :expires)",
            {
                buyId   : { value: arguments.mediaBuyId,   cfsqltype: "cf_sql_integer" },
                clientId: { value: arguments.clientId,     cfsqltype: "cf_sql_integer" },
                reqBy   : { value: session.user.id,        cfsqltype: "cf_sql_integer" },
                token   : { value: token,                  cfsqltype: "cf_sql_varchar" },
                expires : { value: expiresAt,              cfsqltype: "cf_sql_timestamp" }
            },
            { datasource: variables.dsn }
        );

        var newId = queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id;

        // Update media buy status
        queryExecute(
            "UPDATE media_buys SET status='pending_client_approval' WHERE id=:id",
            { id: { value: arguments.mediaBuyId, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        auditLog("create_approval", "approval", newId, "Approval requested for buy #arguments.mediaBuyId#");

        return { id: newId, token: token, expiresAt: expiresAt };
    }

    // ----------------------------------------------------------------
    // Get approval by token (for client portal — no auth required)
    // ----------------------------------------------------------------
    public struct function getApprovalByToken(required string token) {
        var q = queryExecute(
            "SELECT a.*, mb.title AS buy_title, mb.media_type, mb.flight_start, mb.flight_end,
                    mb.original_cost, mb.negotiated_cost, mb.description,
                    c.company_name AS client_name, c.contact_name AS client_contact,
                    v.company_name AS vendor_name,
                    u.name AS buyer_name, u.email AS buyer_email
               FROM approvals a
               JOIN media_buys mb ON mb.id = a.media_buy_id
               JOIN clients    c  ON c.id  = a.client_id
               JOIN vendors    v  ON v.id  = mb.vendor_id
               JOIN users      u  ON u.id  = a.requested_by
              WHERE a.token = :token",
            { token: { value: arguments.token, cfsqltype: "cf_sql_varchar" } },
            { datasource: variables.dsn }
        );

        if (!q.recordCount) return { found: false };

        var items = queryExecute(
            "SELECT * FROM media_buy_items WHERE media_buy_id=:id ORDER BY sort_order",
            { id: { value: q.media_buy_id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        return { found: true, approval: q, items: items };
    }

    // ----------------------------------------------------------------
    // Process client response
    // ----------------------------------------------------------------
    public struct function processResponse(
        required string  token,
        required string  response,   // approved | revision_requested | rejected
        string           notes = "",
        string           ip    = ""
    ) {
        var data = getApprovalByToken(arguments.token);
        if (!data.found) return { success: false, message: "Approval not found." };

        var a = data.approval;

        if (a.status != "pending") {
            return { success: false, message: "This approval has already been responded to." };
        }

        if (now() > a.expires_at) {
            queryExecute(
                "UPDATE approvals SET status='expired' WHERE token=:token",
                { token: { value: arguments.token, cfsqltype: "cf_sql_varchar" } },
                { datasource: variables.dsn }
            );
            return { success: false, message: "This approval link has expired." };
        }

        queryExecute(
            "UPDATE approvals SET status=:status, responded_at=NOW(), revision_notes=:notes, ip_address=:ip
              WHERE token=:token",
            {
                status: { value: arguments.response, cfsqltype: "cf_sql_varchar" },
                notes : { value: arguments.notes,    cfsqltype: "cf_sql_longvarchar" },
                ip    : { value: arguments.ip,       cfsqltype: "cf_sql_varchar" },
                token : { value: arguments.token,    cfsqltype: "cf_sql_varchar" }
            },
            { datasource: variables.dsn }
        );

        // Update media buy status
        var buyStatus = "";
        switch (arguments.response) {
            case "approved":           buyStatus = "client_approved"; break;
            case "revision_requested": buyStatus = "client_revision_requested"; break;
            case "rejected":           buyStatus = "cancelled"; break;
        }
        if (len(buyStatus)) {
            queryExecute(
                "UPDATE media_buys SET status=:status WHERE id=:id",
                {
                    status: { value: buyStatus,       cfsqltype: "cf_sql_varchar" },
                    id    : { value: a.media_buy_id,  cfsqltype: "cf_sql_integer" }
                },
                { datasource: variables.dsn }
            );
        }

        auditLog("approval_response", "approval", a.id, "Response: #arguments.response# for buy #a.media_buy_id#");

        return { success: true, response: arguments.response, mediaBuyId: a.media_buy_id };
    }

    // ----------------------------------------------------------------
    // List all approvals for dashboard
    // ----------------------------------------------------------------
    public query function getApprovals(string status = "", numeric page = 1) {
        var sql = "SELECT a.*, mb.title AS buy_title, c.company_name AS client_name
                     FROM approvals a
                     JOIN media_buys mb ON mb.id = a.media_buy_id
                     JOIN clients    c  ON c.id  = a.client_id
                    WHERE 1=1";
        var params = {};

        if (len(arguments.status)) {
            sql &= " AND a.status = :status";
            params["status"] = { value: arguments.status, cfsqltype: "cf_sql_varchar" };
        }
        sql &= " ORDER BY a.requested_at DESC";
        return queryExecute(sql, params, { datasource: variables.dsn });
    }

}
