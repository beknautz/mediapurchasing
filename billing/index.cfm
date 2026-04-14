<cfset pageTitle  = "Bill Queue">
<cfset billSvc   = new components.BillingService()>
<cfset qCounts   = billSvc.getQueueCounts()>
<cfset statusFilter = url.status ?: "">
<cfset queue     = billSvc.getQueue(status=statusFilter)>

<cfinclude template="/includes/header.cfm">
<cfoutput>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title mb-0">Bill Queue</h1>
    <a href="/billing/create.cfm" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Bill
    </a>
</div>

<!--- Counts bar --->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-number text-warning">#qCounts.waiting#</div>
            <div class="stat-label">Waiting</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-number text-primary">#qCounts.in_progress#</div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-number text-secondary">#qCounts.on_hold#</div>
            <div class="stat-label">On Hold</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-number text-danger">#qCounts.urgent#</div>
            <div class="stat-label">Urgent</div>
        </div>
    </div>
</div>

<!--- Filter tabs --->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link #(!len(statusFilter)?'active':'')#" href="?">All</a></li>
    <cfloop list="waiting,in_progress,on_hold,completed" index="s">
        <li class="nav-item">
            <a class="nav-link #(statusFilter EQ s ? 'active' : '')#" href="?status=#s#">
                #replace(s,"_"," ","all")#
            </a>
        </li>
    </cfloop>
</ul>

<!--- Queue list as cards for visual priority --->
<cfif queue.recordCount>
    <div class="row g-3">
        <cfloop query="queue">
            <div class="col-12">
                <div class="card bill-queue-card priority-#lCase(priority)#">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="fw-semibold">#encodeForHTML(vendor_name)#</div>
                                <div class="text-muted small">
                                    <cfif len(invoice_number)>Invoice ##encodeForHTML(invoice_number)# &bull; </cfif>
                                    <cfif len(invoice_date)>#dateFormat(invoice_date,"mmm d, yyyy")#</cfif>
                                </div>
                                <cfif len(buy_title)>
                                    <div class="small text-muted"><i class="bi bi-cart3 me-1"></i>#encodeForHTML(buy_title)#</div>
                                </cfif>
                            </div>
                            <div class="col-md-2">
                                <div class="fs-5 fw-bold text-primary">$#numberFormat(amount,"9,999.99")#</div>
                            </div>
                            <div class="col-md-2">
                                <span class="badge
                                    <cfswitch expression="#priority#">
                                        <cfcase value="urgent">bg-danger</cfcase>
                                        <cfcase value="high">bg-warning text-dark</cfcase>
                                        <cfcase value="normal">bg-primary</cfcase>
                                        <cfdefaultcase>bg-secondary</cfdefaultcase>
                                    </cfswitch>">
                                    #priority#
                                </span>
                                <span class="badge bg-light text-dark border ms-1">#replace(status,"_"," ","all")#</span>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">
                                    <cfif len(due_at)>
                                        Due: <cfif now() GT due_at><span class="text-danger fw-bold"></cfif>
                                        #dateFormat(due_at,"mmm d")#
                                        <cfif now() GT due_at></span></cfif>
                                    </cfif>
                                </div>
                                <cfif len(assigned_name)>
                                    <div class="small"><i class="bi bi-person me-1"></i>#encodeForHTML(assigned_name)#</div>
                                </cfif>
                            </div>
                            <div class="col-md-2 text-end">
                                <a href="/billing/view.cfm?id=#bill_id#" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </cfloop>
    </div>
<cfelse>
    <div class="text-center text-muted py-5">
        <i class="bi bi-receipt fs-2 d-block mb-2"></i>No bills in queue.
    </div>
</cfif>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
