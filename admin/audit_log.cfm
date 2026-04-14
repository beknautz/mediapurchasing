<cfset authSvc = new components.AuthService()>
<cfset authSvc.requireRole("admin")>

<cfset pageTitle = "Audit Log">
<cfset page     = val(url.page ?: 1)>
<cfset pageSize = 50>
<cfset offset   = (page-1)*pageSize>

<cfset logQ = queryExecute(
    "SELECT al.*, u.name AS user_name
       FROM audit_log al
  LEFT JOIN users u ON u.id = al.user_id
      ORDER BY al.created_at DESC
      LIMIT :limit OFFSET :offset",
    {
        limit : { value: pageSize, cfsqltype: "cf_sql_integer" },
        offset: { value: offset,   cfsqltype: "cf_sql_integer" }
    },
    { datasource: application.datasource }
)>
<cfset totalQ = queryExecute("SELECT COUNT(*) AS cnt FROM audit_log", {}, { datasource: application.datasource })>
<cfset totalPages = ceiling(totalQ.cnt / pageSize)>

<cfinclude template="/includes/header.cfm">

<h1 class="page-title">Audit Log</h1>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th>Date</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <cfloop query="logQ">
                        <tr>
                            <td class="small text-muted text-nowrap">#dateTimeFormat(created_at,"mmm d yyyy, h:mm tt")#</td>
                            <td class="small">#encodeForHTML(user_name ?: "System")#</td>
                            <td><code class="small">#encodeForHTML(action)#</code></td>
                            <td class="small text-muted">
                                #encodeForHTML(entity_type)# <cfif entity_id>##entity_id#</cfif>
                            </td>
                            <td class="small text-muted" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                #encodeForHTML(details)#
                            </td>
                            <td class="small text-muted">#encodeForHTML(ip_address)#</td>
                        </tr>
                    </cfloop>
                </tbody>
            </table>
        </div>

        <cfif totalPages GT 1>
            <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
                <small class="text-muted">#totalQ.cnt# total entries</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <cfloop from="1" to="#min(totalPages,20)#" index="pg">
                            <li class="page-item #(pg EQ page ? 'active' : '')#">
                                <a class="page-link" href="?page=#pg#">#pg#</a>
                            </li>
                        </cfloop>
                    </ul>
                </nav>
            </div>
        </cfif>
    </div>
</div>

<cfinclude template="/includes/footer.cfm">
