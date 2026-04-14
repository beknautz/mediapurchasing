component accessors="true" {

    property name="dsn" type="string" default="mediapurchasing";

    public any function init() {
        variables.dsn = application.datasource ?: "mediapurchasing";
        return this;
    }

    // Generic paginated query helper
    public struct function paginate(
        required string sql,
        required struct params,
        numeric page     = 1,
        numeric pageSize = 25
    ) {
        var offset    = (arguments.page - 1) * arguments.pageSize;
        var countSQL  = reReplaceNoCase(arguments.sql, "SELECT .+ FROM", "SELECT COUNT(*) AS total FROM", "one");
        // strip ORDER BY for count
        countSQL = reReplaceNoCase(countSQL, "ORDER BY .+$", "", "one");

        var countQ  = queryExecute(countSQL, arguments.params, { datasource: variables.dsn });
        var total   = countQ.total;
        var pages   = ceiling(total / arguments.pageSize);

        var dataSQL = arguments.sql & " LIMIT :limit OFFSET :offset";
        var p       = duplicate(arguments.params);
        p["limit"]  = { value: arguments.pageSize, cfsqltype: "cf_sql_integer" };
        p["offset"] = { value: offset,              cfsqltype: "cf_sql_integer" };

        var data = queryExecute(dataSQL, p, { datasource: variables.dsn });

        return {
            data     : data,
            total    : total,
            page     : arguments.page,
            pageSize : arguments.pageSize,
            pages    : pages
        };
    }

    // Log an audit event
    public void function auditLog(
        required string action,
        string  entityType = "",
        numeric entityId   = 0,
        string  details    = ""
    ) {
        try {
            var userId = isStruct(session.user) && structKeyExists(session.user, "id") ? session.user.id : javaCast("null", "");
            queryExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
                 VALUES (:userId, :action, :entityType, :entityId, :details, :ip)",
                {
                    userId    : { value: userId,              cfsqltype: "cf_sql_integer", null: isNull(userId) },
                    action    : { value: arguments.action,    cfsqltype: "cf_sql_varchar" },
                    entityType: { value: arguments.entityType,cfsqltype: "cf_sql_varchar" },
                    entityId  : { value: arguments.entityId,  cfsqltype: "cf_sql_integer", null: !arguments.entityId },
                    details   : { value: arguments.details,   cfsqltype: "cf_sql_longvarchar" },
                    ip        : { value: cgi.remote_addr,     cfsqltype: "cf_sql_varchar" }
                },
                { datasource: variables.dsn }
            );
        } catch (any e) {
            // non-fatal — don't break the request
        }
    }

    // Safe HTML encode shortcut
    public string function h(required string val) {
        return encodeForHTML(arguments.val);
    }

    // Get a setting value from application scope
    public string function getSetting(required string key, string defaultVal = "") {
        if (structKeyExists(application, "settings") && structKeyExists(application.settings, arguments.key)) {
            return application.settings[arguments.key];
        }
        return arguments.defaultVal;
    }

}
