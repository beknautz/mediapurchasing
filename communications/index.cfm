<cfset pageTitle = "Communications">
<cfset emailSvc = new components.EmailService()>
<cfset history  = emailSvc.getHistory()>

<cfinclude template="/includes/header.cfm">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title mb-0">Communication Log</h1>
    <a href="/communications/compose.cfm" class="btn btn-primary">
        <i class="bi bi-envelope-plus me-1"></i>Compose Email
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <cfif history.recordCount>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Type</th><th>Direction</th><th>From / To</th>
                            <th>Subject</th><th>Status</th><th>Campaign</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <cfloop query="history">
                            <tr>
                                <td>
                                    <span class="badge #(type EQ 'email' ? 'bg-primary' : 'bg-success')#">
                                        <i class="bi bi-#(type EQ 'email' ? 'envelope' : 'phone')#"></i> #type#
                                    </span>
                                </td>
                                <td>
                                    <span class="badge #(direction EQ 'inbound' ? 'bg-info text-dark' : 'bg-secondary')#">
                                        <i class="bi bi-arrow-#(direction EQ 'inbound' ? 'down' : 'up')#-circle me-1"></i>#direction#
                                    </span>
                                </td>
                                <td class="small">
                                    <div>#encodeForHTML(from_address)#</div>
                                    <div class="text-muted">&rarr; #encodeForHTML(to_address)#</div>
                                </td>
                                <td class="small">#encodeForHTML(subject ?: "(SMS)")#</td>
                                <td>
                                    <span class="badge
                                        <cfswitch expression="#status#">
                                            <cfcase value="sent,delivered">bg-success</cfcase>
                                            <cfcase value="failed">bg-danger</cfcase>
                                            <cfcase value="received">bg-info text-dark</cfcase>
                                            <cfdefaultcase>bg-secondary</cfdefaultcase>
                                        </cfswitch>">
                                        #status#
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <cfif val(media_buy_id)>
                                        <a href="/media-buys/view.cfm?id=#media_buy_id#">
                                            <i class="bi bi-cart3"></i> ##media_buy_id#
                                        </a>
                                    </cfif>
                                </td>
                                <td class="small text-muted">#dateTimeFormat(created_at,"mmm d, h:tt tt")#</td>
                            </tr>
                        </cfloop>
                    </tbody>
                </table>
            </div>
        <cfelse>
            <div class="text-center text-muted py-5">
                <i class="bi bi-envelope fs-2 d-block mb-2"></i>No communications logged yet.
            </div>
        </cfif>
    </div>
</div>

<cfinclude template="/includes/footer.cfm">
