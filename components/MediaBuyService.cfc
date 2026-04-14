component extends="BaseService" {

    public any function init() {
        super.init();
        return this;
    }

    // ----------------------------------------------------------------
    // List media buys with filters
    // ----------------------------------------------------------------
    public query function getMediaBuys(
        numeric clientId = 0,
        numeric vendorId = 0,
        numeric buyerId  = 0,
        string  status   = "",
        numeric page     = 1,
        numeric pageSize = 25
    ) {
        var sql = "SELECT mb.*, c.company_name AS client_name, v.company_name AS vendor_name,
                          u.name AS buyer_name
                     FROM media_buys mb
                     JOIN clients c  ON c.id = mb.client_id
                     JOIN vendors v  ON v.id = mb.vendor_id
                     JOIN users   u  ON u.id = mb.buyer_id
                    WHERE 1=1";
        var params = {};

        if (arguments.clientId) {
            sql &= " AND mb.client_id = :clientId";
            params["clientId"] = { value: arguments.clientId, cfsqltype: "cf_sql_integer" };
        }
        if (arguments.vendorId) {
            sql &= " AND mb.vendor_id = :vendorId";
            params["vendorId"] = { value: arguments.vendorId, cfsqltype: "cf_sql_integer" };
        }
        if (arguments.buyerId) {
            sql &= " AND mb.buyer_id = :buyerId";
            params["buyerId"] = { value: arguments.buyerId, cfsqltype: "cf_sql_integer" };
        }
        if (len(trim(arguments.status))) {
            sql &= " AND mb.status = :status";
            params["status"] = { value: arguments.status, cfsqltype: "cf_sql_varchar" };
        }
        sql &= " ORDER BY mb.updated_at DESC";

        return paginate(sql, params, arguments.page, arguments.pageSize);
    }

    // ----------------------------------------------------------------
    // Get single media buy with line items and history
    // ----------------------------------------------------------------
    public struct function getMediaBuy(required numeric id) {
        var q = queryExecute(
            "SELECT mb.*, c.company_name AS client_name, c.email AS client_email,
                    v.company_name AS vendor_name, v.email AS vendor_email,
                    u.name AS buyer_name, u.email AS buyer_email
               FROM media_buys mb
               JOIN clients c  ON c.id = mb.client_id
               JOIN vendors v  ON v.id = mb.vendor_id
               JOIN users   u  ON u.id = mb.buyer_id
              WHERE mb.id = :id",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        if (!q.recordCount) return {};

        var items = queryExecute(
            "SELECT * FROM media_buy_items WHERE media_buy_id=:id ORDER BY sort_order",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        var negotiations = queryExecute(
            "SELECT * FROM negotiations WHERE media_buy_id=:id ORDER BY round",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        var approvals = queryExecute(
            "SELECT a.*, cl.company_name AS client_name
               FROM approvals a
               JOIN clients cl ON cl.id = a.client_id
              WHERE a.media_buy_id = :id
              ORDER BY a.requested_at DESC",
            { id: { value: arguments.id, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );

        return {
            buy         : q,
            items       : items,
            negotiations: negotiations,
            approvals   : approvals
        };
    }

    // ----------------------------------------------------------------
    // Save media buy (create or update)
    // ----------------------------------------------------------------
    public struct function saveMediaBuy(required struct data) {
        var d = arguments.data;

        if (!structKeyExists(d, "id") || !d.id) {
            queryExecute(
                "INSERT INTO media_buys
                    (title, client_id, vendor_id, buyer_id, status, media_type,
                     flight_start, flight_end, market, original_cost, description, internal_notes)
                 VALUES
                    (:title, :clientId, :vendorId, :buyerId, 'draft', :mediaType,
                     :flightStart, :flightEnd, :market, :originalCost, :description, :notes)",
                {
                    title       : { value: d.title,         cfsqltype: "cf_sql_varchar"  },
                    clientId    : { value: d.client_id,     cfsqltype: "cf_sql_integer"  },
                    vendorId    : { value: d.vendor_id,     cfsqltype: "cf_sql_integer"  },
                    buyerId     : { value: d.buyer_id,      cfsqltype: "cf_sql_integer"  },
                    mediaType   : { value: d.media_type,    cfsqltype: "cf_sql_varchar"  },
                    flightStart : { value: d.flight_start,  cfsqltype: "cf_sql_date",    null: !len(d.flight_start  ?: "") },
                    flightEnd   : { value: d.flight_end,    cfsqltype: "cf_sql_date",    null: !len(d.flight_end    ?: "") },
                    market      : { value: d.market ?: "",  cfsqltype: "cf_sql_varchar"  },
                    originalCost: { value: d.original_cost, cfsqltype: "cf_sql_decimal"  },
                    description : { value: d.description ?: "", cfsqltype: "cf_sql_longvarchar" },
                    notes       : { value: d.internal_notes ?: "", cfsqltype: "cf_sql_longvarchar" }
                },
                { datasource: variables.dsn }
            );
            var newId = queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id;
            saveLineItems(newId, d.items ?: []);
            auditLog("create_media_buy", "media_buy", newId, "Created: #d.title#");
            return { success: true, id: newId };

        } else {
            queryExecute(
                "UPDATE media_buys SET
                    title=:title, client_id=:clientId, vendor_id=:vendorId,
                    media_type=:mediaType, flight_start=:flightStart, flight_end=:flightEnd,
                    market=:market, original_cost=:originalCost,
                    description=:description, internal_notes=:notes
                 WHERE id=:id",
                {
                    title       : { value: d.title,         cfsqltype: "cf_sql_varchar"  },
                    clientId    : { value: d.client_id,     cfsqltype: "cf_sql_integer"  },
                    vendorId    : { value: d.vendor_id,     cfsqltype: "cf_sql_integer"  },
                    mediaType   : { value: d.media_type,    cfsqltype: "cf_sql_varchar"  },
                    flightStart : { value: d.flight_start,  cfsqltype: "cf_sql_date",    null: !len(d.flight_start  ?: "") },
                    flightEnd   : { value: d.flight_end,    cfsqltype: "cf_sql_date",    null: !len(d.flight_end    ?: "") },
                    market      : { value: d.market ?: "",  cfsqltype: "cf_sql_varchar"  },
                    originalCost: { value: d.original_cost, cfsqltype: "cf_sql_decimal"  },
                    description : { value: d.description ?: "", cfsqltype: "cf_sql_longvarchar" },
                    notes       : { value: d.internal_notes ?: "", cfsqltype: "cf_sql_longvarchar" },
                    id          : { value: d.id,            cfsqltype: "cf_sql_integer"  }
                },
                { datasource: variables.dsn }
            );
            saveLineItems(d.id, d.items ?: []);
            auditLog("update_media_buy", "media_buy", d.id, "Updated: #d.title#");
            return { success: true, id: d.id };
        }
    }

    // ----------------------------------------------------------------
    // Update status
    // ----------------------------------------------------------------
    public void function updateStatus(required numeric id, required string status, string notes="") {
        queryExecute(
            "UPDATE media_buys SET status=:status WHERE id=:id",
            {
                status: { value: arguments.status, cfsqltype: "cf_sql_varchar" },
                id    : { value: arguments.id,     cfsqltype: "cf_sql_integer" }
            },
            { datasource: variables.dsn }
        );
        if (len(arguments.notes)) {
            queryExecute(
                "UPDATE media_buys SET evaluation_notes=:notes WHERE id=:id",
                {
                    notes: { value: arguments.notes, cfsqltype: "cf_sql_longvarchar" },
                    id   : { value: arguments.id,    cfsqltype: "cf_sql_integer" }
                },
                { datasource: variables.dsn }
            );
        }
        auditLog("status_change", "media_buy", arguments.id, "Status -> #arguments.status#");
    }

    // ----------------------------------------------------------------
    // Save negotiation round
    // ----------------------------------------------------------------
    public numeric function saveNegotiation(required struct data) {
        var d = arguments.data;
        queryExecute(
            "INSERT INTO negotiations (media_buy_id, round, buyer_offer, buyer_notes, status)
             VALUES (:buyId, :round, :offer, :notes, 'pending')",
            {
                buyId : { value: d.media_buy_id, cfsqltype: "cf_sql_integer" },
                round : { value: d.round,        cfsqltype: "cf_sql_integer" },
                offer : { value: d.buyer_offer,  cfsqltype: "cf_sql_decimal" },
                notes : { value: d.buyer_notes ?: "", cfsqltype: "cf_sql_longvarchar" }
            },
            { datasource: variables.dsn }
        );
        updateStatus(d.media_buy_id, "negotiating");
        var newId = queryExecute("SELECT LAST_INSERT_ID() AS id", {}, { datasource: variables.dsn }).id;
        return newId;
    }

    // ----------------------------------------------------------------
    // Record vendor counter-offer
    // ----------------------------------------------------------------
    public void function recordVendorCounter(required numeric negotiationId, required numeric counter, string notes="") {
        queryExecute(
            "UPDATE negotiations SET vendor_counter=:counter, vendor_notes=:notes, status='countered'
              WHERE id=:id",
            {
                counter: { value: arguments.counter,       cfsqltype: "cf_sql_decimal" },
                notes  : { value: arguments.notes,         cfsqltype: "cf_sql_longvarchar" },
                id     : { value: arguments.negotiationId, cfsqltype: "cf_sql_integer" }
            },
            { datasource: variables.dsn }
        );
    }

    // ----------------------------------------------------------------
    // Finalize negotiation — accept an offer
    // ----------------------------------------------------------------
    public void function finalizeNegotiation(required numeric mediaBuyId, required numeric agreedCost) {
        queryExecute(
            "UPDATE media_buys SET negotiated_cost=:cost, status='finalized' WHERE id=:id",
            {
                cost: { value: arguments.agreedCost, cfsqltype: "cf_sql_decimal" },
                id  : { value: arguments.mediaBuyId, cfsqltype: "cf_sql_integer" }
            },
            { datasource: variables.dsn }
        );
        auditLog("finalize_negotiation", "media_buy", arguments.mediaBuyId, "Agreed cost: #arguments.agreedCost#");
    }

    // ----------------------------------------------------------------
    // Dashboard counts
    // ----------------------------------------------------------------
    public struct function getDashboardCounts() {
        var q = queryExecute(
            "SELECT
                SUM(status='draft')                      AS drafts,
                SUM(status='pending_client_approval')    AS pending_approval,
                SUM(status='negotiating')                AS negotiating,
                SUM(status='client_approved')            AS approved,
                SUM(status='finalized')                  AS finalized
             FROM media_buys",
            {},
            { datasource: variables.dsn }
        );
        return {
            drafts          : val(q.drafts),
            pending_approval: val(q.pending_approval),
            negotiating     : val(q.negotiating),
            approved        : val(q.approved),
            finalized       : val(q.finalized)
        };
    }

    // ----------------------------------------------------------------
    // Private: save line items
    // ----------------------------------------------------------------
    private void function saveLineItems(required numeric buyId, required array items) {
        queryExecute(
            "DELETE FROM media_buy_items WHERE media_buy_id=:id",
            { id: { value: arguments.buyId, cfsqltype: "cf_sql_integer" } },
            { datasource: variables.dsn }
        );
        var sort = 1;
        for (var item in arguments.items) {
            queryExecute(
                "INSERT INTO media_buy_items (media_buy_id, description, placement, spots, unit_cost, total_cost, sort_order)
                 VALUES (:buyId, :desc, :placement, :spots, :unitCost, :totalCost, :sort)",
                {
                    buyId    : { value: arguments.buyId,          cfsqltype: "cf_sql_integer" },
                    desc     : { value: item.description ?: "",   cfsqltype: "cf_sql_varchar" },
                    placement: { value: item.placement   ?: "",   cfsqltype: "cf_sql_varchar" },
                    spots    : { value: item.spots       ?: 1,    cfsqltype: "cf_sql_integer" },
                    unitCost : { value: item.unit_cost   ?: 0,    cfsqltype: "cf_sql_decimal" },
                    totalCost: { value: item.total_cost  ?: 0,    cfsqltype: "cf_sql_decimal" },
                    sort     : { value: sort,                     cfsqltype: "cf_sql_integer" }
                },
                { datasource: variables.dsn }
            );
            sort++;
        }
    }

}
