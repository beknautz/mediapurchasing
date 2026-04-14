<cfif !isDefined("url.id") OR !val(url.id)>
    <cflocation url="/media-buys/index.cfm" addtoken="false">
</cfif>

<cfset buySvc      = new components.MediaBuyService()>
<cfset emailSvc    = new components.EmailService()>
<cfset approvalSvc = new components.ApprovalService()>
<cfset crmSvc      = new components.CRMService()>
<cfset detail      = buySvc.getMediaBuy(val(url.id))>

<cfif !structCount(detail)>
    <cfset session.flash = "Media buy not found.">
    <cflocation url="/media-buys/index.cfm" addtoken="false">
</cfif>

<cfset b     = detail.buy>
<cfset items = detail.items>
<cfset negs  = detail.negotiations>
<cfset aprvs = detail.approvals>

<!--- Handle POST actions --->
<cfif structKeyExists(form, "action")>
    <cfswitch expression="#form.action#">

        <!--- Send email to vendor --->
        <cfcase value="send_to_vendor">
            <cfset vars = {
                buy_title    : b.title,
                vendor_contact: b.vendor_name,
                media_type   : b.media_type,
                flight_start : dateFormat(b.flight_start,"mmmm d, yyyy"),
                flight_end   : dateFormat(b.flight_end,  "mmmm d, yyyy"),
                market       : b.market,
                original_cost: numberFormat(b.original_cost,"9,999.99"),
                description  : b.description ?: "",
                buyer_name   : b.buyer_name,
                agency_name  : application.appName
            }>
            <cfset emailSvc.sendTemplate(
                slug       = "media_buy_request_vendor",
                toEmail    = b.vendor_email,
                toName     = b.vendor_name,
                vars       = vars,
                mediaBuyId = b.id
            )>
            <cfset buySvc.updateStatus(b.id, "sent_to_vendor")>
            <cfset session.flash = "Request sent to vendor.">
            <cflocation url="/media-buys/view.cfm?id=#b.id#" addtoken="false">
        </cfcase>

        <!--- Request client approval --->
        <cfcase value="request_approval">
            <cfset apResult  = approvalSvc.createApproval(b.id, b.client_id)>
            <cfset baseUrl   = application.settings.app_base_url ?: "">
            <cfset approvalLink = "#baseUrl#/approvals/portal.cfm?token=#apResult.token#">
            <cfset vars = {
                client_contact: b.client_name,
                buy_title     : b.title,
                vendor_name   : b.vendor_name,
                media_type    : b.media_type,
                flight_start  : dateFormat(b.flight_start,"mmmm d, yyyy"),
                flight_end    : dateFormat(b.flight_end,  "mmmm d, yyyy"),
                total_cost    : numberFormat(b.negotiated_cost ?: b.original_cost, "9,999.99"),
                approval_link : approvalLink,
                expires_at    : dateTimeFormat(apResult.expiresAt, "mmm d, yyyy h:mm tt")
            }>
            <cfset emailSvc.sendTemplate(
                slug       = "client_approval_request",
                toEmail    = b.client_email,
                toName     = b.client_name,
                vars       = vars,
                mediaBuyId = b.id,
                approvalId = apResult.id
            )>
            <cfset session.flash = "Approval request sent to client.">
            <cflocation url="/media-buys/view.cfm?id=#b.id#" addtoken="false">
        </cfcase>

        <!--- Save negotiation offer --->
        <cfcase value="negotiate">
            <cfset lastRound = val(negs.recordCount ? negs.round[negs.recordCount] : 0)>
            <cfset buySvc.saveNegotiation({
                media_buy_id : b.id,
                round        : lastRound + 1,
                buyer_offer  : val(form.offer_amount),
                buyer_notes  : form.offer_notes ?: ""
            })>
            <!--- Send counter email to vendor --->
            <cfset vars = {
                buy_title      : b.title,
                vendor_contact : b.vendor_name,
                vendor_rate    : numberFormat(b.original_cost,"9,999.99"),
                proposed_rate  : numberFormat(form.offer_amount,"9,999.99"),
                buyer_notes    : form.offer_notes ?: "",
                buyer_name     : b.buyer_name
            }>
            <cfset emailSvc.sendTemplate(
                slug       = "negotiation_counter",
                toEmail    = b.vendor_email,
                toName     = b.vendor_name,
                vars       = vars,
                mediaBuyId = b.id
            )>
            <cfset session.flash = "Negotiation offer sent.">
            <cflocation url="/media-buys/view.cfm?id=#b.id#" addtoken="false">
        </cfcase>

        <!--- Finalize agreed cost --->
        <cfcase value="finalize">
            <cfset buySvc.finalizeNegotiation(b.id, val(form.agreed_cost))>
            <cfset session.flash = "Media buy finalized at $#numberFormat(form.agreed_cost,'9,999.99')#.">
            <cflocation url="/media-buys/view.cfm?id=#b.id#" addtoken="false">
        </cfcase>

        <!--- Update status manually --->
        <cfcase value="update_status">
            <cfset buySvc.updateStatus(b.id, form.new_status, form.status_notes ?: "")>
            <cfset session.flash = "Status updated.">
            <cflocation url="/media-buys/view.cfm?id=#b.id#" addtoken="false">
        </cfcase>

    </cfswitch>
</cfif>

<!--- Reload detail after any changes --->
<cfset detail = buySvc.getMediaBuy(val(url.id))>
<cfset b = detail.buy>
<cfset items = detail.items>
<cfset negs  = detail.negotiations>
<cfset aprvs = detail.approvals>

<cfset pageTitle = b.title>
<cfinclude template="/includes/header.cfm">
<cfoutput>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/media-buys/index.cfm">Media Buys</a></li>
        <li class="breadcrumb-item active">#encodeForHTML(b.title)#</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="page-title mb-1">#encodeForHTML(b.title)#</h1>
        <span class="badge status-badge status-#lCase(b.status)# fs-6">
            #replace(b.status,"_"," ","all")#
        </span>
    </div>
    <div class="action-bar">
        <a href="/media-buys/edit.cfm?id=#b.id#" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </div>
</div>

<!--- Status timeline --->
<ul class="status-timeline mb-4">
    <cfset stages = ["draft","sent_to_vendor","under_evaluation","negotiating","pending_client_approval","client_approved","finalized"]>
    <cfset currentIdx = arrayFindNoCase(stages, b.status)>
    <cfloop array="#stages#" index="stage" item="stage">
        <cfset idx = arrayFindNoCase(stages, stage)>
        <cfset cls  = (idx LT currentIdx) ? "done" : ((stage EQ b.status) ? "active" : "")>
        <li class="#cls#">#replace(stage,"_"," ","all")#</li>
    </cfloop>
</ul>

<div class="row g-4">

    <!--- Left: details + line items + actions --->
    <div class="col-lg-8">

        <!--- Summary card --->
        <div class="card mb-4">
            <div class="card-header">Buy Details</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-sm-6"><strong>Client:</strong> #encodeForHTML(b.client_name)#</div>
                    <div class="col-sm-6"><strong>Vendor:</strong> #encodeForHTML(b.vendor_name)#</div>
                    <div class="col-sm-4"><strong>Media Type:</strong> #encodeForHTML(b.media_type)#</div>
                    <div class="col-sm-4"><strong>Market:</strong> #encodeForHTML(b.market)#</div>
                    <div class="col-sm-4"><strong>Buyer:</strong> #encodeForHTML(b.buyer_name)#</div>
                    <cfif len(b.flight_start)>
                        <div class="col-sm-6">
                            <strong>Flight:</strong>
                            #dateFormat(b.flight_start,"mmm d")# – #dateFormat(b.flight_end,"mmm d, yyyy")#
                        </div>
                    </cfif>
                    <div class="col-sm-3"><strong>Original Cost:</strong> $#numberFormat(b.original_cost,"9,999.99")#</div>
                    <cfif b.negotiated_cost GT 0>
                        <div class="col-sm-3 text-success"><strong>Negotiated:</strong> $#numberFormat(b.negotiated_cost,"9,999.99")#</div>
                    </cfif>
                    <cfif len(b.description)>
                        <div class="col-12 mt-2">
                            <strong>Description:</strong>
                            <p class="mb-0 text-muted">#encodeForHTML(b.description)#</p>
                        </div>
                    </cfif>
                </div>
            </div>
        </div>

        <!--- Line Items --->
        <cfif items.recordCount>
            <div class="card mb-4">
                <div class="card-header">Line Items</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Description</th><th>Placement</th><th>Spots</th><th>Unit Cost</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            <cfset runTotal = 0>
                            <cfloop query="items">
                                <tr>
                                    <td>#encodeForHTML(description)#</td>
                                    <td class="text-muted">#encodeForHTML(placement)#</td>
                                    <td>#spots#</td>
                                    <td>$#numberFormat(unit_cost,"9,999.99")#</td>
                                    <td>$#numberFormat(total_cost,"9,999.99")#</td>
                                </tr>
                                <cfset runTotal += total_cost>
                            </cfloop>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">Total</td>
                                <td>$#numberFormat(runTotal,"9,999.99")#</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </cfif>

        <!--- Negotiation history --->
        <cfif negs.recordCount>
            <div class="card mb-4">
                <div class="card-header">Negotiation History</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Round</th><th>Our Offer</th><th>Vendor Counter</th><th>Status</th><th>Notes</th></tr></thead>
                        <tbody>
                            <cfloop query="negs">
                                <tr>
                                    <td>#round#</td>
                                    <td>$#numberFormat(buyer_offer,"9,999.99")#</td>
                                    <td><cfif vendor_counter GT 0>$#numberFormat(vendor_counter,"9,999.99")#</cfif></td>
                                    <td><span class="badge bg-secondary">#status#</span></td>
                                    <td class="small text-muted">#encodeForHTML(buyer_notes)#</td>
                                </tr>
                            </cfloop>
                        </tbody>
                    </table>
                </div>
            </div>
        </cfif>

        <!--- Communication log --->
        <div class="card mb-4" id="commLog">
            <div class="card-header">Communication Log</div>
            <div class="card-body"
                 hx-get="/communications/partial_log.cfm?media_buy_id=#b.id#"
                 hx-trigger="load"
                 hx-swap="innerHTML">
                <div class="text-muted small">Loading…</div>
            </div>
        </div>

    </div>

    <!--- Right: action panel --->
    <div class="col-lg-4">

        <!--- Workflow Actions --->
        <div class="card mb-3">
            <div class="card-header">Workflow Actions</div>
            <div class="card-body d-grid gap-2">

                <!--- Send to vendor --->
                <cfif b.status EQ "draft">
                    <form method="post">
                        <input type="hidden" name="action" value="send_to_vendor">
                        <button type="submit" class="btn btn-primary w-100"
                                data-confirm="Send the buy request email to #encodeForHTMLAttribute(b.vendor_name)#?">
                            <i class="bi bi-envelope me-1"></i>Send Request to Vendor
                        </button>
                    </form>
                </cfif>

                <!--- Negotiate --->
                <cfif listFindNoCase("sent_to_vendor,vendor_responded,negotiating", b.status)>
                    <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#negotiateModal">
                        <i class="bi bi-arrow-left-right me-1"></i>Submit Counter Offer
                    </button>
                </cfif>

                <!--- Finalize --->
                <cfif listFindNoCase("negotiating,vendor_responded,client_approved", b.status)>
                    <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#finalizeModal">
                        <i class="bi bi-check-lg me-1"></i>Finalize Agreed Rate
                    </button>
                </cfif>

                <!--- Request client approval --->
                <cfif listFindNoCase("under_evaluation,negotiating,vendor_responded,sent_to_vendor", b.status)>
                    <form method="post">
                        <input type="hidden" name="action" value="request_approval">
                        <button type="submit" class="btn btn-warning w-100"
                                data-confirm="Send approval request to #encodeForHTMLAttribute(b.client_name)#?">
                            <i class="bi bi-person-check me-1"></i>Request Client Approval
                        </button>
                    </form>
                </cfif>

                <!--- Status override --->
                <cfif session.role EQ "admin">
                    <button class="btn btn-outline-secondary w-100 btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal">
                        <i class="bi bi-arrow-repeat me-1"></i>Override Status
                    </button>
                </cfif>

                <!--- Compose ad-hoc email --->
                <a href="/communications/compose.cfm?media_buy_id=#b.id#" class="btn btn-outline-dark w-100 btn-sm">
                    <i class="bi bi-envelope-plus me-1"></i>Compose Email
                </a>

            </div>
        </div>

        <!--- Approval history --->
        <cfif aprvs.recordCount>
            <div class="card">
                <div class="card-header">Approval History</div>
                <ul class="list-group list-group-flush">
                    <cfloop query="aprvs">
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <span>#encodeForHTML(client_name)#</span>
                                <span class="badge
                                    <cfswitch expression="#status#">
                                        <cfcase value="approved">bg-success</cfcase>
                                        <cfcase value="pending">bg-warning text-dark</cfcase>
                                        <cfcase value="revision_requested">bg-danger</cfcase>
                                        <cfdefaultcase>bg-secondary</cfdefaultcase>
                                    </cfswitch>">
                                    #status#
                                </span>
                            </div>
                            <div class="text-muted" style="font-size:.75rem">
                                Sent: #dateTimeFormat(requested_at,"mmm d h:tt tt")#
                                <cfif len(responded_at)> | Resp: #dateTimeFormat(responded_at,"mmm d h:tt tt")#</cfif>
                            </div>
                            <cfif len(revision_notes)>
                                <div class="fst-italic text-danger small mt-1">"#encodeForHTML(revision_notes)#"</div>
                            </cfif>
                        </li>
                    </cfloop>
                </ul>
            </div>
        </cfif>

    </div>
</div>

<!--- Negotiate Modal --->
<div class="modal fade" id="negotiateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="negotiate">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Counter Offer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Proposed Rate ($)</label>
                        <input type="number" name="offer_amount" class="form-control" step="0.01"
                               value="#numberFormat(b.original_cost,'9999.99')#" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes to Vendor</label>
                        <textarea name="offer_notes" class="form-control" rows="3"
                                  placeholder="Explain your counter offer…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Counter Offer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--- Finalize Modal --->
<div class="modal fade" id="finalizeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="finalize">
                <div class="modal-header">
                    <h5 class="modal-title">Finalize Agreed Rate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Final Agreed Cost ($)</label>
                        <input type="number" name="agreed_cost" class="form-control" step="0.01"
                               value="#numberFormat(b.negotiated_cost ?: b.original_cost, '9999.99')#" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Finalize</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--- Status Override Modal (admin) --->
<cfif session.role EQ "admin">
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="update_status">
                <div class="modal-header">
                    <h5 class="modal-title">Override Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="new_status" class="form-select">
                            <cfloop list="draft,sent_to_vendor,vendor_responded,under_evaluation,pending_client_approval,client_approved,client_revision_requested,negotiating,finalized,cancelled" index="s">
                                <option value="#s#" #(b.status EQ s ? 'selected' : '')#>#replace(s,"_"," ","all")#</option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="status_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
</cfif>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
