component extends="BaseService" {

    public any function init() {
        super.init();
        return this;
    }

    // ----------------------------------------------------------------
    // Send SMS via Twilio REST API
    // ----------------------------------------------------------------
    public struct function send(
        required string toNumber,
        required string body,
        numeric  mediaBuyId = 0,
        numeric  approvalId = 0
    ) {
        var accountSid = getSetting("twilio_account_sid", "");
        var authToken  = getSetting("twilio_auth_token",  "");
        var fromNumber = getSetting("twilio_from_number", "");

        if (!len(accountSid) || !len(authToken) || !len(fromNumber)) {
            return { success: false, message: "Twilio not configured." };
        }

        var twilioSid = "";

        try {
            var httpResult = "";
            cfhttp(
                method   = "POST",
                url      = "https://api.twilio.com/2010-04-01/Accounts/#accountSid#/Messages.json",
                username = accountSid,
                password = authToken,
                result   = "httpResult"
            ) {
                cfhttpparam(type="formfield", name="From", value=fromNumber);
                cfhttpparam(type="formfield", name="To",   value=arguments.toNumber);
                cfhttpparam(type="formfield", name="Body", value=arguments.body);
            }

            var resp    = deserializeJSON(httpResult.fileContent);
            twilioSid   = resp.sid ?: "";
            var success = (httpResult.statusCode contains "201" || httpResult.statusCode contains "200");

            logSMS(
                direction   = "outbound",
                toAddress   = arguments.toNumber,
                fromAddress = fromNumber,
                body        = arguments.body,
                status      = success ? "sent" : "failed",
                mediaBuyId  = arguments.mediaBuyId,
                approvalId  = arguments.approvalId,
                twilioSid   = twilioSid,
                errorMsg    = success ? "" : (resp.message ?: "")
            );

            return { success: success, sid: twilioSid };

        } catch (any e) {
            return { success: false, message: e.message };
        }
    }

    // ----------------------------------------------------------------
    // Send approval notification SMS
    // ----------------------------------------------------------------
    public struct function sendApprovalNotification(
        required string toNumber,
        required string clientName,
        required string buyTitle,
        required string approvalLink
    ) {
        var body = "Hi #arguments.clientName#, a media buy needs your approval: ""#arguments.buyTitle#"". Review here: #arguments.approvalLink#";
        // Twilio SMS max 1600 chars; truncate link message if needed
        if (len(body) > 160) {
            body = "Media buy approval needed: #arguments.buyTitle# — #arguments.approvalLink#";
        }
        return send(toNumber = arguments.toNumber, body = body);
    }

    // ----------------------------------------------------------------
    // Process inbound SMS webhook from Twilio
    // ----------------------------------------------------------------
    public void function processInbound(required struct postData) {
        var from = arguments.postData["From"] ?: "";
        var body = arguments.postData["Body"] ?: "";
        var sid  = arguments.postData["SmsSid"] ?: "";

        logSMS(
            direction   = "inbound",
            fromAddress = from,
            toAddress   = arguments.postData["To"] ?: "",
            body        = body,
            status      = "received",
            twilioSid   = sid,
            rawPayload  = serializeJSON(arguments.postData)
        );
    }

    // ----------------------------------------------------------------
    // Private: log SMS to communications table
    // ----------------------------------------------------------------
    private void function logSMS(
        required string direction,
        string fromAddress = "",
        string toAddress   = "",
        string body        = "",
        string status      = "sent",
        numeric mediaBuyId = 0,
        numeric approvalId = 0,
        string  twilioSid  = "",
        string  errorMsg   = "",
        string  rawPayload = ""
    ) {
        queryExecute(
            "INSERT INTO communications
                (type, direction, from_address, to_address, body_text, status,
                 media_buy_id, approval_id, twilio_sid, error_message, raw_payload)
             VALUES
                ('sms', :dir, :from, :to, :body, :status,
                 :buyId, :approvalId, :twilioSid, :error, :raw)",
            {
                dir       : { value: arguments.direction,   cfsqltype: "cf_sql_varchar" },
                from      : { value: arguments.fromAddress, cfsqltype: "cf_sql_varchar" },
                to        : { value: arguments.toAddress,   cfsqltype: "cf_sql_varchar" },
                body      : { value: arguments.body,        cfsqltype: "cf_sql_longvarchar" },
                status    : { value: arguments.status,      cfsqltype: "cf_sql_varchar" },
                buyId     : { value: arguments.mediaBuyId,  cfsqltype: "cf_sql_integer", null: !arguments.mediaBuyId },
                approvalId: { value: arguments.approvalId,  cfsqltype: "cf_sql_integer", null: !arguments.approvalId },
                twilioSid : { value: arguments.twilioSid,   cfsqltype: "cf_sql_varchar" },
                error     : { value: arguments.errorMsg,    cfsqltype: "cf_sql_longvarchar" },
                raw       : { value: arguments.rawPayload,  cfsqltype: "cf_sql_longvarchar" }
            },
            { datasource: variables.dsn }
        );
    }

}
