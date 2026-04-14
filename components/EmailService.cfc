component extends="BaseService" {

    public any function init() {
        super.init();
        return this;
    }

    // ----------------------------------------------------------------
    // Send email via SendGrid HTTP API
    // ----------------------------------------------------------------
    public struct function send(
        required string toEmail,
        required string toName,
        required string subject,
        required string bodyHtml,
        string   bodyText   = "",
        string   fromEmail  = "",
        string   fromName   = "",
        numeric  mediaBuyId = 0,
        numeric  approvalId = 0,
        numeric  billId     = 0
    ) {
        var apiKey   = getSetting("sendgrid_api_key", "");
        var fromAddr = len(arguments.fromEmail) ? arguments.fromEmail : getSetting("sendgrid_from_email", "noreply@youragency.com");
        var fromNm   = len(arguments.fromName)  ? arguments.fromName  : getSetting("sendgrid_from_name",  "Media Buying Team");

        if (!len(apiKey)) {
            return { success: false, message: "SendGrid API key not configured." };
        }

        var payload = {
            "personalizations": [{
                "to": [{ "email": arguments.toEmail, "name": arguments.toName }]
            }],
            "from"   : { "email": fromAddr, "name": fromNm },
            "subject": arguments.subject,
            "content": [
                { "type": "text/plain", "value": len(arguments.bodyText) ? arguments.bodyText : stripTags(arguments.bodyHtml) },
                { "type": "text/html",  "value": arguments.bodyHtml }
            ]
        };

        var response = "";
        var msgId    = "";

        try {
            var httpResult = "";
            cfhttp(
                method  = "POST",
                url     = "https://api.sendgrid.com/v3/mail/send",
                result  = "httpResult"
            ) {
                cfhttpparam(type="header", name="Authorization", value="Bearer #apiKey#");
                cfhttpparam(type="header", name="Content-Type",  value="application/json");
                cfhttpparam(type="body",   value=serializeJSON(payload));
            }

            var success = (httpResult.statusCode contains "202" || httpResult.statusCode contains "200");
            msgId       = httpResult.responseheader["X-Message-Id"] ?: "";

            logCommunication(
                type        = "email",
                direction   = "outbound",
                fromAddress = fromAddr,
                toAddress   = arguments.toEmail,
                subject     = arguments.subject,
                bodyText    = len(arguments.bodyText) ? arguments.bodyText : stripTags(arguments.bodyHtml),
                bodyHtml    = arguments.bodyHtml,
                status      = success ? "sent" : "failed",
                mediaBuyId  = arguments.mediaBuyId,
                approvalId  = arguments.approvalId,
                billId      = arguments.billId,
                externalId  = msgId,
                errorMsg    = success ? "" : httpResult.fileContent
            );

            return { success: success, messageId: msgId, statusCode: httpResult.statusCode };

        } catch (any e) {
            logCommunication(
                type        = "email",
                direction   = "outbound",
                fromAddress = fromAddr,
                toAddress   = arguments.toEmail,
                subject     = arguments.subject,
                status      = "failed",
                errorMsg    = e.message
            );
            return { success: false, message: e.message };
        }
    }

    // ----------------------------------------------------------------
    // Send from template — merges {{variables}}
    // ----------------------------------------------------------------
    public struct function sendTemplate(
        required string  slug,
        required string  toEmail,
        required string  toName,
        required struct  vars,
        numeric  mediaBuyId = 0,
        numeric  approvalId = 0,
        numeric  billId     = 0
    ) {
        var tmpl = queryExecute(
            "SELECT * FROM email_templates WHERE slug=:slug AND is_active=1 LIMIT 1",
            { slug: { value: arguments.slug, cfsqltype: "cf_sql_varchar" } },
            { datasource: variables.dsn }
        );

        if (!tmpl.recordCount) {
            return { success: false, message: "Template '#arguments.slug#' not found." };
        }

        var subject  = mergeVars(tmpl.subject,   arguments.vars);
        var bodyHtml = mergeVars(tmpl.body_html,  arguments.vars);
        var bodyText = mergeVars(tmpl.body_text,  arguments.vars);

        return send(
            toEmail    = arguments.toEmail,
            toName     = arguments.toName,
            subject    = subject,
            bodyHtml   = bodyHtml,
            bodyText   = bodyText,
            mediaBuyId = arguments.mediaBuyId,
            approvalId = arguments.approvalId,
            billId     = arguments.billId
        );
    }

    // ----------------------------------------------------------------
    // Process inbound email from SendGrid inbound parse webhook
    // ----------------------------------------------------------------
    public struct function processInbound(required struct postData) {
        var from    = arguments.postData["from"]    ?: "";
        var to      = arguments.postData["to"]      ?: "";
        var subject = arguments.postData["subject"] ?: "";
        var text    = arguments.postData["text"]    ?: "";
        var html    = arguments.postData["html"]    ?: "";

        // Log the inbound message
        var commId = logCommunication(
            type        = "email",
            direction   = "inbound",
            fromAddress = from,
            toAddress   = to,
            subject     = subject,
            bodyText    = text,
            bodyHtml    = html,
            status      = "received",
            rawPayload  = serializeJSON(arguments.postData)
        );

        // Heuristic: detect bill/invoice keywords
        var isBill = (
            findNoCase("invoice", subject) ||
            findNoCase("invoice", text)    ||
            findNoCase("bill",    subject) ||
            findNoCase("remittance", subject)
        );

        if (isBill) {
            // Try to match vendor by from email
            var vendorQ = queryExecute(
                "SELECT id FROM vendors WHERE email=:email OR billing_email=:email LIMIT 1",
                { email: { value: listFirst(from, "<>"), cfsqltype: "cf_sql_varchar" } },
                { datasource: variables.dsn }
            );
            var vendorId = vendorQ.recordCount ? vendorQ.id : 0;

            queryExecute(
                "INSERT INTO bills (vendor_id, status, intake_method, raw_email_id, notes)
                 VALUES (:vendorId, 'queued', 'email', :commId, :notes)",
                {
                    vendorId: { value: vendorId, cfsqltype: "cf_sql_integer", null: !vendorId },
                    commId  : { value: commId,   cfsqltype: "cf_sql_integer" },
                    notes   : { value: "Auto-created from inbound email: #subject#", cfsqltype: "cf_sql_longvarchar" }
                },
                { datasource: variables.dsn }
            );
            var billId = queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id;

            queryExecute(
                "INSERT INTO bill_queue (bill_id, priority) VALUES (:billId, 'normal')",
                { billId: { value: billId, cfsqltype: "cf_sql_integer" } },
                { datasource: variables.dsn }
            );

            return { success: true, type: "bill", billId: billId };
        }

        return { success: true, type: "general", commId: commId };
    }

    // ----------------------------------------------------------------
    // Get communication history
    // ----------------------------------------------------------------
    public query function getHistory(
        numeric mediaBuyId = 0,
        numeric approvalId = 0,
        numeric billId     = 0,
        numeric page       = 1
    ) {
        var sql = "SELECT * FROM communications WHERE 1=1";
        var params = {};

        if (arguments.mediaBuyId) {
            sql &= " AND media_buy_id=:buyId";
            params["buyId"] = { value: arguments.mediaBuyId, cfsqltype: "cf_sql_integer" };
        }
        if (arguments.approvalId) {
            sql &= " AND approval_id=:approvalId";
            params["approvalId"] = { value: arguments.approvalId, cfsqltype: "cf_sql_integer" };
        }
        sql &= " ORDER BY created_at DESC";
        return queryExecute(sql, params, { datasource: variables.dsn });
    }

    // ----------------------------------------------------------------
    // Compose and send ad-hoc email (from UI)
    // ----------------------------------------------------------------
    public struct function sendAdHoc(required struct data) {
        return send(
            toEmail    = arguments.data.to_email,
            toName     = arguments.data.to_name    ?: "",
            subject    = arguments.data.subject,
            bodyHtml   = arguments.data.body_html  ?: arguments.data.body_text,
            bodyText   = arguments.data.body_text  ?: "",
            mediaBuyId = arguments.data.media_buy_id ?: 0
        );
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------
    private string function mergeVars(required string template, required struct vars) {
        var result = arguments.template;
        for (var key in arguments.vars) {
            result = replace(result, "{{#key#}}", arguments.vars[key], "all");
        }
        return result;
    }

    private string function stripTags(required string html) {
        return reReplace(arguments.html, "<[^>]*>", "", "all");
    }

    private numeric function logCommunication(
        required string type,
        required string direction,
        string fromAddress = "",
        string toAddress   = "",
        string subject     = "",
        string bodyText    = "",
        string bodyHtml    = "",
        string status      = "sent",
        numeric mediaBuyId = 0,
        numeric approvalId = 0,
        numeric billId     = 0,
        string  externalId = "",
        string  errorMsg   = "",
        string  rawPayload = ""
    ) {
        queryExecute(
            "INSERT INTO communications
                (type, direction, from_address, to_address, subject, body_text, body_html,
                 status, media_buy_id, approval_id, bill_id, sendgrid_message_id, error_message, raw_payload)
             VALUES
                (:type, :dir, :from, :to, :subject, :bodyText, :bodyHtml,
                 :status, :buyId, :approvalId, :billId, :msgId, :error, :raw)",
            {
                type      : { value: arguments.type,        cfsqltype: "cf_sql_varchar" },
                dir       : { value: arguments.direction,   cfsqltype: "cf_sql_varchar" },
                from      : { value: arguments.fromAddress, cfsqltype: "cf_sql_varchar" },
                to        : { value: arguments.toAddress,   cfsqltype: "cf_sql_varchar" },
                subject   : { value: arguments.subject,     cfsqltype: "cf_sql_varchar" },
                bodyText  : { value: arguments.bodyText,    cfsqltype: "cf_sql_longvarchar" },
                bodyHtml  : { value: arguments.bodyHtml,    cfsqltype: "cf_sql_longvarchar" },
                status    : { value: arguments.status,      cfsqltype: "cf_sql_varchar" },
                buyId     : { value: arguments.mediaBuyId,  cfsqltype: "cf_sql_integer", null: !arguments.mediaBuyId },
                approvalId: { value: arguments.approvalId,  cfsqltype: "cf_sql_integer", null: !arguments.approvalId },
                billId    : { value: arguments.billId,      cfsqltype: "cf_sql_integer", null: !arguments.billId },
                msgId     : { value: arguments.externalId,  cfsqltype: "cf_sql_varchar" },
                error     : { value: arguments.errorMsg,    cfsqltype: "cf_sql_longvarchar" },
                raw       : { value: arguments.rawPayload,  cfsqltype: "cf_sql_longvarchar" }
            },
            { datasource: variables.dsn }
        );
        return val(queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id);
    }

}
