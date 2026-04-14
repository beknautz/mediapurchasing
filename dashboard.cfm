<cfset pageTitle  = "Dashboard">
<cfset buySvc    = new components.MediaBuyService()>
<cfset billSvc   = new components.BillingService()>
<cfset buyCounts = buySvc.getDashboardCounts()>
<cfset qCounts   = billSvc.getQueueCounts()>

<!--- Recent media buys for this user --->
<cfset buyerFilter = (session.role EQ "buyer") ? session.user.id : 0>
<cfset recentBuys  = buySvc.getMediaBuys(buyerId=buyerFilter, pageSize=8)>

<!--- Pending approvals --->
<cfset approvalSvc     = new components.ApprovalService()>
<cfset pendingApprovals = approvalSvc.getApprovals(status="pending")>

<cfinclude template="/includes/header.cfm">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="page-title mb-0">Dashboard</h1>
    <cfif listFindNoCase("admin,buyer", session.role)>
        <a href="/media-buys/create.cfm" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Media Buy
        </a>
    </cfif>
</div>

<!--- Stat cards --->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card stat-card">
            <div class="stat-number text-secondary">#buyCounts.drafts#</div>
            <div class="stat-label">Drafts</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card stat-card">
            <div class="stat-number text-warning">#buyCounts.pending_approval#</div>
            <div class="stat-label">Awaiting Approval</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card stat-card">
            <div class="stat-number text-primary">#buyCounts.negotiating#</div>
            <div class="stat-label">Negotiating</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card stat-card">
            <div class="stat-number text-success">#buyCounts.approved#</div>
            <div class="stat-label">Approved</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card stat-card">
            <div class="stat-number text-danger">#qCounts.urgent#</div>
            <div class="stat-label">Urgent Bills</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card stat-card">
            <div class="stat-number text-info">#qCounts.waiting#</div>
            <div class="stat-label">Bills Queued</div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!--- Recent Media Buys --->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart3 me-2"></i>Recent Media Buys</span>
                <a href="/media-buys/index.cfm" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <cfif recentBuys.data.recordCount>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Client</th>
                                    <th>Vendor</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <cfloop query="recentBuys.data">
                                    <tr onclick="location='/media-buys/view.cfm?id=#id#'" style="cursor:pointer">
                                        <td>#encodeForHTML(title)#</td>
                                        <td>#encodeForHTML(client_name)#</td>
                                        <td>#encodeForHTML(vendor_name)#</td>
                                        <td>$#numberFormat(original_cost, '9,999.99')#</td>
                                        <td>
                                            <span class="badge status-badge status-#lCase(status)#">
                                                #replace(status, "_", " ", "all")#
                                            </span>
                                        </td>
                                        <td class="text-muted">#dateFormat(updated_at, "mmm d")#</td>
                                    </tr>
                                </cfloop>
                            </tbody>
                        </table>
                    </div>
                <cfelse>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-cart3 fs-2 d-block mb-2"></i>No media buys yet.
                        <cfif listFindNoCase("admin,buyer", session.role)>
                            <br><a href="/media-buys/create.cfm" class="btn btn-sm btn-primary mt-2">Create First Buy</a>
                        </cfif>
                    </div>
                </cfif>
            </div>
        </div>
    </div>

    <!--- Pending Approvals --->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-square me-2"></i>Pending Approvals</span>
                <a href="/approvals/index.cfm" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <cfif pendingApprovals.recordCount>
                    <ul class="list-group list-group-flush">
                        <cfloop query="pendingApprovals">
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-semibold small">#encodeForHTML(buy_title)#</div>
                                        <div class="text-muted" style="font-size:.78rem">#encodeForHTML(client_name)#</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning text-dark">Pending</span>
                                        <div class="text-muted" style="font-size:.72rem">
                                            Exp: #dateFormat(expires_at, "mmm d")#
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </cfloop>
                    </ul>
                <cfelse>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-all fs-2 d-block mb-1"></i>No pending approvals
                    </div>
                </cfif>
            </div>
        </div>
    </div>

</div>

<cfinclude template="/includes/footer.cfm">
