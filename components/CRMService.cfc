component extends="BaseService" {

    public any function init() {
        super.init();
        return this;
    }

    // ================================================================
    // CLIENTS
    // ================================================================

    public query function getClients(string search = "") {
        var sql = "SELECT c.*, u.email AS portal_email
                     FROM clients c
                LEFT JOIN users u ON u.id = c.user_id
                    WHERE c.is_active=1";
        var params = {};
        if (len(trim(arguments.search))) {
            sql &= " AND (c.company_name LIKE :s OR c.contact_name LIKE :s OR c.email LIKE :s)";
            params["s"] = { value: "%#arguments.search#%", cfsqltype: "cf_sql_varchar" };
        }
        sql &= " ORDER BY c.company_name";
        return queryExecute(sql, params, { datasource: variables.dsn });
    }

    public struct function getClient(required numeric id) {
        var q = queryExecute(
            "SELECT c.*, u.email AS portal_email FROM clients c
        LEFT JOIN users u ON u.id = c.user_id WHERE c.id=:id",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );
        if (!q.recordCount) return { found: false };

        var buys = queryExecute(
            "SELECT id, title, status, created_at FROM media_buys WHERE client_id=:id ORDER BY created_at DESC LIMIT 10",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        return { found: true, client: q, buys: buys };
    }

    public struct function saveClient(required struct data) {
        var d = arguments.data;
        if (!structKeyExists(d, "id") || !d.id) {
            queryExecute(
                "INSERT INTO clients (company_name, contact_name, email, phone, address, notes)
                 VALUES (:company, :contact, :email, :phone, :address, :notes)",
                {
                    company : { value: d.company_name,    cfsqltype: "cf_sql_varchar" },
                    contact : { value: d.contact_name,    cfsqltype: "cf_sql_varchar" },
                    email   : { value: d.email,           cfsqltype: "cf_sql_varchar" },
                    phone   : { value: d.phone   ?: "",   cfsqltype: "cf_sql_varchar" },
                    address : { value: d.address ?: "",   cfsqltype: "cf_sql_longvarchar" },
                    notes   : { value: d.notes   ?: "",   cfsqltype: "cf_sql_longvarchar" }
                },
                { datasource: variables.dsn }
            );
            var newId = val(queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id);
            auditLog("create_client", "client", newId, d.company_name);
            return { success: true, id: newId };
        } else {
            queryExecute(
                "UPDATE clients SET company_name=:company, contact_name=:contact, email=:email,
                  phone=:phone, address=:address, notes=:notes WHERE id=:id",
                {
                    company : { value: d.company_name, cfsqltype: "cf_sql_varchar" },
                    contact : { value: d.contact_name, cfsqltype: "cf_sql_varchar" },
                    email   : { value: d.email,        cfsqltype: "cf_sql_varchar" },
                    phone   : { value: d.phone   ?: "", cfsqltype: "cf_sql_varchar" },
                    address : { value: d.address ?: "", cfsqltype: "cf_sql_longvarchar" },
                    notes   : { value: d.notes   ?: "", cfsqltype: "cf_sql_longvarchar" },
                    id      : { value: d.id,           cfsqltype: "cf_sql_integer" }
                },
                { datasource: variables.dsn }
            );
            auditLog("update_client", "client", d.id, d.company_name);
            return { success: true, id: d.id };
        }
    }

    // ================================================================
    // VENDORS
    // ================================================================

    public query function getVendors(string search = "") {
        var sql = "SELECT v.*, u.email AS portal_email
                     FROM vendors v
                LEFT JOIN users u ON u.id = v.user_id
                    WHERE v.is_active=1";
        var params = {};
        if (len(trim(arguments.search))) {
            sql &= " AND (v.company_name LIKE :s OR v.contact_name LIKE :s OR v.email LIKE :s)";
            params["s"] = { value: "%#arguments.search#%", cfsqltype: "cf_sql_varchar" };
        }
        sql &= " ORDER BY v.company_name";
        return queryExecute(sql, params, { datasource: variables.dsn });
    }

    public struct function getVendor(required numeric id) {
        var q = queryExecute(
            "SELECT v.*, u.email AS portal_email FROM vendors v
        LEFT JOIN users u ON u.id = v.user_id WHERE v.id=:id",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );
        if (!q.recordCount) return { found: false };

        var buys = queryExecute(
            "SELECT mb.id, mb.title, mb.status, mb.original_cost, mb.created_at
               FROM media_buys mb WHERE mb.vendor_id=:id ORDER BY mb.created_at DESC LIMIT 10",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        return { found: true, vendor: q, buys: buys };
    }

    public struct function saveVendor(required struct data) {
        var d = arguments.data;
        if (!structKeyExists(d, "id") || !d.id) {
            queryExecute(
                "INSERT INTO vendors (company_name, contact_name, email, phone, billing_email, media_types, address, notes)
                 VALUES (:company, :contact, :email, :phone, :billing, :types, :address, :notes)",
                {
                    company : { value: d.company_name,      cfsqltype: "cf_sql_varchar" },
                    contact : { value: d.contact_name,      cfsqltype: "cf_sql_varchar" },
                    email   : { value: d.email,             cfsqltype: "cf_sql_varchar" },
                    phone   : { value: d.phone      ?: "",  cfsqltype: "cf_sql_varchar" },
                    billing : { value: d.billing_email ?: "", cfsqltype: "cf_sql_varchar" },
                    types   : { value: d.media_types ?: "",  cfsqltype: "cf_sql_varchar" },
                    address : { value: d.address    ?: "",   cfsqltype: "cf_sql_longvarchar" },
                    notes   : { value: d.notes      ?: "",   cfsqltype: "cf_sql_longvarchar" }
                },
                { datasource: variables.dsn }
            );
            var newId = val(queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id);
            auditLog("create_vendor", "vendor", newId, d.company_name);
            return { success: true, id: newId };
        } else {
            queryExecute(
                "UPDATE vendors SET company_name=:company, contact_name=:contact, email=:email,
                  phone=:phone, billing_email=:billing, media_types=:types, address=:address, notes=:notes
                 WHERE id=:id",
                {
                    company : { value: d.company_name,      cfsqltype: "cf_sql_varchar" },
                    contact : { value: d.contact_name,      cfsqltype: "cf_sql_varchar" },
                    email   : { value: d.email,             cfsqltype: "cf_sql_varchar" },
                    phone   : { value: d.phone      ?: "",  cfsqltype: "cf_sql_varchar" },
                    billing : { value: d.billing_email ?: "", cfsqltype: "cf_sql_varchar" },
                    types   : { value: d.media_types ?: "",  cfsqltype: "cf_sql_varchar" },
                    address : { value: d.address    ?: "",   cfsqltype: "cf_sql_longvarchar" },
                    notes   : { value: d.notes      ?: "",   cfsqltype: "cf_sql_longvarchar" },
                    id      : { value: d.id,                cfsqltype: "cf_sql_integer" }
                },
                { datasource: variables.dsn }
            );
            auditLog("update_vendor", "vendor", d.id, d.company_name);
            return { success: true, id: d.id };
        }
    }

    // ================================================================
    // TEMPLATES (CMS)
    // ================================================================

    public query function getTemplates(string category = "") {
        var sql = "SELECT * FROM email_templates WHERE 1=1";
        var params = {};
        if (len(arguments.category)) {
            sql &= " AND category=:cat";
            params["cat"] = { value: arguments.category, cfsqltype: "cf_sql_varchar" };
        }
        sql &= " ORDER BY category, name";
        return queryExecute(sql, params, { datasource: variables.dsn });
    }

    public query function getTemplate(required numeric id) {
        return queryExecute(
            "SELECT * FROM email_templates WHERE id=:id",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );
    }

    public struct function saveTemplate(required struct data) {
        var d = arguments.data;
        if (!structKeyExists(d, "id") || !d.id) {
            queryExecute(
                "INSERT INTO email_templates (name, slug, category, channel, subject, body_html, body_text, sms_body, variables, is_active)
                 VALUES (:name, :slug, :cat, :channel, :subject, :html, :text, :sms, :vars, :active)",
                {
                    name   : { value: d.name,           cfsqltype: "cf_sql_varchar" },
                    slug   : { value: d.slug,           cfsqltype: "cf_sql_varchar" },
                    cat    : { value: d.category,       cfsqltype: "cf_sql_varchar" },
                    channel: { value: d.channel ?: "email", cfsqltype: "cf_sql_varchar" },
                    subject: { value: d.subject ?: "",  cfsqltype: "cf_sql_varchar" },
                    html   : { value: d.body_html ?: "", cfsqltype: "cf_sql_longvarchar" },
                    text   : { value: d.body_text ?: "", cfsqltype: "cf_sql_longvarchar" },
                    sms    : { value: d.sms_body ?: "",  cfsqltype: "cf_sql_longvarchar" },
                    vars   : { value: d.variables ?: "", cfsqltype: "cf_sql_longvarchar" },
                    active : { value: d.is_active ?: 1,  cfsqltype: "cf_sql_tinyint" }
                },
                { datasource: variables.dsn }
            );
            var newId = val(queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id);
            return { success: true, id: newId };
        } else {
            queryExecute(
                "UPDATE email_templates SET name=:name, category=:cat, channel=:channel,
                  subject=:subject, body_html=:html, body_text=:text, sms_body=:sms,
                  variables=:vars, is_active=:active WHERE id=:id",
                {
                    name   : { value: d.name,           cfsqltype: "cf_sql_varchar" },
                    cat    : { value: d.category,       cfsqltype: "cf_sql_varchar" },
                    channel: { value: d.channel ?: "email", cfsqltype: "cf_sql_varchar" },
                    subject: { value: d.subject ?: "",  cfsqltype: "cf_sql_varchar" },
                    html   : { value: d.body_html ?: "", cfsqltype: "cf_sql_longvarchar" },
                    text   : { value: d.body_text ?: "", cfsqltype: "cf_sql_longvarchar" },
                    sms    : { value: d.sms_body ?: "",  cfsqltype: "cf_sql_longvarchar" },
                    vars   : { value: d.variables ?: "", cfsqltype: "cf_sql_longvarchar" },
                    active : { value: d.is_active ?: 1,  cfsqltype: "cf_sql_tinyint" },
                    id     : { value: d.id,             cfsqltype: "cf_sql_integer" }
                },
                { datasource: variables.dsn }
            );
            return { success: true, id: d.id };
        }
    }

    // ================================================================
    // WORKFLOW SETTINGS (CMS)
    // ================================================================

    public query function getWorkflowSettings(string group = "") {
        var sql = "SELECT * FROM workflow_settings WHERE 1=1";
        var params = {};
        if (len(arguments.group)) {
            sql &= " AND setting_group=:grp";
            params["grp"] = { value: arguments.group, cfsqltype: "cf_sql_varchar" };
        }
        sql &= " ORDER BY setting_group, label";
        return queryExecute(sql, params, { datasource: variables.dsn });
    }

    public void function saveSetting(required string key, required string value) {
        queryExecute(
            "UPDATE workflow_settings SET setting_value=:val, updated_by=:uid WHERE setting_key=:key",
            {
                val : { value: arguments.value,          cfsqltype: "cf_sql_longvarchar" },
                uid : { value: session.user.id ?: 0,     cfsqltype: "cf_sql_integer" },
                key : { value: arguments.key,            cfsqltype: "cf_sql_varchar" }
            },
            { datasource: variables.dsn }
        );
        // Refresh application settings cache
        if (structKeyExists(application, "settings")) {
            application.settings[arguments.key] = arguments.value;
        }
    }

}
