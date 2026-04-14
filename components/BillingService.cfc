component extends="BaseService" {

    public any function init() {
        super.init();
        return this;
    }

    // ----------------------------------------------------------------
    // Get bill queue
    // ----------------------------------------------------------------
    public query function getQueue(string status = "", numeric page = 1) {
        var sql = "SELECT bq.*, b.invoice_number, b.invoice_date, b.due_date, b.amount,
                          b.status AS bill_status, b.intake_method,
                          v.company_name AS vendor_name,
                          mb.title AS buy_title,
                          u.name  AS assigned_name
                     FROM bill_queue bq
                     JOIN bills   b  ON b.id  = bq.bill_id
                     JOIN vendors v  ON v.id  = b.vendor_id
                LEFT JOIN media_buys mb ON mb.id = b.media_buy_id
                LEFT JOIN users      u  ON u.id  = bq.assigned_to
                    WHERE 1=1";
        var params = {};

        if (len(arguments.status)) {
            sql &= " AND bq.status = :status";
            params["status"] = { value: arguments.status, cfsqltype: "cf_sql_varchar" };
        }

        // Urgency sort: urgent first, then by due date
        sql &= " ORDER BY FIELD(bq.priority,'urgent','high','normal','low'), bq.due_at ASC, bq.queued_at ASC";

        return queryExecute(sql, params, { datasource: variables.dsn });
    }

    // ----------------------------------------------------------------
    // Get single bill with details
    // ----------------------------------------------------------------
    public struct function getBill(required numeric id) {
        var q = queryExecute(
            "SELECT b.*, v.company_name AS vendor_name, v.email AS vendor_email,
                    mb.title AS buy_title, u.name AS assigned_name,
                    bq.priority, bq.status AS queue_status, bq.processing_notes, bq.id AS queue_id
               FROM bills b
               JOIN vendors v ON v.id = b.vendor_id
          LEFT JOIN media_buys mb ON mb.id = b.media_buy_id
          LEFT JOIN bill_queue bq ON bq.bill_id = b.id
          LEFT JOIN users      u  ON u.id = bq.assigned_to
              WHERE b.id = :id",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );
        if (!q.recordCount) return { found: false };
        return { found: true, bill: q };
    }

    // ----------------------------------------------------------------
    // Create bill manually
    // ----------------------------------------------------------------
    public struct function createBill(required struct data) {
        var d = arguments.data;

        queryExecute(
            "INSERT INTO bills (vendor_id, media_buy_id, invoice_number, invoice_date, due_date,
                                amount, status, intake_method, notes)
             VALUES (:vendorId, :buyId, :invNum, :invDate, :dueDate, :amount, 'queued', :intake, :notes)",
            {
                vendorId: { value: d.vendor_id,       cfsqltype: "cf_sql_integer" },
                buyId   : { value: d.media_buy_id ?: 0, cfsqltype: "cf_sql_integer", null: !(d.media_buy_id ?: 0) },
                invNum  : { value: d.invoice_number ?: "", cfsqltype: "cf_sql_varchar" },
                invDate : { value: d.invoice_date,    cfsqltype: "cf_sql_date",    null: !len(d.invoice_date ?: "") },
                dueDate : { value: d.due_date ?: "",  cfsqltype: "cf_sql_date",    null: !len(d.due_date    ?: "") },
                amount  : { value: d.amount,          cfsqltype: "cf_sql_decimal" },
                intake  : { value: d.intake_method ?: "manual_upload", cfsqltype: "cf_sql_varchar" },
                notes   : { value: d.notes ?: "",     cfsqltype: "cf_sql_longvarchar" }
            },
            { datasource: variables.dsn }
        );
        var billId = val(queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id);

        // Add to queue
        queryExecute(
            "INSERT INTO bill_queue (bill_id, priority, due_at)
             VALUES (:billId, :priority, :dueAt)",
            {
                billId  : { value: billId,                  cfsqltype: "cf_sql_integer" },
                priority: { value: d.priority ?: "normal",  cfsqltype: "cf_sql_varchar" },
                dueAt   : { value: d.due_date ?: "",        cfsqltype: "cf_sql_date",    null: !len(d.due_date ?: "") }
            },
            { datasource: variables.dsn }
        );

        auditLog("create_bill", "bill", billId, "Invoice #d.invoice_number# from vendor #d.vendor_id#");
        return { success: true, id: billId };
    }

    // ----------------------------------------------------------------
    // Update bill status
    // ----------------------------------------------------------------
    public void function updateBillStatus(
        required numeric billId,
        required string  status,
        string           notes = ""
    ) {
        queryExecute(
            "UPDATE bills SET status=:status WHERE id=:id",
            {
                status: { value: arguments.status, cfsqltype: "cf_sql_varchar" },
                id    : { value: arguments.billId, cfsqltype: "cf_sql_integer" }
            },
            { datasource: variables.dsn }
        );
        if (len(arguments.notes)) {
            queryExecute(
                "UPDATE bill_queue SET processing_notes=:notes WHERE bill_id=:id",
                {
                    notes: { value: arguments.notes,    cfsqltype: "cf_sql_longvarchar" },
                    id   : { value: arguments.billId,   cfsqltype: "cf_sql_integer" }
                },
                { datasource: variables.dsn }
            );
        }
        if (arguments.status == "paid") {
            queryExecute(
                "UPDATE bill_queue SET status='completed', completed_at=NOW() WHERE bill_id=:id",
                { id: { value: arguments.billId, cfsqltype: "cf_sql_integer" } },
                { datasource: variables.dsn }
            );
        }
        auditLog("bill_status_change", "bill", arguments.billId, "Status -> #arguments.status#");
    }

    // ----------------------------------------------------------------
    // Assign bill to user
    // ----------------------------------------------------------------
    public void function assignBill(required numeric billId, required numeric userId) {
        queryExecute(
            "UPDATE bill_queue SET assigned_to=:userId, status='in_progress' WHERE bill_id=:billId",
            {
                userId : { value: arguments.userId,  cfsqltype: "cf_sql_integer" },
                billId : { value: arguments.billId,  cfsqltype: "cf_sql_integer" }
            },
            { datasource: variables.dsn }
        );
    }

    // ----------------------------------------------------------------
    // Dashboard: queue counts by status
    // ----------------------------------------------------------------
    public struct function getQueueCounts() {
        var q = queryExecute(
            "SELECT
                SUM(bq.status='waiting')     AS waiting,
                SUM(bq.status='in_progress') AS in_progress,
                SUM(bq.status='on_hold')     AS on_hold,
                SUM(bq.priority='urgent')    AS urgent
             FROM bill_queue bq
             JOIN bills b ON b.id=bq.bill_id
            WHERE b.status NOT IN ('paid','rejected')",
            {},
            { datasource: variables.dsn }
        );
        return {
            waiting    : val(q.waiting),
            in_progress: val(q.in_progress),
            on_hold    : val(q.on_hold),
            urgent     : val(q.urgent)
        };
    }

    // ----------------------------------------------------------------
    // Get vendors list (for dropdown)
    // ----------------------------------------------------------------
    public query function getVendors() {
        return queryExecute(
            "SELECT id, company_name FROM vendors WHERE is_active=1 ORDER BY company_name",
            {},
            { datasource: variables.dsn }
        );
    }

}
