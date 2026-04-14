<cfset pageTitle     = "Approvals">
<cfset approvalSvc  = new components.ApprovalService()>
<cfset statusFilter = url.status ?: "">
<cfset approvals    = approvalSvc.getApprovals(status=statusFilter)>

<cfinclude template="/includes/header.cfm">
<cfoutput>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title mb-0">Client Approvals</h1>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link #(!len(statusFilter)?'active':'')#" href="?">All</a></li>
    <cfloop list="pending,approved,revision_requested,rejected,expired" index="s">
        <li class="nav-item">
            <a class="nav-link #(statusFilter EQ s ? 'active' : '')#" href="?status=#s#">
                #replace(s,"_"," ","all")#
            </a>
        </li>
    </cfloop>
</ul>

<div class="card">
    <div class="card-body p-0">
        <cfif approvals.recordCount>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Client</th>
                            <th>Requested</th>
                            <th>Expires</th>
                            <th>Responded</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <cfloop query="approvals">
                            <tr>
                                <td>
                                    <a href="/media-buys/view.cfm?id=#media_buy_id#" class="fw-semibold">
                                        #encodeForHTML(buy_title)#
                                    </a>
                                </td>
                                <td>#encodeForHTML(client_name)#</td>
                                <td class="small text-muted">#dateTimeFormat(requested_at,"mmm d, h:tt tt")#</td>
                                <td class="small <cfif status EQ 'pending' AND now() GT expires_at>text-danger fw-bold</cfif>">
                                    #dateTimeFormat(expires_at,"mmm d, h:tt tt")#
                                </td>
                                <td class="small text-muted">
                                    <cfif len(responded_at)>#dateTimeFormat(responded_at,"mmm d, h:tt tt")#</cfif>
                                </td>
                                <td>
                                    <span class="badge
                                        <cfswitch expression="#status#">
                                            <cfcase value="approved">bg-success</cfcase>
                                            <cfcase value="pending">bg-warning text-dark</cfcase>
                                            <cfcase value="revision_requested">bg-danger</cfcase>
                                            <cfcase value="expired">bg-secondary</cfcase>
                                            <cfdefaultcase>bg-secondary</cfdefaultcase>
                                        </cfswitch>">
                                        #status#
                                    </span>
                                </td>
                                <td class="small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    #encodeForHTML(revision_notes ?: "")#
                                </td>
                                <td>
                                    <a href="/approvals/portal.cfm?token=#token#" target="_blank"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right"></i> Portal
                                    </a>
                                </td>
                            </tr>
                        </cfloop>
                    </tbody>
                </table>
            </div>
        <cfelse>
            <div class="text-center text-muted py-5">
                <i class="bi bi-check2-square fs-2 d-block mb-2"></i>No approvals found.
            </div>
        </cfif>
    </div>
</div>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
